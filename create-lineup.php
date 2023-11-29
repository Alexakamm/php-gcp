<?php
include('connect.php');
include('header.html');

session_start();

$lineupMessage = '';
$playerMessage = '';
$username = $_SESSION['username'] ?? null;
$deleteCommentMessage = '';

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


    
}
?>



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

    <!-- Lineup Like/Unlike Section -->
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


    <!-- Comment Section -->
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
  
    <!-- Delete Comment Section -->
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
</form>

   
</body>
</html>