<?php
require_once 'core/dbConfig.php';
require_once 'core/models.php';

// Redirect if not admin
if (!isset($_SESSION['username']) || $_SESSION['is_client'] != 1) {
  header("Location: login.php");
  exit;
}

// Handle suspend toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['suspend_toggle'])) {
  $userId = intval($_POST['user_id']);
  $isSuspended = isset($_POST['is_suspended']) ? 1 : 0;

  $stmt = $pdo->prepare("UPDATE gdocs_users SET is_suspended = ? WHERE user_id = ?");
  $stmt->execute([$isSuspended, $userId]);
}

// Fetch all users (excluding admins)
$usersStmt = $pdo->query("SELECT user_id, username, first_name, last_name, is_suspended FROM gdocs_users WHERE is_client = 0");
$users = $usersStmt->fetchAll();

// Fetch all documents with usernames
$docsStmt = $pdo->query("
  SELECT d.doc_id, d.title, d.updated_at, u.username
  FROM written_documents d
  JOIN gdocs_users u ON d.user_id = u.user_id
  ORDER BY d.updated_at DESC
");
$documents = $docsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - MyDocs</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

  <header class="bg-white shadow mb-6 p-4 flex justify-between items-center">
    <h1 class="text-xl font-bold text-blue-600">Admin Dashboard</h1>
    <a href="core/handleForms.php?logoutUserBtn=1" class="block text-gray-600 hover:text-blue-600">Logout</a>
  </header>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

    <!-- User Management -->
    <section class="bg-white p-6 rounded shadow">
      <h2 class="text-2xl font-semibold mb-4">Manage Users</h2>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b">
            <th class="text-left py-2">Username</th>
            <th class="text-left py-2">Email</th>
            <th class="text-left py-2">Suspended</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr class="border-b">
              <td class="py-2"><?= htmlspecialchars($user['username']) ?></td>
              <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
              <td>
                <form method="POST" class="flex items-center space-x-2">
                  <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                  <input type="checkbox" name="is_suspended" <?= $user['is_suspended'] ? 'checked' : '' ?> onchange="this.form.submit()">
                  <input type="hidden" name="suspend_toggle" value="1">
                </form>
              </td>
              <td class="text-gray-500 text-xs"><?= $user['is_suspended'] ? 'Suspended' : 'Active' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- Document Overview -->
    <section class="bg-white p-6 rounded shadow">
      <h2 class="text-2xl font-semibold mb-4">All User Documents</h2>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b">
            <th class="text-left py-2">Title</th>
            <th class="text-left py-2">User</th>
            <th class="text-left py-2">Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $doc): ?>
            <tr class="border-b">
              <td class="py-2"><?= htmlspecialchars($doc['title']) ?></td>
              <td><?= htmlspecialchars($doc['username']) ?></td>
              <td><?= htmlspecialchars($doc['updated_at']) ?></td>
              <td><a href="view.php?doc_id=<?= $doc['doc_id'] ?>" class="text-blue-500 text-sm hover:underline">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

  </div>

</body>
</html>
