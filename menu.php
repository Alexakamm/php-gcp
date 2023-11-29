<?php
include('header.html');

include('connect.php');
session_start();

if (!isset($_SESSION['username'])) {
    header("location: login.php"); // Redirect to login if not logged in
    exit();
}

$username = $_SESSION['username'];
?>



    <h1>Welcome, <?php echo $username; ?>!</h1>
    <p>Select an option below:</p>
    <ul>
        <li><a href="create-lineup.php">View Lineups</a></li>
    </ul>
</body>
</html>
