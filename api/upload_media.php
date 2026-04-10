<?php
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(["error" => "No file uploaded or upload error"]);
  exit;
}

$file = $_FILES["file"];
$origName = $file["name"];
$tmpPath = $file["tmp_name"];
$size = $file["size"];

// Basic validation
$allowedExt = ["jpg","jpeg","png","gif","webp","svg","mp4","webm","mov","ogg"];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
  http_response_code(415);
  echo json_encode(["error" => "Unsupported file type: .$ext"]);
  exit;
}

// 50MB limit
if ($size > 50 * 1024 * 1024) {
  http_response_code(413);
  echo json_encode(["error" => "File too large (max 50MB)"]);
  exit;
}

// Ensure uploads directory exists
$uploadDir = __DIR__ . "/../uploads/";
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0775, true);
}

// Generate safe unique filename
$base = preg_replace("/[^a-zA-Z0-9_-]/", "_", pathinfo($origName, PATHINFO_FILENAME));
$base = substr($base, 0, 40);
$unique = $base . "_" . bin2hex(random_bytes(4)) . "." . $ext;
$destPath = $uploadDir . $unique;

if (!move_uploaded_file($tmpPath, $destPath)) {
  http_response_code(500);
  echo json_encode(["error" => "Failed to save file"]);
  exit;
}

echo json_encode([
  "path" => "uploads/" . $unique,
  "original_name" => $origName,
  "size" => $size,
]);
