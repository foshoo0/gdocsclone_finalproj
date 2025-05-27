<?php
require_once 'core/dbConfig.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$title = trim($data['title']);
$content = trim($data['content']);
$userId = $_SESSION['user_id'];

// Create or update a draft (for simplicity, we’ll use one active doc per user)
$sqlCheck = "SELECT doc_id FROM written_documents WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $pdo->prepare($sqlCheck);
$stmt->execute([$userId]);
$existingDoc = $stmt->fetch();

if ($existingDoc) {
  $sql = "UPDATE written_documents SET title = ?, content = ? WHERE doc_id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$title, $content, $existingDoc['doc_id']]);
} else {
  $sql = "INSERT INTO written_documents (user_id, title, content) VALUES (?, ?, ?)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId, $title, $content]);
}

echo json_encode(['status' => 'success']);
