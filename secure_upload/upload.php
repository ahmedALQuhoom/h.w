<?php
/**
 * ==========================================
 * Tool 12: Secure File Uploads (Defense-in-Depth)
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

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Allow: POST");
    sendJsonResponse(405, ['error' => 'Method not allowed. Use POST.']);
}

// Define upload configuration constants
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 Megabytes limit
const ALLOWED_MIME_TYPES = [
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'pdf'  => 'application/pdf',
];

// Verify if a file has been uploaded without errors
if (!isset($_FILES['uploaded_file']) || $_FILES['uploaded_file']['error'] !== UPLOAD_ERR_OK) {
    sendJsonResponse(400, ['error' => 'No file uploaded or an upload error occurred.']);
}

$file = $_FILES['uploaded_file'];

// 1. Validate file size strictly
if ($file['size'] > MAX_FILE_SIZE) {
    sendJsonResponse(400, ['error' => 'File exceeds the maximum allowed size of 5MB.']);
}

// 2. Validate file extension using whitelist strategy
$originalName = basename($file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!array_key_exists($extension, ALLOWED_MIME_TYPES)) {
    sendJsonResponse(400, ['error' => 'Invalid file extension. Allowed types: jpg, png, pdf.']);
}

// 3. Inspect actual file content using Fileinfo (MIME Type Inspection)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMimeType = $finfo->file($file['tmp_name']);

if ($detectedMimeType !== ALLOWED_MIME_TYPES[$extension]) {
    sendJsonResponse(400, ['error' => 'File content does not match its extension (MIME type mismatch).']);
}

// Additional validation for images (ensure GD can read it safely)
if (in_array($extension, ['jpg', 'png'], true)) {
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        sendJsonResponse(400, ['error' => 'Uploaded file is not a valid image.']);
    }
}

// 4. Define an isolated storage directory outside the public web root
// (In production, ensure this folder is outside document_root)
$uploadDir = __DIR__ . '/secure_uploads_dir/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0750, true);
}

// 5. Generate a cryptographically secure random filename to prevent Path Traversal & collisions
$secureFilename = bin2hex(random_bytes(16)) . '.' . $extension;
$destination = $uploadDir . $secureFilename;

// 6. Move the uploaded file securely
if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $destination)) {
    sendJsonResponse(500, ['error' => 'Failed to securely move the uploaded file.']);
}

// Return successful response with metadata (never expose internal server paths)
sendJsonResponse(201, [
    'success' => true,
    'message' => 'File uploaded successfully using defense-in-depth validation.',
    'original_name' => $originalName,
    'stored_filename' => $secureFilename,
    'file_size' => $file['size'],
    'mime_type' => $detectedMimeType
]);
?>