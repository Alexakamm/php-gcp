<?php
// update_lineup.php

include('connect.php');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lineup_id']) && isset($_POST['new_name'])) {
    $lineupId = $_POST['lineup_id'];
    $newName = $_POST['new_name'];

    // Prepare and execute the update statement
    $stmt = $pdo->prepare("UPDATE Lineup SET name = ? WHERE lineup_id = ?");
    $stmt->execute([$newName, $lineupId]);

    // Redirect back to the lineups page or display a success message
    header('Location: my_lineups.php'); // Adjust the redirect as necessary
    exit();
}
?>
