<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../config/db.connection.php";

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

// Which question is this for? Its AllowDocument/AllowImage flags decide the
// permitted extensions, so a student can't slip an image onto a doc-only question.
$questionId = (int)($_POST["question_id"] ?? 0);
if (!$questionId) {
  http_response_code(400);
  echo json_encode(["error" => "Missing question_id"]);
  exit;
}

$stmt = $pdo->prepare("SELECT `OpenQuestion`, `AllowDocument`, `AllowImage` FROM `PQQuestion` WHERE `Id` = ?");
$stmt->execute([$questionId]);
$q = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$q || (int)$q["OpenQuestion"] !== 1) {
  http_response_code(404);
  echo json_encode(["error" => "Question not found or not an open question"]);
  exit;
}

$docExt = ["pdf", "doc", "docx", "txt"];
$imgExt = ["png", "jpg", "jpeg"];
$allowed = [];
if ((int)$q["AllowDocument"] === 1) $allowed = array_merge($allowed, $docExt);
if ((int)$q["AllowImage"]    === 1) $allowed = array_merge($allowed, $imgExt);

if (!$allowed) {
  http_response_code(403);
  echo json_encode(["error" => "Deze vraag accepteert geen bestanden"]);
  exit;
}

$file     = $_FILES["file"];
$origName = $file["name"];
$tmpPath  = $file["tmp_name"];
$size     = $file["size"];

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
  http_response_code(415);
  echo json_encode(["error" => "Bestandstype niet toegestaan: .$ext"]);
  exit;
}

// 25MB limit for student submissions.
if ($size > 25 * 1024 * 1024) {
  http_response_code(413);
  echo json_encode(["error" => "Bestand te groot (max 25MB)"]);
  exit;
}

$uploadDir = __DIR__ . "/../uploads/";
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0775, true);
}

// Safe unique filename — the random suffix keeps the path unguessable and matches
// the pattern quiz_submit.php validates before storing it.
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
  "path"          => "uploads/" . $unique,
  "original_name" => $origName,
  "size"          => $size,
]);
