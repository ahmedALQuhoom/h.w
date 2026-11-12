<?php
/**
 * ==========================================
 * Tool 10: PHP REST API
 * Created by: Ahmed_Q
 * ==========================================
 */

// Set the response header to return JSON format strictly
header("Content-Type: application/json; charset=UTF-8");

// Path to the local temporary JSON storage file
$dataFile = __DIR__ . '/data.json';

/**
 * Helper function to read and manage files with flock protection
 * to ensure data integrity during concurrent requests.
 */
function useStore(string $filePath, callable $callback) {
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([], JSON_PRETTY_PRINT));
    }
    
    $fileHandle = fopen($filePath, 'r+');
    if (!$fileHandle) {
        throw new Exception("Failed to open data file.");
    }

    // Activate file lock to prevent race conditions
    if (flock($fileHandle, LOCK_EX)) {
        $fileSize = filesize($filePath);
        $rawContent = $fileSize > 0 ? fread($fileHandle, $fileSize) : '[]';
        $items = json_decode($rawContent, true);
        if (!is_array($items)) {
            $items = [];
        }

        // Execute the requested operation on items
        $result = $callback($items);

        if (isset($result['save']) && $result['save'] === true) {
            ftruncate($fileHandle, 0);
            rewind($fileHandle);
            fwrite($fileHandle, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);

        return $result;
    } else {
        fclose($fileHandle);
        throw new Exception("Failed to acquire file lock for operation.");
    }
}

/**
 * Helper function to send JSON response with custom HTTP status code
 */
function sendJson(int $statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Read incoming request method (GET, POST, PUT, DELETE)
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Extract item ID from query string if provided (e.g., ?id=1)
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // Receive and parse incoming request body as JSON
    $body = null;
    if (in_array($method, ['POST', 'PUT'])) {
        $rawInput = file_get_contents("php://input");
        if (!empty($rawInput)) {
            $body = json_decode($rawInput, true);
        }
    }

    // Build conditional structure to handle different request methods
    $response = useStore($dataFile, function (array &$items) use ($method, $id, $body) {
        switch ($method) {
            
            // 1. Read Request (GET)
            case 'GET':
                if ($id !== null) {
                    $filtered = array_values(array_filter($items, fn($item) => $item['id'] === $id));
                    if (empty($filtered)) {
                        return ['save' => false, 'status' => 404, 'data' => ['error' => 'Item not found.']];
                    }
                    return ['save' => false, 'status' => 200, 'data' => $filtered[0]];
                }
                return ['save' => false, 'status' => 200, 'data' => $items];

            // 2. Create Request (POST)
            case 'POST':
                if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
                    return ['save' => false, 'status' => 400, 'data' => ['error' => 'Request must contain Content-Type: application/json']];
                }
                if (!$body || !isset($body['name'])) {
                    return ['save' => false, 'status' => 400, 'data' => ['error' => 'Invalid request payload or missing "name" field.']];
                }

                // Generate a new sequential automatic ID
                $newId = empty($items) ? 1 : max(array_column($items, 'id')) + 1;
                $newItem = [
                    'id' => $newId,
                    'name' => trim($body['name']),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $items[] = $newItem;
                return ['save' => true, 'status' => 201, 'data' => $newItem];

            // 3. Update Request (PUT)
            case 'PUT':
                if ($id === null) {
                    return ['save' => false, 'status' => 400, 'data' => ['error' => 'Item ID must be specified for update.']];
                }
                if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
                    return ['save' => false, 'status' => 400, 'data' => ['error' => 'Request must contain Content-Type: application/json']];
                }

                $found = false;
                $updatedItem = null;
                foreach ($items as &$item) {
                    if ($item['id'] === $id) {
                        if (isset($body['name'])) {
                            $item['name'] = trim($body['name']);
                        }
                        $item['updated_at'] = date('Y-m-d H:i:s');
                        $updatedItem = $item;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return ['save' => false, 'status' => 404, 'data' => ['error' => 'Item to update not found.']];
                }
                return ['save' => true, 'status' => 200, 'data' => $updatedItem];

            // 4. Delete Request (DELETE)
            case 'DELETE':
                if ($id === null) {
                    return ['save' => false, 'status' => 400, 'data' => ['error' => 'Item ID must be specified for deletion.']];
                }

                $initialCount = count($items);
                $items = array_values(array_filter($items, fn($item) => $item['id'] !== $id));

                if (count($items) === $initialCount) {
                    return ['save' => false, 'status' => 404, 'data' => ['error' => 'Item not found for deletion.']];
                }
                return ['save' => true, 'status' => 200, 'data' => ['message' => 'Item deleted successfully.']];

            // 5. Unsupported HTTP methods
            default:
                return ['save' => false, 'status' => 405, 'data' => ['error' => 'Method not supported.']];
        }
    });

    // Return the final response with the appropriate status code
    sendJson($response['status'], $response['data']);

} catch (Exception $e) {
    sendJson(500, ['error' => 'System error: ' . $e->getMessage()]);
}
?>