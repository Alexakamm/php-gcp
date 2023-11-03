<?php
include('connect.php');

session_start();

$lineupMessage = '';
$playerMessage = '';
$username = $_SESSION['username'] ?? null;

if (!$username){
    header("location: login.php");
    exit();
}
// Function to delete a lineup
function deleteLineup($pdo, $lineup_id, $username) {
    // Begin Transaction
    $pdo->beginTransaction();
    try {
        // Delete related records from Included_in table
        $stmt = $pdo->prepare("DELETE FROM Included_in WHERE lineup_id = :lineup_id");
        $stmt->execute(['lineup_id' => $lineup_id]);

        // Delete related records from Creates table
        $stmt = $pdo->prepare("DELETE FROM Creates WHERE lineup_id = :lineup_id AND username = :username");
        $stmt->execute(['lineup_id' => $lineup_id, 'username' => $username]);

        // Delete the lineup from Lineup table
        $stmt = $pdo->prepare("DELETE FROM Lineup WHERE lineup_id = :lineup_id");
        $stmt->execute(['lineup_id' => $lineup_id]);

        // Commit Transaction
        $pdo->commit();
        return "Lineup deleted successfully.";
    } catch (PDOException $e) {
        // Rollback if there is an error
        $pdo->rollBack();
        return "Error deleting lineup: " . $e->getMessage();
    }
}
// Function to fetch all players
function fetchPlayers($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM Player");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Function to fetch players in a specific lineup
function fetchPlayersInLineup($pdo, $lineup_id) {
    $stmt = $pdo->prepare("SELECT p.* FROM Player p INNER JOIN Included_in i ON p.player_id = i.player_id WHERE i.lineup_id = :lineup_id");
    $stmt->execute(['lineup_id' => $lineup_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function removePlayerFromLineup($pdo, $player_id, $lineup_id) {
    $stmt = $pdo->prepare("DELETE FROM Included_in WHERE player_id = :player_id AND lineup_id = :lineup_id");
    $stmt->execute(['player_id' => $player_id, 'lineup_id' => $lineup_id]);
}

function countPlayersInLineup($pdo, $lineup_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Included_in WHERE lineup_id = :lineup_id");
    $stmt->execute(['lineup_id' => $lineup_id]);
    return $stmt->fetchColumn();
}

function isPlayerInLineup($pdo, $player_id, $lineup_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Included_in WHERE player_id = :player_id AND lineup_id = :lineup_id");
    $stmt->execute(['player_id' => $player_id, 'lineup_id' => $lineup_id]);
    return $stmt->fetchColumn() > 0;
}


// Function to fetch user's lineups
function fetchUserLineups($pdo, $username) {
    $stmt = $pdo->prepare("SELECT * FROM Lineup WHERE lineup_id IN (SELECT lineup_id FROM Creates WHERE username = :username)");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$players = fetchPlayers($pdo);
$userLineups = fetchUserLineups($pdo, $username);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['lineup_name'])) {
        $lineup_name = $_POST['lineup_name'];
        
        // Insert into Lineup table
        try {
            // Begin Transaction
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO Lineup (name, date_created) VALUES (:name, NOW())");
            $stmt->execute(['name' => $lineup_name]);
            $lineup_id = $pdo->lastInsertId(); // Get the last insert ID to use in Creates table

            $stmt = $pdo->prepare("INSERT INTO Creates (username, lineup_id) VALUES (:username, :lineup_id)");
            $stmt->execute(['username' => $username, 'lineup_id' => $lineup_id]);

            // Commit Transaction
            $pdo->commit();
            $lineupMessage = "New lineup created successfully.";
            $userLineups = fetchUserLineups($pdo, $username); // Fetch lineups again after insertion

        } catch (PDOException $e) {
            // Rollback if there is an error
            $pdo->rollBack();
            $lineupMessage = "Error creating lineup: " . $e->getMessage();
        }
    } elseif (isset($_POST['player_id'], $_POST['lineup_id'])) {
        // Code to add a player to the lineup
        $player_id = $_POST['player_id'];
        $lineup_id = $_POST['lineup_id'];
    
        // Check if lineup already has 5 players
        if (countPlayersInLineup($pdo, $lineup_id) >= 5) {
            $playerMessage = "This lineup already has the maximum number of players (5).";
        } elseif (isPlayerInLineup($pdo, $player_id, $lineup_id)) {
            // Check if the player is already in the lineup
            $playerMessage = "This player is already in the lineup.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO Included_in (player_id, lineup_id) VALUES (:player_id, :lineup_id)");
                $stmt->execute(['player_id' => $player_id, 'lineup_id' => $lineup_id]);
                $playerMessage = "Player added successfully to the lineup.";
            } catch (PDOException $e) {
                $playerMessage = "Error adding player: " . $e->getMessage();
            }
        }
    }
    else if(isset($_POST['remove_player_id'], $_POST['remove_lineup_id'])) {
        $remove_message = removePlayerFromLineup($pdo, $_POST['remove_player_id'], $_POST['remove_lineup_id']);
        
    }
    else if (isset($_POST['remove_player_id'], $_POST['remove_lineup_id'])) {
        $player_id = $_POST['remove_player_id'];
        $lineup_id = $_POST['remove_lineup_id'];
    
        try {
            $stmt = $pdo->prepare("DELETE FROM Included_in WHERE player_id = :player_id AND lineup_id = :lineup_id");
            $stmt->execute(['player_id' => $player_id, 'lineup_id' => $lineup_id]);
            
            if ($stmt->rowCount() > 0) {
                $playerMessage = "Player removed successfully from the lineup.";
            } else {
                $playerMessage = "Player not found in the lineup or already removed.";
            }
        } catch (PDOException $e) {
            $playerMessage = "Error removing player: " . $e->getMessage();
        }
    }
    
    
    // If deleting a lineup
    else if (isset($_POST['delete_lineup_id'])) {
        $lineup_id = $_POST['delete_lineup_id'];
    
        try {
            // Start a transaction
            $pdo->beginTransaction();
    
            // Remove players from lineup first due to foreign key constraints
            $stmt = $pdo->prepare("DELETE FROM Included_in WHERE lineup_id = :lineup_id");
            $stmt->execute(['lineup_id' => $lineup_id]);
            
            // Delete the lineup
            $stmt = $pdo->prepare("DELETE FROM Lineup WHERE lineup_id = :lineup_id");
            $stmt->execute(['lineup_id' => $lineup_id]);
    
            // Commit transaction
            $pdo->commit();
    
            if ($stmt->rowCount() > 0) {
                $lineupMessage = "Lineup deleted successfully.";
            } else {
                $lineupMessage = "Lineup not found or already deleted.";
            }
        } catch (PDOException $e) {
            // Roll back the transaction if something failed
            $pdo->rollBack();
            $lineupMessage = "Error deleting lineup: " . $e->getMessage();
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Lineup</title>
</head>
<body>
    <h1>Create a New Lineup</h1>
    <?php if ($lineupMessage) { echo "<p>$lineupMessage</p>"; } ?>
    <form action="create-lineup.php" method="post">
        Lineup Name: <input type="text" name="lineup_name" required>
        <input type="submit" value="Create Lineup">
    </form>

      <!-- Add Player to Lineup Section -->
      <h1>Add a Player to a Lineup</h1>
    <?php if ($playerMessage) { echo "<p>$playerMessage</p>"; } ?>
    <form action="create-lineup.php" method="post">
        Player: <select name="player_id" required>
            <?php foreach ($players as $player) { ?>
                <option value="<?= $player['player_id'] ?>"><?= htmlspecialchars($player['name']) ?></option>
            <?php } ?>
        </select>
        Lineup: <select name="lineup_id" required>
            <?php foreach ($userLineups as $lineup) { ?>
                <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
            <?php } ?>
        </select>
        <input type="submit" value="Add Player">
    </form>
    <!-- Remove Player from Lineup Section -->
    <h1>Remove a Player from a Lineup</h1>
    <form action="create-lineup.php" method="post">
        Player to Remove: <select name="remove_player_id" required>
            <?php foreach ($players as $player) { ?>
                <option value="<?= $player['player_id'] ?>"><?= htmlspecialchars($player['name']) ?></option>
            <?php } ?>
        </select>
        From Lineup: <select name="remove_lineup_id" required>
            <?php foreach ($userLineups as $lineup) { ?>
                <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
            <?php } ?>
        </select>
        <input type="submit" value="Remove Player">
    </form>
    <!-- Delete Lineup Section -->
    <h1>Delete a Lineup</h1>
    <form action="create-lineup.php" method="post">
        Select Lineup to Delete: <select name="delete_lineup_id" required>
            <?php foreach ($userLineups as $lineup) { ?>
                <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
            <?php } ?>
        </select>
        <input type="submit" value="Delete Lineup">
    </form>

    

   
    <h1>Your Lineups and Players</h1>
<?php foreach ($userLineups as $lineup) { ?>
    <h2><?= htmlspecialchars($lineup['name']) ?> Lineup</h2>
    <?php 
    $lineupPlayers = fetchPlayersInLineup($pdo, $lineup['lineup_id']);
    if (!empty($lineupPlayers)): ?>
        <ul>
            <?php foreach ($lineupPlayers as $player) { ?>
                <li><?= htmlspecialchars($player['name']) ?></li>
            <?php } ?>
        </ul>
    <?php else: ?>
        <p>No players in this lineup.</p>
    <?php endif; ?>
<?php } ?>

    

   
</body>
</html>
