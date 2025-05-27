<?php
require_once 'core/dbConfig.php';

// Optional: redirect if not admin
if (!isset($_SESSION['username']) || $_SESSION['is_client'] != 1) {
    header("Location: login.php");
    exit;
}

// Check for document ID
if (!isset($_GET['doc_id'])) {
    echo "Document not found.";
    exit;
}

$docId = $_GET['doc_id'];

// Fetch document and user info
$stmt = $pdo->prepare("
    SELECT d.*, u.username, u.first_name, u.last_name
    FROM written_documents d
    JOIN gdocs_users u ON d.user_id = u.user_id
    WHERE d.doc_id = ?
");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    echo "Document not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - View Document</title>
  <link rel="stylesheet" href="https://cdn.tailwindcss.com">
</head>
<body class="bg-gray-100 p-8">
  <div class="max-w-4xl mx-auto bg-white p-6 shadow rounded">
    <h1 class="text-2xl font-bold mb-4">Document: <?= htmlspecialchars($doc['title']) ?></h1>
    <p class="text-sm text-gray-600 mb-2">By: <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?> (<?= htmlspecialchars($doc['username']) ?>)</p>
    <p class="text-sm text-gray-500 mb-6">Created: <?= $doc['created_at'] ?> | Updated: <?= $doc['updated_at'] ?></p>

    <?php if ($doc['is_file_upload']): ?>
      <p class="mb-4 text-blue-600">This document was uploaded as a file.</p>
      <a href="../uploads/<?= htmlspecialchars($doc['title']) ?>" target="_blank" class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
        View Uploaded File
      </a>
    <?php else: ?>
      <div class="border p-4 rounded bg-gray-50 whitespace-pre-wrap">
        <?= nl2br(htmlspecialchars($doc['content'])) ?>
      </div>
    <?php endif; ?>

    <div class="mt-6">
      <a href="index.php" class="text-blue-500 hover:underline">← Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
