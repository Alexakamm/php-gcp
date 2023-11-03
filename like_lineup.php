<?php
session_start();
include 'connect.php';

$username = $_SESSION['username']; // Assumes you store username in session upon login.
$lineup_id = $_POST['lineup_id'];  // The ID of the lineup to be liked or unliked.

// First, get the user ID from the username.
$stmt = $pdo->prepare("SELECT username FROM User WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

// Check if the user already liked the lineup
$stmt = $pdo->prepare("SELECT lineup_id FROM likes WHERE username = ? AND lineup_id = ?");
$stmt->execute([$user['username'], $lineup_id]);
$like = $stmt->fetch();

if ($like) {
    // Unlike the lineup
    $stmt = $pdo->prepare("DELETE FROM likes WHERE username = ? AND lineup_id = ?");
    $stmt->execute([$user['username'], $lineup_id]);
    $liked = false;
} else {
    // Like the lineup
    $stmt = $pdo->prepare("INSERT INTO likes (username, lineup_id) VALUES (?, ?)");
    $stmt->execute([$user['username'], $lineup_id]);
    $liked = true;
}

echo json_encode(['success' => true, 'liked' => $liked]);
?>
