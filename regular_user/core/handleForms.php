<?php  
require_once 'dbConfig.php';
require_once 'models.php';

if (isset($_POST['insertNewUserBtn'])) {
	$username = trim($_POST['username']);
	$first_name = trim($_POST['first_name']);
	$last_name = trim($_POST['last_name']);
	$password = trim($_POST['password']);
	$confirm_password = trim($_POST['confirm_password']);

	if (!empty($username) && !empty($first_name) && !empty($last_name) && !empty($password) && !empty($confirm_password)) {

		if ($password == $confirm_password) {

			$insertQuery = insertNewUser($pdo, $username, $first_name, $last_name, password_hash($password, PASSWORD_DEFAULT));
			$_SESSION['message'] = $insertQuery['message'];

			if ($insertQuery['status'] == '200') {
				$_SESSION['message'] = $insertQuery['message'];
				$_SESSION['status'] = $insertQuery['status'];
				header("Location: ../login.php");
			}

			else {
				$_SESSION['message'] = $insertQuery['message'];
				$_SESSION['status'] = $insertQuery['status'];
				header("Location: ../register.php");
			}

		}
		else {
			$_SESSION['message'] = "Please make sure both passwords are equal";
			$_SESSION['status'] = '400';
			header("Location: ../register.php");
		}

	}

	else {
		$_SESSION['message'] = "Please make sure there are no empty input fields";
		$_SESSION['status'] = '400';
		header("Location: ../register.php");
	}
}

if (isset($_POST['loginUserBtn'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Fetch user
    $stmt = $pdo->prepare("SELECT * FROM gdocs_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        // ✅ Check for suspension
        if ($user['is_suspended'] == 1) {
            $_SESSION['message'] = "Your account is suspended. Please contact the administrator.";
            $_SESSION['status'] = "403";
            header("Location: ../login.php");
            exit;
        }

        // ✅ Log user in
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_client'] = $user['is_admin'] ?? 0; // Adjust as needed

        // Redirect based on user type
        if ($_SESSION['is_client'] == 1) {
            header("Location: ../admin_user/index.php");
        } else {
            header("Location: ../index.php");
        }
        exit;

    } else {
        $_SESSION['message'] = "Invalid username or password.";
        $_SESSION['status'] = "401";
        header("Location: ../login.php");
        exit;
    }
}
if (isset($_GET['logoutUserBtn'])) {
	unset($_SESSION['username']);
	header("Location: ../login.php");
}

// --- AJAX user search ---
if (isset($_GET['action']) && $_GET['action'] === 'search_users') {
    $q = $_GET['q'] ?? '';
    $q = "%$q%";

    $stmt = $pdo->prepare("SELECT user_id, username, first_name, last_name FROM gdocs_users WHERE username LIKE ? AND is_client = 0 LIMIT 10");
    $stmt->execute([$q]);

    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- Handle share document form submit ---
if (isset($_POST['shareDocumentBtn'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }

    $docId = $_POST['doc_id'] ?? null;
    $targetUserId = $_POST['user_id'] ?? null;

    if ($docId && $targetUserId) {
        // Check if already shared
        $check = $pdo->prepare("SELECT * FROM document_shared_users WHERE doc_id = ? AND user_id = ?");
        $check->execute([$docId, $targetUserId]);

        if ($check->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO document_shared_users (doc_id, user_id) VALUES (?, ?)");
            $stmt->execute([$docId, $targetUserId]);
        }
    }

    header("Location: ../index.php");
    exit;

}

if (isset($_POST['updateDocumentBtn'])) {
    session_start();
    require_once 'dbConfig.php';

    $userId = $_SESSION['user_id'] ?? 0;
    $docId = intval($_POST['doc_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Check if user owns or has edit access
    $stmt = $pdo->prepare("
        SELECT d.title AS old_title, d.content AS old_content
        FROM written_documents d
        LEFT JOIN document_shared_users s ON d.doc_id = s.doc_id AND s.user_id = :uid
        WHERE d.doc_id = :doc_id
          AND (
            d.user_id = :uid
            OR s.can_edit = 1
          )
    ");
    $stmt->execute(['doc_id' => $docId, 'uid' => $userId]);
    $doc = $stmt->fetch();

    if ($doc && $title !== '' && $content !== '') {
        $changes = [];
        if ($title !== $doc['old_title']) $changes[] = "updated the title";
        if ($content !== $doc['old_content']) $changes[] = "edited the content";

        $updateStmt = $pdo->prepare("
            UPDATE written_documents
            SET title = ?, content = ?, updated_at = NOW()
            WHERE doc_id = ?
        ");
        $updateStmt->execute([$title, $content, $docId]);

        if (!empty($changes)) {
            $action = implode(' and ', $changes);
            $logStmt = $pdo->prepare("
                INSERT INTO document_activity_logs (doc_id, user_id, action)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([$docId, $userId, $action]);
        }

        header("Location: ../view.php?doc_id=$docId");
        exit;
    } else {
        $_SESSION['message'] = "Unauthorized or invalid input.";
        $_SESSION['status'] = "403";
        header("Location: ../index.php");
        exit;
    }
}

// Add new comment
if (isset($_POST['addComment'])) {
  $docId = intval($_POST['doc_id']);
  $userId = $_SESSION['user_id'];
  $content = trim($_POST['content']);

  if (!empty($content)) {
    $stmt = $pdo->prepare("INSERT INTO document_comments (doc_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$docId, $userId, $content]);
  }

  header("Location: ../view.php?doc_id=" . $docId);
  exit;
}

// Add reply to a comment
if (isset($_POST['addReply'])) {
  $docId = intval($_POST['doc_id']);
  $parentId = intval($_POST['parent_comment_id']);
  $userId = $_SESSION['user_id'];
  $content = trim($_POST['content']);

  if (!empty($content)) {
    $stmt = $pdo->prepare("INSERT INTO document_comments (doc_id, user_id, parent_comment_id, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$docId, $userId, $parentId, $content]);
  }

  header("Location: ../view.php?doc_id=" . $docId);
  exit;
}




