<?php
require_once 'core/dbConfig.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$docId = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;
$userId = $_SESSION['user_id'];

// Check if user has access to the document
$stmt = $pdo->prepare("
  SELECT d.*, s.user_id AS shared_user_id
  FROM written_documents d
  LEFT JOIN document_shared_users s ON d.doc_id = s.doc_id AND s.user_id = :uid
  WHERE d.doc_id = :doc_id
");
$stmt->execute(['doc_id' => $docId, 'uid' => $userId]);
$document = $stmt->fetch();

if (!$document || ($document['user_id'] != $userId && !$document['shared_user_id'])) {
  die("Access denied or document not found.");
}

$isOwner = $document['user_id'] == $userId;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Document</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($document['title']) ?></h1>
    <div class="prose max-w-full border p-4 rounded bg-gray-50 whitespace-pre-wrap mb-6"><?= htmlspecialchars($document['content']) ?></div>

    <a href="index.php" class="text-blue-600 hover:underline mb-6 inline-block">← Back to Dashboard</a>

    <?php if ($isOwner): ?>
    <!-- Share Document Section -->
    <section class="mt-6">
      <h2 class="text-xl font-semibold mb-3">Share Document</h2>
      <form id="share-form" method="POST" action="core/handleForms.php" class="space-y-4">
        <input type="hidden" name="doc_id" value="<?= $docId ?>">
        <label for="user-search" class="block font-medium">Search User:</label>
        <input type="text" id="user-search" class="w-full border p-2 rounded" placeholder="Type a username...">
        <div id="search-results" class="border rounded max-h-48 overflow-auto hidden mt-1"></div>
        <input type="hidden" name="user_id" id="selected-user-id">
        <button type="submit" name="shareDocumentBtn" id="share-btn" disabled class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Share</button>
      </form>
    </section>
    <?php endif; ?>
  </div>

  <script>
    const userSearch = document.getElementById('user-search');
    const searchResults = document.getElementById('search-results');
    const selectedUserIdInput = document.getElementById('selected-user-id');
    const shareBtn = document.getElementById('share-btn');

    let timeout = null;

    userSearch.addEventListener('input', () => {
      clearTimeout(timeout);
      const query = userSearch.value.trim();
      selectedUserIdInput.value = '';
      shareBtn.disabled = true;

      if (query.length < 2) {
        searchResults.innerHTML = '';
        searchResults.classList.add('hidden');
        return;
      }

      timeout = setTimeout(() => {
        fetch(`core/handleForms.php?action=search_users&q=${encodeURIComponent(query)}`)
          .then(res => res.json())
          .then(users => {
            if (users.length === 0) {
              searchResults.innerHTML = '<p class="p-2 text-gray-500">No users found.</p>';
              searchResults.classList.remove('hidden');
              return;
            }

            searchResults.innerHTML = users.map(user => `
              <div class="p-2 cursor-pointer hover:bg-blue-100" data-user-id="${user.user_id}">
                ${user.username} (${user.first_name} ${user.last_name})
              </div>
            `).join('');
            searchResults.classList.remove('hidden');

            [...searchResults.children].forEach(div => {
              div.addEventListener('click', () => {
                selectedUserIdInput.value = div.dataset.userId;
                userSearch.value = div.textContent.trim();
                searchResults.classList.add('hidden');
                shareBtn.disabled = false;
              });
            });
          })
          .catch(() => {
            searchResults.innerHTML = '<p class="p-2 text-red-500">Error fetching users.</p>';
            searchResults.classList.remove('hidden');
          });
      }, 300);
    });

    document.addEventListener('click', (e) => {
      if (!userSearch.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.classList.add('hidden');
      }
    });
  </script>
</body>
</html>
