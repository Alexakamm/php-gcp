<?php
ob_start();
include('connect.php'); // Ensure this file exists and contains the PDO connection setup
session_start();

// Handle the login logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $sql = "SELECT password FROM User WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, so start a new session
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            
             // Redirect user to menu page 
            header("Location: https://dreamhoops.appspot.com/menu.php");
            ob_end_flush();
            exit();
        } else {
            $login_error = "The username or password you entered was not valid.";
        }
    } catch (PDOException $e) {
        $login_error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">   
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">  
  <title>Log in to Dream Hoops</title> 
  <link href="activity-styles.css" rel="stylesheet">  
</head>
<body>
  
  <div>  
    <h1>Log in to Dream Hoops</h1>
    <!-- Display possible login error -->
    <?php if (!empty($login_error)): ?>
        <p class="error"><?= $login_error ?></p>
    <?php endif; ?>
    <form action="login.php" method="post">     
      Username: <input type="text" name="username" required /> <br/>
      Password: <input type="password" name="password" required /> <br/>
      <input type="submit" value="Log In" class="btn" />
    </form>
    <!-- Link to the registration page -->
    <p>Don't have an account? <a href="register.php">Sign up here</a>.</p>
  </div>
  
</body>
</html>
