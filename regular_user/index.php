<?php
require_once 'core/dbConfig.php';
require_once 'core/models.php';


if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit;
}

if ($_SESSION['is_client'] == 1) {
  header("Location: ../admin_user/index.php");
  exit;
}

// Check if user is suspended (optional, but recommended)
$stmt = $pdo->prepare("SELECT is_suspended FROM gdocs_users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userStatus = $stmt->fetch();
if ($userStatus && $userStatus['is_suspended']) {
  session_destroy();
  die('Your account is suspended. Contact admin.');
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
  $uploadDir = 'uploads/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
  }

  $filename = basename($_FILES['document']['name']);
  $targetPath = $uploadDir . $filename;

  if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
    // Insert into DB
    $stmt = $pdo->prepare("INSERT INTO written_documents (user_id, title, content) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $filename, "Uploaded File"]);
  }
}

// Fetch documents owned by user OR shared with user
$stmt = $pdo->prepare("
  SELECT d.*
  FROM written_documents d
  LEFT JOIN document_shared_users s ON d.doc_id = s.doc_id
  WHERE d.user_id = ? OR s.user_id = ?
  GROUP BY d.doc_id
  ORDER BY d.updated_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$documents = $stmt->fetchAll();
$latestDoc = $documents[0] ?? ['title' => '', 'content' => ''];


// Pick a doc_id for demo of sharing feature (first doc)
$docId = $documents[0]['doc_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Dashboard - MyDocs</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

  <!-- Header -->
  <header class="bg-white shadow px-6 py-4 flex justify-between items-center">
    <h1 class="text-xl font-bold text-blue-600">MyDocs</h1>
    <div class="flex items-center space-x-4">
      <span class="text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
      <img src="https://i.pravatar.cc/40" alt="Profile" class="w-10 h-10 rounded-full">
    </div>
  </header>

  <div class="flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-white h-screen shadow-lg p-6">
      <nav class="space-y-4">
        <a href="index.php" class="block text-blue-600 font-semibold">Dashboard</a>
        <a href="documents.php" class="block text-gray-600 hover:text-blue-600">My Documents</a>
        <a href="core/handleForms.php?logoutUserBtn=1" class="block text-gray-600 hover:text-blue-600">Logout</a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto max-w-4xl">

      <!-- Upload Section -->
      <h2 class="text-2xl font-semibold mb-4">Upload a Document</h2>
      <form action="" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md w-full max-w-xl mb-10">
        <label class="block mb-2 text-gray-700 font-medium">Choose a file</label>
        <input type="file" name="document" required class="w-full border border-gray-300 p-2 rounded mb-4">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Upload</button>
      </form>

      <!-- Create New Document Section -->
      <h2 class="text-2xl font-semibold mb-4">Create a New Document</h2>
      <input id="doc-title" class="w-full p-2 border mb-2" placeholder="Document Title"
       value="<?= htmlspecialchars($latestDoc['title']) ?>" />
       <textarea id="doc-content" class="w-full h-64 p-4 border" placeholder="Start typing here..."><?= htmlspecialchars($latestDoc['content']) ?></textarea>
       <p id="save-status" class="text-sm text-green-600 mt-2">All changes saved</p>


      <!-- Uploaded Documents List -->
      <h3 class="text-xl font-semibold mt-10 mb-4">Your Documents (Owned or Shared)</h3>
      <div class="bg-white shadow rounded-lg p-4 space-y-2">
        <?php foreach ($documents as $doc): ?>
        <div class="flex justify-between items-center border-b pb-2">
          <span class="text-gray-800"><?= htmlspecialchars($doc['title']) ?></span>
          <a href="view.php?doc_id=<?= $doc['doc_id'] ?>" class="text-sm text-blue-500 hover:underline">View</a>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Add Users to Document Section -->
      <section class="bg-white p-6 rounded shadow mt-10 max-w-xl">
        <h2 class="text-2xl font-semibold mb-4">Add Users to Document</h2>
        <?php if ($docId): ?>
          <form id="share-form" method="POST" action="core/handleForms.php" class="space-y-4">
            <input type="hidden" name="doc_id" value="<?= htmlspecialchars($docId) ?>">
            <label for="user-search" class="block font-medium">Search Users:</label>
            <input type="text" id="user-search" autocomplete="off" class="w-full border p-2 rounded" placeholder="Start typing username...">
            <div id="search-results" class="border rounded max-h-48 overflow-auto hidden mt-1"></div>
            
            <input type="hidden" name="user_id" id="selected-user-id">
            <button type="submit" name="shareDocumentBtn" id="share-btn" disabled class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Share Document</button>
          </form>
        <?php else: ?>
          <p>No document available to share.</p>
        <?php endif; ?>
      </section>

    </main>
  </div>

  <!-- Auto-save Script -->
  <script>
    let saveTimeout;
    const titleInput = document.getElementById('doc-title');
    const contentArea = document.getElementById('doc-content');
    const saveStatus = document.getElementById('save-status');

    function autosave() {
      clearTimeout(saveTimeout);
      saveTimeout = setTimeout(() => {
        fetch('autosave.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            title: titleInput.value,
            content: contentArea.value
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            saveStatus.textContent = 'Saved at ' + new Date().toLocaleTimeString();
          } else {
            saveStatus.textContent = 'Error saving...';
          }
        });
      }, 1000);
    }

    titleInput.addEventListener('input', autosave);
    contentArea.addEventListener('input', autosave);
  </script>

  <!-- User Search and Share Script -->
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
