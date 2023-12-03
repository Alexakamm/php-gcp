<?php

include('connect.php');
session_start();

if (!isset($_SESSION['username'])) {
    header("location: main.php"); // Redirect to login if not logged in
    exit();
}

$username = $_SESSION['username'];
include('header.html');
include('footer.html');
?>

<!DOCTYPE html>
<html lang="en">
<body>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="activity-styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <div class="container mt-4">
        <h1 class="display-4 text-center">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p class="lead text-center">Your journey to the ultimate fantasy basketball lineup starts here.</p>

        <div class="row my-4">
            <div class="col-lg-4 mb-2">
                <div class="card h-100">
                    <img src="images/create.jpeg" class="card-img-top" alt="Create Lineup">
                    <div class="card-body">
                        <h5 class="card-title">Create Your Lineup</h5>
                        <p class="card-text">Craft your dream team and see how you stack up against the competition.</p>
                        <a href="create-lineup.php" class="btn btn-primary">Create Lineup</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-2">
                <div class="card h-100">
                    <img src="images/community.jpeg" class="card-img-top" alt="Community Lineups">
                    <div class="card-body">
                        <h5 class="card-title">Explore Community Lineups</h5>
                        <p class="card-text">See what other users are creating and get inspired by their strategies.</p>
                        <a href="other_lineups.php" class="btn btn-primary">View Lineups</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body>
</html>

