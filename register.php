<?php
include('connect.php'); // Ensure this file exists and contains the PDO connection setup

$message = ''; // Message to display to the user

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    // Check if the username already exists
    $stmt = $pdo->prepare("SELECT * FROM User WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user) {
        $message = "Username already exists.";
    } else {
        try {
            $sql = "INSERT INTO User (username, password) VALUES (:username, :password)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['username' => $username, 'password' => $password]);
            $message = "New user created successfully.";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">   
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">  
  <title>Create Account - Dream Hoops</title> 
  <link href="activity-styles.css" rel="stylesheet">  
</head>
<body>
  
  <div>  
    <h1>Create a Dream Hoops Account</h1>
    <!-- Display a message -->
    <?php if ($message != ''): ?>
        <p><?= $message ?></p>
    <?php endif; ?>

    <form action="register.php" method="post">     
      Username: <input type="text" name="username" required /> <br/>
      Password: <input type="password" name="password" required /> <br/>
      <input type="submit" value="Register" class="btn" />
    </form>
    <!-- Link to the login page -->
    <p>Already have an account? <a href="login.php">Log in here</a>.</p>
  </div>
  
</body>
</html>
