
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
			
			// DEBUG TEMPORAIRE
   // Logger dans un fichier personnalisé
    $logFile = __DIR__ . '/debug_login.log';
    $logMessage = date('Y-m-d H:i:s') . " === LOGIN DEBUG ===\n";
    $logMessage .= "User ID: " . $userData['id'] . "\n";
    $logMessage .= "Username: " . $userData['username'] . "\n";
    $logMessage .= "must_change_password isset: " . (isset($userData['must_change_password']) ? 'OUI' : 'NON') . "\n";
    $logMessage .= "must_change_password value: " . ($userData['must_change_password'] ?? 'NOT SET') . "\n";
    $logMessage .= "must_change_password type: " . gettype($userData['must_change_password'] ?? null) . "\n";
    $logMessage .= "Condition result: " . (isset($userData['must_change_password']) && $userData['must_change_password'] == 1 ? 'TRUE - DOIT REDIRIGER' : 'FALSE - PAS DE REDIRECTION') . "\n";
    $logMessage .= "==================\n\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
   
			
			
			
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['status'] = $userData['status'];
			
			// NOUVEAU V4.6 : Vérifier si l'utilisateur doit changer son mot de passe
            if(isset($userData['must_change_password']) && $userData['must_change_password'] == 1) {
            $_SESSION['must_change_password'] = true;
            header('Location: change_password.php?reason=temporary');
                exit;
            }
			
			
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
	// NOUVEAU V4.6 : Rediriger vers le changement de mot de passe si nécessaire
    if(isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
        // Autoriser l'accès uniquement à change_password.php
        $currentPage = basename($_SERVER['PHP_SELF']);
        if($currentPage !== 'change_password.php') {
            header('Location: change_password.php?reason=temporary');
            exit;
        }
    }
}

function requireAdmin() {
    if(!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}
?>

