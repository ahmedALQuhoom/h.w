<?php
/**
 * ==========================================
 * Tool 11: SQL Injection (SQLi) Defense Demo
 * Created by: Ahmed_Q
 * ==========================================
 */

declare(strict_types=1);

// Set strict JSON response headers
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache");

// Helper function to send standardized JSON responses
function sendJsonResponse(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Ensure the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("Allow: GET");
    sendJsonResponse(405, ['error' => 'Method not allowed. Use GET.']);
}

// Retrieve and validate input parameters
$mode = $_GET['mode'] ?? 'safe';
$username = $_GET['username'] ?? '';

// Validate mode parameter
if (!in_array($mode, ['safe', 'vulnerable'], true)) {
    sendJsonResponse(400, ['error' => 'Invalid mode. Choose either "safe" or "vulnerable".']);
}

// Validate username parameter presence
if (trim($username) === '') {
    sendJsonResponse(400, ['error' => 'Username parameter is required.']);
}

try {
    // Initialize an in-memory SQLite database using PDO for quick demonstration
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create a sample users table
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            full_name TEXT NOT NULL
        )
    ");

    // Seed dummy records safely
    $seedStmt = $pdo->prepare("INSERT INTO users (username, full_name) VALUES (:username, :full_name)");
    $seedData = [
        ['username' => 'ali', 'full_name' => 'Ali Ahmed'],
        ['username' => 'sara', 'full_name' => 'Sara Mohamed'],
        ['username' => 'omar', 'full_name' => 'Omar Khaled'],
    ];
    foreach ($seedData as $row) {
        $seedStmt->execute($row);
    }

    $results = [];

    // Branch 1: Safe Mode (Using Prepared Statements)
    if ($mode === 'safe') {
        // Prepared statement separates user data from SQL structure completely
        $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $results = $stmt->fetchAll();
    } 
    // Branch 2: Vulnerable Mode (Direct String Concatenation - Educational Demo Only)
    else {
        // DANGER: Direct concatenation allows SQL Injection payloads to alter query logic
        $sql = "SELECT id, username, full_name FROM users WHERE username = '{$username}'";
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
    }

    sendJsonResponse(200, [
        'mode' => $mode,
        'query_input' => $username,
        'results_count' => count($results),
        'results' => $results,
        'warning' => ($mode === 'vulnerable') ? 'WARNING: This mode is insecure and vulnerable to SQLi.' : null
    ]);

} catch (Throwable $e) {
    // Log the actual error internally and return a generic safe message to the user
    error_log('SQLi Demo Error: ' . $e->getMessage());
    sendJsonResponse(500, ['error' => 'A secure internal database error occurred.']);
}
?>