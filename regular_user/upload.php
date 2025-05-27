<?php
session_start();
require_once 'core/dbConfig.php';
require_once 'core/models.php';

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
	$user_id = $_SESSION['user_id'];
	$file = $_FILES['document'];
	$uploadDir = 'uploads/';

	if (!is_dir($uploadDir)) {
		mkdir($uploadDir, 0755, true);
	}

	if ($file['error'] === UPLOAD_ERR_OK) {
		$originalName = basename($file['name']);
		$filename = time() . "_" . preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $originalName);
		$targetPath = $uploadDir . $filename;

		if (move_uploaded_file($file['tmp_name'], $targetPath)) {
			if (insertDocument($pdo, $user_id, $originalName, $targetPath)) {
				$_SESSION['upload_message'] = "✅ File uploaded successfully!";
			} else {
				$_SESSION['upload_message'] = "❌ Database error while saving file.";
			}
		} else {
			$_SESSION['upload_message'] = "❌ Failed to move uploaded file.";
		}
	} else {
		$_SESSION['upload_message'] = "❌ Upload error code: " . $file['error'];
	}
} else {
	$_SESSION['upload_message'] = "❌ Invalid upload request.";
}

header("Location: index.php");
exit;
