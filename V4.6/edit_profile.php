<?php
// edit_profile.php - V4.6 Modification du profil utilisateur
require_once 'auth.php';
requireAuth();
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$userManager = new User($db);

$currentUser = $userManager->getUserById($_SESSION['user_id']);
$error = '';
$success = '';

if($_POST) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'update_email') {
        $newEmail = trim($_POST['email'] ?? '');
        
        if(empty($newEmail)) {
            $error = "L'adresse email est requise";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "L'adresse email n'est pas valide";
        } else {
            $result = $userManager->updateUserEmail($_SESSION['user_id'], $newEmail);
            
            if($result['status'] === 'success') {
                $success = $result['message'];
                $currentUser = $userManager->getUserById($_SESSION['user_id']);
            } else {
                $error = $result['message'];
            }
        }
    }
    
    if($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if(empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "Tous les champs de mot de passe sont requis";
        } elseif($newPassword !== $confirmPassword) {
            $error = "Les nouveaux mots de passe ne correspondent pas";
        } elseif(strlen($newPassword) < 6) {
            $error = "Le nouveau mot de passe doit contenir au moins 6 caractères";
        } elseif(!password_verify($currentPassword, $currentUser['password'])) {
            $error = "Le mot de passe actuel est incorrect";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                $success = "Mot de passe modifié avec succès";
            } else {
                $error = "Erreur lors de la modification du mot de passe";
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
    <title>Mon profil - Shareman V4.6</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .navbar { 
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 1rem;
            color: white;
        }
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn { 
            padding: 0.6rem 1.2rem; 
            background: rgba(255, 255, 255, 0.2);
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { 
            background: rgba(255, 255, 255, 0.3);
        }
        .container { 
            max-width: 800px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
        }
        .section { 
            background: rgba(255, 255, 255, 0.95);
            margin-bottom: 2rem; 
            padding: 2rem; 
            border-radius: 16px; 
        }
        .section h2 { 
            margin-bottom: 1.5rem; 
            color: #1f2937;
        }
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #374151; 
            font-weight: 600; 
        }
        .form-group input { 
            width: 100%; 
            padding: 0.75rem; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
        }
        .form-group input:focus { 
            outline: none; 
            border-color: #4f46e5;
        }
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 2px solid #fecaca;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #6ee7b7;
        }
        .info-box {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Mon profil</h1>
            <a href="dashboard.php" class="btn">← Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <!-- Informations actuelles -->
        <div class="section">
            <h2>Informations du compte</h2>
            <div class="info-box">
                <p><strong>Nom d'utilisateur :</strong> <?= htmlspecialchars($currentUser['username']) ?></p>
                <p><strong>Email actuel :</strong> <?= htmlspecialchars($currentUser['email']) ?></p>
                <p><strong>Statut :</strong> <?= htmlspecialchars($currentUser['status']) ?></p>
            </div>
        </div>
        
        <!-- Modification de l'email -->
        <div class="section">
            <h2>Modifier l'adresse email</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_email">
                <div class="form-group">
                    <label>Nouvelle adresse email :</label>
                    <input type="email" name="email" required 
                           value="<?= htmlspecialchars($currentUser['email']) ?>">
                    <small>Votre adresse email doit être unique sur la plateforme</small>
                </div>
                <button type="submit" class="btn-primary">Mettre à jour l'email</button>
            </form>
        </div>
        
        <!-- Modification du mot de passe -->
        <div class="section">
            <h2>Modifier le mot de passe</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label>Mot de passe actuel :</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Nouveau mot de passe :</label>
                    <input type="password" name="new_password" required minlength="6">
                    <small>Minimum 6 caractères</small>
                </div>
                <div class="form-group">
                    <label>Confirmer le nouveau mot de passe :</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn-primary">Modifier le mot de passe</button>
            </form>
        </div>
    </div>
</body>
</html>