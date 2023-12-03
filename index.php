<?php

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($request) {
    case '/':
    case '':
        require 'main.php';
        break;
    case '/register.php':
        require 'register.php';
        break;
    case '/menu.php':
        require 'menu.php';
        break;
    case '/create-lineup.php':
        require 'create-lineup.php';
        break;
    case '/my_lineups.php':
        require 'my_lineups.php';
        break;
    case '/other_lineups.php':
        require 'other_lineups.php';
        break;
    case '/update_lineup.php':
        require 'update_lineup.php';
        break;
        

    // Add more cases as needed for other pages
    default:
        http_response_code(404);
        echo '404 Not Found'; // Or require a custom 404.php page
        break;
}
?>