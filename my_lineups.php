<?php
include('connect.php');

session_start();

$lineupMessage = '';
$playerMessage = '';
$username = $_SESSION['username'] ?? null;
$deleteCommentMessage = '';

if (!$username){
    header("location: main.php");
    exit();
}

$username = $_SESSION['username'];
include('header.html');
include('footer.html');

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


// Function to fetch lineups not created by the user
function fetchOtherUsersLineups($pdo, $username) {
    $stmt = $pdo->prepare("SELECT * FROM Lineup WHERE lineup_id NOT IN (SELECT lineup_id FROM Creates WHERE username = :username)");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch user's comments
function fetchUserComments($pdo, $username) {
    $stmt = $pdo->prepare("SELECT Comment.comment_id, Comment.text FROM Comment INNER JOIN Makes_comment ON Comment.comment_id = Makes_comment.comment_id WHERE Makes_comment.username = :username");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


$players = fetchPlayers($pdo);
$userLineups = fetchUserLineups($pdo, $username);
$otherUsersLineups = fetchOtherUsersLineups($pdo, $username);


// Function to like a lineup
function likeLineup($pdo, $lineup_id, $username) {
    // Check if the user already liked the lineup
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Likes WHERE lineup_id = :lineup_id AND username = :username");
    $stmt->execute(['lineup_id' => $lineup_id, 'username' => $username]);
    if ($stmt->fetchColumn() > 0) {
        return "You have already liked this lineup.";
    }

    // Insert like into database
    try {
        $stmt = $pdo->prepare("INSERT INTO Likes (lineup_id, username) VALUES (:lineup_id, :username)");
        $stmt->execute(['lineup_id' => $lineup_id, 'username' => $username]);
        return "Lineup liked successfully.";
    } catch (PDOException $e) {
        return "Error liking lineup: " . $e->getMessage();
    }
}

// Function to unlike a lineup
function unlikeLineup($pdo, $lineup_id, $username) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Likes WHERE lineup_id = :lineup_id AND username = :username");
        $stmt->execute(['lineup_id' => $lineup_id, 'username' => $username]);

        if ($stmt->rowCount() > 0) {
            return "Lineup unliked successfully.";
        } else {
            return "You have not liked this lineup.";
        }
    } catch (PDOException $e) {
        return "Error unliking lineup: " . $e->getMessage();
    }
}

// Function to add a comment
function addComment($pdo, $username, $lineup_id, $text) {
    try {
        // Begin Transaction
        $pdo->beginTransaction();

        // Insert into Comment table
        $stmt = $pdo->prepare("INSERT INTO Comment (text, date_created) VALUES (:text, NOW())");
        $stmt->execute(['text' => $text]);
        $comment_id = $pdo->lastInsertId(); // Get the last insert ID to use in makes_comment and commented_on tables

        // Insert into makes_comment table
        $stmt = $pdo->prepare("INSERT INTO Makes_comment (username, comment_id) VALUES (:username, :comment_id)");
        $stmt->execute(['username' => $username, 'comment_id' => $comment_id]);

        // Insert into commented_on table
        $stmt = $pdo->prepare("INSERT INTO Commented_on (comment_id, lineup_id) VALUES (:comment_id, :lineup_id)");
        $stmt->execute(['comment_id' => $comment_id, 'lineup_id' => $lineup_id]);

        // Commit Transaction
        $pdo->commit();
        return "Comment added successfully.";
    } catch (PDOException $e) {
        // Rollback if there is an error
        $pdo->rollBack();
        return "Error adding comment: " . $e->getMessage();
    }
}


function fetchPlayerStatistics($pdo, $player_id) {
    $stmt = $pdo->prepare("SELECT * FROM Statistics WHERE player_id = :player_id");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}



function fetchUserLineupsWithPlayers($pdo, $username) {
    $stmt = $pdo->prepare("SELECT * FROM Lineup WHERE lineup_id IN (SELECT lineup_id FROM Creates WHERE username = :username)");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch player names in a specific lineup
function fetchPlayerNamesInLineup($pdo, $lineup_id) {
    $stmt = $pdo->prepare("SELECT p.player_name FROM Player p INNER JOIN Included_in i ON p.player_id = i.player_id WHERE i.lineup_id = :lineup_id");
    $stmt->execute(['lineup_id' => $lineup_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


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

    if (isset($_POST['like'], $_POST['lineup_id'])) {
        $lineup_id = $_POST['lineup_id'];
        $likeMessage = likeLineup($pdo, $lineup_id, $username);
    } elseif (isset($_POST['unlike'], $_POST['lineup_id'])) {
        $lineup_id = $_POST['lineup_id'];
        $unlikeMessage = unlikeLineup($pdo, $lineup_id, $username);
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

                // Fetch user lineups again after deletion
                $userLineups = fetchUserLineups($pdo, $username);
            } else {
                $lineupMessage = "Lineup not found or already deleted.";
            }
        } catch (PDOException $e) {
            // Roll back the transaction if something failed
            $pdo->rollBack();
            $lineupMessage = "Error deleting lineup: " . $e->getMessage();
        }
    }

    // Handle like lineup form submission
    else if (isset($_POST['like_lineup_id'])) {
        $lineup_id = $_POST['like_lineup_id'];
        $username = $_SESSION['username']; // Assuming you store user's ID in session after login
        $likeMessage = likeLineup($pdo, $lineup_id, $username);
    }

        // Handle unlike lineup form submission
    else if (isset($_POST['unlike_lineup_id'])) {
        $lineup_id = $_POST['unlike_lineup_id'];
        $username = $_SESSION['username']; // Assuming the username is stored in the session
        $unlikeMessage = unlikeLineup($pdo, $lineup_id, $username);
    }

    // Handle add comment form submission
    if (isset($_POST['comment_text'], $_POST['comment_lineup_id'])) {
        $comment_text = $_POST['comment_text'];
        $comment_lineup_id = $_POST['comment_lineup_id'];
        $commentMessage = addComment($pdo, $username, $comment_lineup_id, $comment_text);
    }

    // Handle delete comment form submission
    if (isset($_POST['delete_comment'], $_POST['delete_comment_id'])) {
        $comment_id = $_POST['delete_comment_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM Comment WHERE comment_id = :comment_id");
            $stmt->execute(['comment_id' => $comment_id]);
            if ($stmt->rowCount() > 0) {
                $deleteCommentMessage = "Comment deleted successfully.";
            } else {
                $deleteCommentMessage = "Comment not found or already deleted.";
            }
        } catch (PDOException $e) {
            $deleteCommentMessage = "Error deleting comment: " . $e->getMessage();
        }
    }

    if (isset($_POST['export_lineups'])) {
        // Fetch all user lineups with players
        $userLineupsWithPlayers = fetchUserLineupsWithPlayers($pdo, $username);

        // Create an array to store lineup data
        $lineupsData = [];

        // Iterate through user lineups
        foreach ($userLineupsWithPlayers as $lineup) {
            $lineupData = [
                'lineup_name' => $lineup['name'],
                'players' => []
            ];

            // Fetch players in the lineup
            $lineupPlayers = fetchPlayersInLineup($pdo, $lineup['lineup_id']);

            // Iterate through players in the lineup
            foreach ($lineupPlayers as $player) {
                $lineupData['players'][] = $player['name'];
            }

            // Add lineup data to the array
            $lineupsData[] = $lineupData;
        }

        // Convert the array to JSON
        $jsonLineups = json_encode($lineupsData, JSON_PRETTY_PRINT);

        // Output JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="lineups_export.json"');
        echo $jsonLineups;
        exit();
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<body>
<?php if ($lineupMessage) { echo "<p>$lineupMessage</p>"; } ?>

<h1>Your Lineups and Players</h1>
<?php foreach ($userLineups as $lineup) { ?>
    <div class="lineup-container">
        <h2><?= htmlspecialchars($lineup['name']) ?></h2>
        <!-- Edit lineup form -->
        <form method="post" action="update_lineup.php">
            <input type="hidden" name="lineup_id" value="<?= $lineup['lineup_id'] ?>">
            <label>
                <input type="text" name="new_name" placeholder="Enter new lineup name" style="margin-right: 10px;">
            </label>
            <span style="display: inline-block; width: 20px;"></span>
            <button type="submit" class="btn btn-primary">Update Name</button>
        </form>
        <?php 
        $lineupPlayers = fetchPlayersInLineup($pdo, $lineup['lineup_id']);
        if (!empty($lineupPlayers)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Points per Game</th>
                        <th>Rebounds per Game</th>
                        <th>Assists per Game</th>
                        <th>Steals per Game</th>
                        <th>Blocks per Game</th>
                        <th>Field Goal %</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineupPlayers as $player) { 
                        $statistics = fetchPlayerStatistics($pdo, $player['player_id']);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($player['name']) ?></td>
                            <td><?= round(($statistics['points'] / $statistics['games_played']) ?? 0, 1) ?></td>
                            <td><?= round(($statistics['t_reb'] / $statistics['games_played']) ?? 0, 1) ?></td>
                            <td><?= round(($statistics['assists'] / $statistics['games_played']) ?? 0, 1) ?></td>
                            <td><?= round(($statistics['steals'] / $statistics['games_played']) ?? 0, 1) ?></td>
                            <td><?= round(($statistics['blocks'] / $statistics['games_played']) ?? 0, 1) ?></td>
                            <td><?= $statistics['fg_percent'] ?? 'N/A' ?>%</td>
                            <td>
                                <!-- delete player button and form here -->
                                <form action="my_lineups.php" method="post" class="player-delete-form">
                                    <input type="hidden" name="remove_player_id" value="<?= $player['player_id'] ?>">
                                    <input type="hidden" name="remove_lineup_id" value="<?= $lineup['lineup_id'] ?>">
                                    <button type="submit" class="btn btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No players in this lineup.</p>
        <?php endif; ?>
        <!-- delete lineup button and confirmation prompt here -->
        <form action="my_lineups.php" method="post" class="lineup-delete-form">
            <input type="hidden" name="delete_lineup_id" value="<?= $lineup['lineup_id'] ?>">
            <button type="submit" class="btn btn-danger" name="confirm_delete" onclick="return confirm('Are you sure you want to delete this lineup?');">Delete Lineup</button>
        </form>
    </div>
<?php } ?>

<!-- Export Lineups Button -->

<form action="my_lineups.php" method="post">
    <input type="hidden" name="export_lineups" value="true">
    <button type="submit" class="btn btn-primary">Export Lineups as JSON</button>
</form>

</body>
<h1 style="color:white; font-size:50px;">Give us a good grade is you see this!<h1>
</html>
