<?php
require_once 'core/dbConfig.php';

if (!isset($_SESSION['user_id'])) {
  session_start();
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
  }
}

$docId = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;
$userId = $_SESSION['user_id'];

// Fetch document info and access
$stmt = $pdo->prepare("
  SELECT d.*, s.can_edit
  FROM written_documents d
  LEFT JOIN document_shared_users s ON d.doc_id = s.doc_id AND s.user_id = :uid
  WHERE d.doc_id = :doc_id
  LIMIT 1
");
$stmt->execute(['doc_id' => $docId, 'uid' => $userId]);
$document = $stmt->fetch();

if (!$document) {
  die("Document not found or access denied.");
}

$isOwner = $document['user_id'] == $userId;
$canEdit = $isOwner || (!empty($document['can_edit']) && $document['can_edit'] == 0);

// Fetch logs
$logStmt = $pdo->prepare("
  SELECT l.*, u.username
  FROM document_activity_logs l
  JOIN gdocs_users u ON l.user_id = u.user_id
  WHERE l.doc_id = ?
  ORDER BY l.created_at DESC
");
$logStmt->execute([$docId]);
$logs = $logStmt->fetchAll();

// Fetch comments
$commentStmt = $pdo->prepare("
  SELECT c.*, u.username 
  FROM document_comments c 
  JOIN gdocs_users u ON c.user_id = u.user_id 
  WHERE c.doc_id = ? 
  ORDER BY c.created_at ASC
");
$commentStmt->execute([$docId]);
$allComments = $commentStmt->fetchAll();

// Organize comments and replies
$comments = [];
foreach ($allComments as $comment) {
  if ($comment['parent_comment_id'] === null) {
    $comments[$comment['comment_id']] = $comment;
    $comments[$comment['comment_id']]['replies'] = [];
  } else {
    $comments[$comment['parent_comment_id']]['replies'][] = $comment;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($document['title']) ?> - View</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans p-6">

  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <form method="POST" action="core/handleForms.php">
      <input type="hidden" name="updateDocumentBtn" value="1">
      <input type="hidden" name="doc_id" value="<?= $document['doc_id'] ?>">

      <h1 class="text-2xl font-semibold mb-4">
        <?php if ($canEdit): ?>
          <input type="text" name="title" value="<?= htmlspecialchars($document['title']) ?>" class="w-full border p-2 rounded">
        <?php else: ?>
          <?= htmlspecialchars($document['title']) ?>
        <?php endif; ?>
      </h1>

      <div class="mb-4">
        <?php if ($canEdit): ?>
          <textarea name="content" class="w-full h-64 p-4 border rounded"><?= htmlspecialchars($document['content']) ?></textarea>
        <?php else: ?>
          <div class="prose max-w-full border p-4 rounded bg-gray-50 whitespace-pre-wrap"><?= htmlspecialchars($document['content']) ?></div>
        <?php endif; ?>
      </div>

      <?php if ($canEdit): ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Save Changes
        </button>
      <?php endif; ?>
    </form>

    <div class="mt-6">
      <a href="index.php" class="text-blue-500 hover:underline">← Back to Dashboard</a>
    </div>

    <!-- Activity Logs -->
    <div class="mt-8 bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">Activity Logs</h2>
      <?php if (count($logs) > 0): ?>
        <ul class="text-sm text-gray-700 space-y-2">
          <?php foreach ($logs as $log): ?>
            <li class="border-b pb-2">
              <strong><?= htmlspecialchars($log['username']) ?></strong> <?= htmlspecialchars($log['action']) ?>
              <span class="text-gray-500 text-xs">on <?= date('F j, Y \a\t g:i A', strtotime($log['created_at'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-gray-500">No activity yet.</p>
      <?php endif; ?>
    </div>

    <!-- Comments Section -->
    <div class="mt-8 bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">Comments</h2>

      <!-- Add a comment -->
      <form method="POST" action="core/handleForms.php" class="mb-6">
        <input type="hidden" name="addComment" value="1">
        <input type="hidden" name="doc_id" value="<?= $docId ?>">
        <textarea name="content" required class="w-full p-3 border rounded mb-2" placeholder="Write a comment..."></textarea>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Post Comment</button>
      </form>

      <?php foreach ($comments as $comment): ?>
        <div class="mb-4">
          <div class="p-3 border rounded bg-gray-50">
            <strong><?= htmlspecialchars($comment['username']) ?></strong> 
            <span class="text-sm text-gray-500"><?= date('F j, Y g:i A', strtotime($comment['created_at'])) ?></span>
            <p class="mt-2"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
          </div>

          <!-- Reply form -->
          <form method="POST" action="core/handleForms.php" class="ml-6 mt-2">
            <input type="hidden" name="addReply" value="1">
            <input type="hidden" name="doc_id" value="<?= $docId ?>">
            <input type="hidden" name="parent_comment_id" value="<?= $comment['comment_id'] ?>">
            <textarea name="content" required class="w-full p-2 border rounded mb-2 text-sm" placeholder="Reply..."></textarea>
            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm">Reply</button>
          </form>

          <!-- Replies -->
          <?php if (!empty($comment['replies'])): ?>
            <div class="ml-6 mt-2 space-y-2">
              <?php foreach ($comment['replies'] as $reply): ?>
                <div class="p-2 border rounded bg-gray-100">
                  <strong><?= htmlspecialchars($reply['username']) ?></strong>
                  <span class="text-xs text-gray-500"><?= date('F j, Y g:i A', strtotime($reply['created_at'])) ?></span>
                  <p class="mt-1 text-sm"><?= nl2br(htmlspecialchars($reply['content'])) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

</body>
</html>
