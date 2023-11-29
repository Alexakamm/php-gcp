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
<!DOCTYPE html>
<html lang="en">

<header>
  <nav class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="/menu.php">Dream Hoops</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar" aria-controls="collapsibleNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="collapsibleNavbar">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
            <a class="nav-link" href="/menu.php">Menu</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/create-lineup.php">Create Lineup</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/view_lineups.php">View Lineups</a>
          </li>
          <!-- Add more navigation items as needed -->
        </ul>
      </div>
    </div>
  </nav>
</header>

<!-- Add these lines to include Bootstrap CSS and JS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

<head>
    <meta charset="UTF-8">
    <title>Welcome <?php echo $username; ?></title>
    <!-- Link your stylesheet -->
    <link rel="stylesheet" href="activity-styles.css">
</head>
<body>
    <h1>Welcome, <?php echo $username; ?>!</h1>
    <p>Select an option below:</p>
    <ul>
        <li><a href="create-lineup.php">View Lineups</a></li>
    </ul>
</body>
</html>
