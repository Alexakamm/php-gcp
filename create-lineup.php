<?php
include('connect.php');

session_start();

$lineupMessage = '';
$playerMessage = '';
$username = $_SESSION['username'] ?? null;
$deleteCommentMessage = '';

if (!$username){
    header("location: login.php");
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

function fetchPlayersStatisticsSorted($pdo, $orderBy) {
    $stmt = $pdo->prepare("SELECT Player.*, Statistics.* FROM Player INNER JOIN Statistics ON Player.player_id = Statistics.player_id ORDER BY $orderBy");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchPlayersByName($pdo, $search) {
    $stmt = $pdo->prepare("SELECT Player.*, Statistics.* FROM Player INNER JOIN Statistics ON Player.player_id = Statistics.player_id WHERE Player.name LIKE :search");
    $stmt->execute(['search' => '%' . $search . '%']);
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

$search = $_GET['search'] ?? null;
$filter = $_GET['filter'] ?? null;

switch ($filter) {
    case 'ppg':
        $orderBy = 'ppg DESC';
        break;
    case 'rpg':
        $orderBy = 'rpg DESC';
        break;
    case 'apg':
        $orderBy = 'apg DESC';
        break;
    case 'bpg':
        $orderBy = 'bpg DESC';
        break;
    case 'spg':
        $orderBy = 'spg DESC';
        break;
    default:
        $orderBy = 'player_id';
        break;
}

if ($search) {
    $players = searchPlayersByName($pdo, $search);
} elseif ($filter) {
    $players = fetchPlayersStatisticsSorted($pdo, $orderBy);
} else {
    $players = fetchPlayers($pdo);
}

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

    if (isset($_POST['upload_json'])) {
        // Check if a file was uploaded successfully
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] == UPLOAD_ERR_OK) {
            $file_name = $_FILES['json_file']['name'];
            $file_tmp = $_FILES['json_file']['tmp_name'];

            // Read the JSON file content
            $json_content = file_get_contents($file_tmp);
            $json_data = json_decode($json_content, true);

            if ($json_data === null) {
                $lineupMessage = "Error decoding JSON file. Please make sure the file contains valid JSON.";
            } else {
                // Insert into Lineup table
                try {
                    // Begin Transaction
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO Lineup (name, date_created) VALUES (:name, NOW())");
                    $stmt->execute(['name' => $json_data['lineup_name']]);
                    $lineup_id = $pdo->lastInsertId(); // Get the last insert ID to use in Creates table

                    $stmt = $pdo->prepare("INSERT INTO Creates (username, lineup_id) VALUES (:username, :lineup_id)");
                    $stmt->execute(['username' => $username, 'lineup_id' => $lineup_id]);

                    // Insert players from JSON data into Included_in table
                    foreach ($json_data['selected_players'] as $player_id) {
                        $stmt = $pdo->prepare("INSERT INTO Included_in (player_id, lineup_id) VALUES (:player_id, :lineup_id)");
                        $stmt->execute(['player_id' => $player_id, 'lineup_id' => $lineup_id]);
                    }

                    // Commit Transaction
                    $pdo->commit();
                    $lineupMessage = "Lineup uploaded successfully.";
                    $userLineups = fetchUserLineups($pdo, $username); // Fetch lineups again after insertion

                } catch (PDOException $e) {
                    // Rollback if there is an error
                    $pdo->rollBack();
                    $lineupMessage = "Error uploading lineup: " . $e->getMessage();
                }
            }
        } else {
            $lineupMessage = "Error uploading file. Please try again.";
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<body>
    <div class="container mt-4">
        <h1>Create a New Lineup</h1>
        <?php if ($lineupMessage) { echo "<p>$lineupMessage</p>"; } ?>
        <form action="create-lineup.php" method="post" style="width: 80%;">
            <div class="form-group">
                <label for="lineup_name">Lineup Name:</label>
                <input type="text" name="lineup_name" id="lineup_name" class="form-control" required>
            </div>
            <div class="form-group">
                <input type="submit" value="Create Lineup" class="btn btn-primary">
            </div>
        </form>

        <h1>Players with Statistics</h1>

        <div style="max-height: 500px; overflow-y: scroll; width: 80%;">
        <form action="create-lineup.php" method="GET">
            <input type="text" name="search" placeholder="Search by player name">
            <button type="submit">Search</button>
        </form>
        <div class="filter-buttons mb-3 text-center">
        <a href="create-lineup.php?filter=ppg" class="btn btn-info btn-sm">Filter by PPG</a>
        <a href="create-lineup.php?filter=rpg" class="btn btn-info btn-sm">Filter by RPG</a>
        <a href="create-lineup.php?filter=apg" class="btn btn-info btn-sm">Filter by APG</a>
        <a href="create-lineup.php?filter=bpg" class="btn btn-info btn-sm">Filter by BPG</a>
        <a href="create-lineup.php?filter=spg" class="btn btn-info btn-sm">Filter by SPG</a>
        </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Points Per Game PPG</th>
                        <th>Rebounds Per Game RPG</th>
                        <th>Assists Per Game APG</th>
                        <th>Blocks Per Game BPG</th>
                        <th>Steals Per Game SPG</th> 
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player) { ?>
                        <tr>
                            <td><?= htmlspecialchars($player['name']) ?></td>
                            <td><?= number_format($player['ppg'], 1) ?></td>
                            <td><?= number_format($player['rpg'], 1) ?></td>
                            <td><?= number_format($player['apg'], 1) ?></td>
                            <td><?= number_format($player['bpg'], 1) ?></td>
                            <td><?= number_format($player['spg'], 1) ?></td>
                            <!-- Add other statistics data here -->
                            <td>
                                <form action="create-lineup.php" method="post">
                                    <input type="hidden" name="player_id" value="<?= $player['player_id'] ?>">
                                    <select name="lineup_id" required>
                                        <?php foreach ($userLineups as $lineup) { ?>
                                            <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
                                        <?php } ?>
                                    </select>
                                    <input type="submit" value="Add to Lineup" class="btn btn-success">
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Remove Player from Lineup Section
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
    
    <h1>Delete a Lineup</h1>
    <form action="create-lineup.php" method="post">
        Select Lineup to Delete: <select name="delete_lineup_id" required>
            <?php foreach ($userLineups as $lineup) { ?>
                <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
            <?php } ?>
        </select>
        <input type="submit" value="Delete Lineup">
    </form> -->

    

   
    <!-- <h1>Your Lineups and Players</h1>
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

    <?php } ?> -->

    <!-- Lineup Like/Unlike Section
    <h1>Like or Unlike a Lineup</h1>
        <?php if (!empty($likeMessage)) { echo "<p>$likeMessage</p>"; } ?>
        <?php if (!empty($unlikeMessage)) { echo "<p>$unlikeMessage</p>"; } ?>
        <form action="create-lineup.php" method="post">
            Select Lineup: <select name="lineup_id" required>
                <?php foreach ($otherUsersLineups as $lineup) { ?>
                    <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
                <?php } ?>
            </select>
            <input type="submit" name="like" value="Like Lineup">
            <input type="submit" name="unlike" value="Unlike Lineup">
        </form>


   
    <h1>Add a Comment to a Lineup</h1>
    <?php if (!empty($commentMessage)) { echo "<p>$commentMessage</p>"; } ?>
    <form action="create-lineup.php" method="post">
        <label for="comment_lineup">Select Lineup:</label>
        <select name="comment_lineup_id" id="comment_lineup" required>
            <?php foreach ($otherUsersLineups as $lineup) { ?>
                <option value="<?= $lineup['lineup_id'] ?>"><?= htmlspecialchars($lineup['name']) ?></option>
            <?php } ?>
        </select>
        <label for="comment_text">Comment:</label>
        <textarea name="comment_text" id="comment_text" required maxlength="500"></textarea>
        <input type="submit" value="Add Comment">
    </form>
  
    
<h1>Delete a Comment</h1>
<?php
$userComments = fetchUserComments($pdo, $username); // Fetch comments to display in the dropdown
if (!empty($deleteCommentMessage)) { echo "<p>$deleteCommentMessage</p>"; }
?>
<form action="create-lineup.php" method="post">
    Select Comment to Delete: <select name="delete_comment_id" required>
        <?php foreach ($userComments as $comment) { ?>
            <option value="<?= $comment['comment_id'] ?>"><?= htmlspecialchars($comment['text']) ?></option>
        <?php } ?>
    </select>
    <input type="submit" name="delete_comment" value="Delete Comment">
</form> -->

   
<div class="container mt-4">
<h1>Upload a Lineup (JSON)</h1>
    <p>Upload a JSON file containing the lineup information. The format should be as follows:</p>
    <pre>
{
  "lineup_name": "Your Lineup Name",
  "selected_players": [1, 2, 3, 4, 5]
}
    </pre>
    <p>Please ensure that the file follows this structure, where "lineup_name" is the name of your lineup and "selected_players" is an array of player IDs.</p>

    <?php if ($lineupMessage) {
        echo "<p>$lineupMessage</p>";
    } ?>
        <form action="create-lineup.php" method="post" enctype="multipart/form-data" style="width: 80%;">
            <div class="form-group">
                <label for="json_file">Select JSON File:</label>
                <input type="file" name="json_file" id="json_file" class="form-control-file" required accept=".json">
            </div>
            <div class="form-group">
                <input type="hidden" name="upload_json" value="true">
                <input type="submit" value="Upload Lineup" class="btn btn-primary">
            </div>
        </form>

        <!-- ... (existing HTML code) -->
    </div>
</body>
</html>
