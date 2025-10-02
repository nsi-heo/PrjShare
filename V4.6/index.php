<?php
// index.php - V4.6 avec validation email unique
session_start();

if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/database.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();

if(!$db) {
    die("Erreur de connexion à la base de données. Veuillez vérifier votre configuration et lancer install.php");
}

$user = new User($db);
$error = '';
$message = '';

if($_POST) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $userData = $user->login($username, $password);
        if($userData) {
			
	
			
			
			
			// Logger pour debug
    /*        $logFile = __DIR__ . '/debug_login.log';
            $logMessage = date('Y-m-d H:i:s') . " LOGIN\n";
            $logMessage .= "User: " . $userData['username'] . "\n";
            $logMessage .= "must_change_password: " . ($userData['must_change_password'] ?? 'NOT SET') . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
      */      	
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['status'] = $userData['status'];
			// CRITIQUE : Vérifier le flag must_change_password
            if(isset($userData['must_change_password']) && $userData['must_change_password'] == 1) {
                $_SESSION['must_change_password'] = true;
				
                //file_put_contents($logFile, "REDIRECTION vers change_password.php\n", FILE_APPEND);
                header('Location: change_password.php?reason=temporary');
                exit;
            }
            
           // file_put_contents($logFile, "REDIRECTION vers dashboard.php\n", FILE_APPEND);													
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Identifiants incorrects";
        }
    }
    
    // MODIFIÉ V4.6 : Gestion des erreurs d'inscription avec email unique
    if($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if(empty($username) || empty($email) || empty($password)) {
            $error = "Tous les champs sont requis";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "L'adresse email n'est pas valide";
        } else {
            $result = $user->register($username, $email, $password);
            // CORRECTION : Vérifier si $result est un tableau
            if(is_array($result)) {
                if($result['status'] === 'success') {
                    $message = "Compte créé avec succès. Vous pouvez vous connecter.";
                } else {
                    $error = $result['message'];
                }
            } else {
                // Ancienne version qui retourne un booléen
                if($result === true) {
                    $message = "Compte créé avec succès. Vous pouvez vous connecter.";
                } else {
                    $error = "Erreur lors de la création du compte (nom d'utilisateur ou email peut-être déjà pris)";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shareman V4.6 - Partage de dépenses</title>
    <style>
        /* ... styles existants ... */
		        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container { 
            background: white; 
            padding: 2rem; 
            border-radius: 15px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 90%;
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 2rem; 
        }
        
        .header h1 { 
            color: #333; 
            font-size: 2.5rem; 
            margin-bottom: 0.5rem; 
        }
        
        .header p { 
            color: #666; 
        }
        
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #333; 
            font-weight: 500; 
        }
        
        .form-group input { 
            width: 100%; 
            padding: 0.75rem; 
            border: 2px solid #e1e5e9; 
            border-radius: 8px; 
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus { 
            outline: none; 
            border-color: #667eea; 
        }
        
        .btn { 
            width: 100%; 
            padding: 0.75rem; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 1rem; 
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
        }
        
        .switch-form { 
            text-align: center; 
            margin-top: 1.5rem; 
            color: #666; 
        }
        
        .switch-form a { 
            color: #667eea; 
            text-decoration: none; 
        }
        
        .switch-form a:hover {
            text-decoration: underline;
        }
        
        .error { 
            background: #fee; 
            color: #c33; 
            padding: 0.75rem; 
            border-radius: 8px; 
            margin-bottom: 1rem; 
            border: 1px solid #fcc;
        }
        
        .success { 
            background: #efe; 
            color: #3c3; 
            padding: 0.75rem; 
            border-radius: 8px; 
            margin-bottom: 1rem; 
            border: 1px solid #cfc;
        }
        
        .form-container {
            transition: opacity 0.3s ease-in-out;
        }
        
        .form-container.hidden {
            display: none;
        }
		
		
		
		
		
        
        .form-group small.helper {
            display: block;
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.85rem;
        }
        
        .form-group input.error {
            border-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Shareman V4.6</h1>
            <p>Partagez vos dépenses facilement</p>
        </div>
        
        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if($message): ?>
            <div class="success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- Formulaire de connexion -->
        <div id="login-form" class="form-container">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Connexion</h2>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Nom d'utilisateur:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">Se connecter</button>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="forgot_password.php" style="color: #667eea; font-size: 0.9rem; text-decoration: none;">
                        Mot de passe oublié ?
                    </a>
                </div>
            </form>
            <div class="switch-form">
                Pas de compte? <a href="#" onclick="showRegisterForm()">S'inscrire</a>
            </div>
        </div>
        
        <!-- Formulaire d'inscription -->
        <div id="register-form" class="form-container hidden">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Inscription</h2>
            <form method="POST">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Nom d'utilisateur:</label>
                    <input type="text" name="username" required minlength="3" maxlength="50" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <small class="helper">3 à 50 caractères, doit être unique</small>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <small class="helper">Votre adresse email doit être unique</small>
                </div>
                <div class="form-group">
                    <label>Mot de passe:</label>
                    <input type="password" name="password" required minlength="6">
                    <small class="helper">Minimum 6 caractères</small>
                </div>
                <button type="submit" class="btn">S'inscrire</button>
            </form>
            <div class="switch-form">
                Déjà un compte? <a href="#" onclick="showLoginForm()">Se connecter</a>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee;">
            <small style="color: #666;">
                Première installation? <a href="install.php" style="color: #667eea;">Cliquez ici</a>
            </small>
        </div>
    </div>
    
    <script>
        function showRegisterForm() {
            document.getElementById('login-form').classList.add('hidden');
            document.getElementById('register-form').classList.remove('hidden');
        }
        
        function showLoginForm() {
            document.getElementById('register-form').classList.add('hidden');
            document.getElementById('login-form').classList.remove('hidden');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"]');
            if(firstInput) {
                firstInput.focus();
            }
            
            <?php if($error && $_POST['action'] === 'register'): ?>
                // Afficher le formulaire d'inscription si erreur d'inscription
                showRegisterForm();
            <?php endif; ?>
        });
    </script>
</body>
</html>