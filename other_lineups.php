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
    $stmt = $pdo->prepare("
        SELECT l.*, 
        (SELECT COUNT(*) FROM Likes WHERE lineup_id = l.lineup_id) as like_count,
        (SELECT COUNT(*) FROM Likes WHERE lineup_id = l.lineup_id AND username = :username) as liked_by_user
        FROM Lineup l 
        WHERE l.lineup_id NOT IN (SELECT lineup_id FROM Creates WHERE username = :username)
    ");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch user's comments
function fetchUserComments($pdo, $username) {
    $stmt = $pdo->prepare("SELECT Comment.comment_id, Comment.text FROM Comment INNER JOIN Makes_comment ON Comment.comment_id = Makes_comment.comment_id WHERE Makes_comment.username = :username");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


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

// Function to fetch comments on Lineups 
function fetchCommentsForLineup($pdo, $lineup_id) {
    $stmt = $pdo->prepare("
        SELECT c.text, mc.username, c.date_created 
        FROM Comment c
        INNER JOIN Makes_comment mc ON c.comment_id = mc.comment_id
        INNER JOIN Commented_on co ON c.comment_id = co.comment_id
        WHERE co.lineup_id = :lineup_id
        ORDER BY c.date_created DESC");
    $stmt->execute(['lineup_id' => $lineup_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    if (isset($_POST['add_comment'], $_POST['comment_text'], $_POST['lineup_id'])) {
        $comment_text = $_POST['comment_text'];
        $lineup_id = $_POST['lineup_id'];
        // Call your function to add a comment
        $commentMessage = addComment($pdo, $username, $lineup_id, $comment_text);
    }

}

$players = fetchPlayers($pdo);
$userLineups = fetchUserLineups($pdo, $username);
$otherUsersLineups = fetchOtherUsersLineups($pdo, $username);
?>

<!DOCTYPE html>
<html lang="en">
<body>
<!-- Lineup Like/Unlike Section -->
<!--    <h1>Like or Unlike a Lineup</h1>-->
    <?php if (!empty($likeMessage)) { echo "<p>$likeMessage</p>"; } ?>
    <?php if (!empty($unlikeMessage)) { echo "<p>$unlikeMessage</p>"; } ?>
<!--    <form action="other_lineups.php" method="post">-->
<!--        Select Lineup: <select name="lineup_id" required>-->
<!--            --><?php //foreach ($otherUsersLineups as $lineup) { ?>
<!--                <option value="--><?php //= $lineup['lineup_id'] ?><!--">--><?php //= htmlspecialchars($lineup['name']) ?><!--</option>-->
<!--            --><?php //} ?>
<!--        </select>-->
<!--        <input type="submit" name="like" value="Like Lineup">-->
<!--        <input type="submit" name="unlike" value="Unlike Lineup">-->
<!--    </form>-->

    <!-- Delete Comment Section -->
    <h1>Delete a Comment</h1>
    <?php
    $userComments = fetchUserComments($pdo, $username); // Fetch comments to display in the dropdown
    if (!empty($deleteCommentMessage)) { echo "<p>$deleteCommentMessage</p>"; }
    ?>
    <form action="other_lineups.php" method="post">
        Select Comment to Delete: <select name="delete_comment_id" required>
            <?php foreach ($userComments as $comment) { ?>
                <option value="<?= $comment['comment_id'] ?>"><?= htmlspecialchars($comment['text']) ?></option>
            <?php } ?>
        </select>
        <input type="submit" name="delete_comment" value="Delete Comment">
    </form>

    <!-- Display Other Users' Lineups -->
    <!-- Display Other Users' Lineups -->
<h1>Other Users' Lineups</h1>
<?php foreach ($otherUsersLineups as $lineup) { ?>
    <div class="lineup-header">
        <h2><?= htmlspecialchars($lineup['name']) ?></h2>
        <span><?= $lineup['like_count'] ?> likes</span>
        <?php if ($lineup['liked_by_user']): ?>
            <form action="other_lineups.php" method="post">
                <input type="hidden" name="lineup_id" value="<?= htmlspecialchars($lineup['lineup_id']) ?>">
                <button type="submit" name="unlike" class="btn btn-primary">Unlike</button>
            </form>
        <?php else: ?>
            <form action="other_lineups.php" method="post">
                <input type="hidden" name="lineup_id" value="<?= htmlspecialchars($lineup['lineup_id']) ?>">
                <button type="submit" name="like" class="btn btn-primary">Like</button>
            </form>
        <?php endif; ?>
    </div>
<!--    <div class="lineup-name-container">-->
<!--        <div class="lineup-header" style="display: flex; align-items: center; gap: 10px; justify-content: flex-start;">-->
<!--            <h2 style="margin-bottom: 0;">--><?php //= htmlspecialchars($lineup['name']) ?><!--</h2>-->
<!--            --><?php //if ($lineup['liked']): ?>
<!--                <form action="other_lineups.php" method="post" style="margin-bottom: 0;">-->
<!--                    <input type="hidden" name="lineup_id" value="--><?php //= htmlspecialchars($lineup['lineup_id']) ?><!--">-->
<!--                    <button type="submit" name="unlike" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Unlike</button>-->
<!--                </form>-->
<!--            --><?php //else: ?>
<!--                <form action="other_lineups.php" method="post" style="margin-bottom: 0;">-->
<!--                    <input type="hidden" name="lineup_id" value="--><?php //= htmlspecialchars($lineup['lineup_id']) ?><!--">-->
<!--                    <button type="submit" name="like" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Like</button>-->
<!--                </form>-->
<!--            --><?php //endif; ?>
<!--        </div>-->
<!--    </div>-->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Player Name</th>
                <th>Points per Game</th>
                <th>Rebounds per Game</th>
                <th>Assists per Game</th>
                <th>Steals per Game</th>
                <th>Blocks per Game</th>
                <th>Field Goal %</th>
                <!-- Add other headers if needed -->
            </tr>
        </thead>
        <tbody>
            <?php
            $playersInLineup = fetchPlayersInLineup($pdo, $lineup['lineup_id']);
            foreach ($playersInLineup as $player) {
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
                    <!-- Add other data fields if needed -->
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- Display Comments for this Lineup -->
    <?php
    $comments = fetchCommentsForLineup($pdo, $lineup['lineup_id']);
    foreach ($comments as $comment) {
        echo '<div class="comment">' . htmlspecialchars($comment['username']) . ': ' . htmlspecialchars($comment['text']) . '</div>';
    }
    ?>

    <!-- Add Comment Form -->
    <form action="" method="post" class="comment-form">
        <input type="hidden" name="lineup_id" value="<?= htmlspecialchars($lineup['lineup_id']) ?>">
        <input type="text" name="comment_text" placeholder="Enter comment" class="comment-input">
        <input type="submit" name="add_comment" value="Comment" class="comment-button">
    </form>
<?php } ?>
</body>

</html>
