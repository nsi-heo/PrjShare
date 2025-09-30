
<?php
// auth.php - Gestion de l'authentification
session_start();
require_once 'config/database.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if($_POST) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'login') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $userData = $user->login($username, $password);
        if($userData) {
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['status'] = $userData['status'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Identifiants incorrects";
        }
    }
    
    if($action === 'register') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        if($user->register($username, $email, $password)) {
            $message = "Compte créé avec succès. Vous pouvez vous connecter.";
        } else {
            $error = "Erreur lors de la création du compte";
        }
    }
    
    if($action === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserStatus() {
    return $_SESSION['status'] ?? 'visiteur';
}

function isAdmin() {
    return getUserStatus() === 'administrateur';
}

function requireAuth() {
    if(!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    if(!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}
?>

