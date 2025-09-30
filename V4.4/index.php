<?php
// index.php - Page de connexion/inscription
session_start();

// Rediriger si déjà connecté
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
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if(empty($username) || empty($email) || empty($password)) {
            $error = "Tous les champs sont requis";
        } else {
            if($user->register($username, $email, $password)) {
                $message = "Compte créé avec succès. Vous pouvez vous connecter.";
            } else {
                $error = "Erreur lors de la création du compte (nom d'utilisateur peut-être déjà pris)";
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
    <title>Shareman - Partage de dépenses</title>
    <style>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Shareman</h1>
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
                    <input type="text" name="username" required minlength="3" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe:</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <button type="submit" class="btn">S'inscrire</button>
            </form>
            <div class="switch-form">
                Déjà un compte? <a href="#" onclick="showLoginForm()">Se connecter</a>
            </div>
        </div>
        
        <!-- Lien d'installation -->
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
        
        // Auto-focus sur le premier champ
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"]');
            if(firstInput) {
                firstInput.focus();
            }
        });
        
        // Gestion des erreurs de connexion à la base de données
        <?php if(!$db): ?>
            alert('Erreur de connexion à la base de données. Veuillez vérifier votre configuration et lancer install.php');
        <?php endif; ?>
    </script>
</body>
</html>