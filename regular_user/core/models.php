<?php

function insertNewUser($pdo, $username, $first_name, $last_name, $password) {
	$response = array();
	$checkIfUserExists = checkIfUserExists($pdo, $username); 

	if (!$checkIfUserExists['result']) {

		$sql = "INSERT INTO gdocs_users (username, first_name, last_name, is_client, password) 
		VALUES (?,?,?,?,?)";

		$stmt = $pdo->prepare($sql);

		if ($stmt->execute([$username, $first_name, $last_name, false, $password])) {
			$response = array(
				"status" => "200",
				"message" => "User successfully inserted!"
			);
		}

		else {
			$response = array(
				"status" => "400",
				"message" => "An error occured with the query!"
			);
		}
	}

	else {
		$response = array(
			"status" => "400",
			"message" => "User already exists!"
		);
	}

	return $response;
}

function checkIfUserExists($pdo, $username) {
	$response = array();
	$sql = "SELECT * FROM gdocs_users WHERE username = ?";
	$stmt = $pdo->prepare($sql);

	if ($stmt->execute([$username])) {

		$userInfoArray = $stmt->fetch();

		if ($stmt->rowCount() > 0) {
			$response = array(
				"result"=> true,
				"status" => "200",
				"userInfoArray" => $userInfoArray
			);
		}

		else {
			$response = array(
				"result"=> false,
				"status" => "400",
				"message"=> "User doesn't exist from the database"
			);
		}
	}

	return $response;

}

function insertDocument($pdo, $user_id, $file_name, $file_path) {
	$sql = "INSERT INTO documents (user_id, file_name, file_path) VALUES (?, ?, ?)";
	$stmt = $pdo->prepare($sql);
	return $stmt->execute([$user_id, $file_name, $file_path]);
}

function getUserDocuments($pdo, $user_id) {
	$sql = "SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


