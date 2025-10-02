<?php
// change_password.php - V4.6 Changement obligatoire du mot de passe temporaire
require_once 'auth.php';
requireAuth();
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$userManager = new User($db);

$currentUser = $userManager->getUserById($_SESSION['user_id']);
$error = '';
$reason = $_GET['reason'] ?? 'general';

// Si l'utilisateur n'a pas besoin de changer son mot de passe, rediriger
if(!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true) {
    header('Location: dashboard.php');
    exit;
}

if($_POST) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // DEBUG
    error_log("=== DÉBUT CHANGEMENT MOT DE PASSE ===");
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("New password length: " . strlen($newPassword));
    error_log("Passwords match: " . ($newPassword === $confirmPassword ? 'OUI' : 'NON'));
    
    if(empty($newPassword) || empty($confirmPassword)) {
        $error = "Tous les champs sont requis";
        error_log("ERREUR: Champs vides");
    } elseif($newPassword !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas";
        error_log("ERREUR: Mots de passe différents");
    } elseif(strlen($newPassword) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
        error_log("ERREUR: Mot de passe trop court");
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        error_log("Hash généré: " . substr($hashedPassword, 0, 20) . "...");
        
        // Mettre à jour le mot de passe et désactiver le flag
        $query = "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?";
        $stmt = $db->prepare($query);
        
        error_log("Exécution de la requête UPDATE...");
        
        if($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
            error_log("UPDATE réussi!");
            error_log("Nombre de lignes affectées: " . $stmt->rowCount());
            
            // Vérification après UPDATE
            $checkQuery = "SELECT must_change_password FROM users WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$_SESSION['user_id']]);
            $check = $checkStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Valeur must_change_password après UPDATE: " . ($check['must_change_password'] ? 'TRUE' : 'FALSE'));
            
            // Supprimer le flag de la session
            unset($_SESSION['must_change_password']);
            error_log("Flag supprimé de la session");
            error_log("=== FIN CHANGEMENT MOT DE PASSE - SUCCÈS ===");
            
            // Rediriger vers le dashboard avec message de succès
            header('Location: dashboard.php?password_changed=1');
            exit;
        } else {
            $error = "Erreur lors de la modification du mot de passe";
            error_log("ERREUR lors de l'exécution de UPDATE");
            error_log("PDO Error: " . print_r($stmt->errorInfo(), true));
            error_log("=== FIN CHANGEMENT MOT DE PASSE - ÉCHEC ===");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changement de mot de passe obligatoire - Shareman V4.6</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
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
            padding: 2.5rem; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            max-width: 500px;
            width: 90%;
        }
        .header { 
            text-align: center; 
            margin-bottom: 2rem; 
        }
        .header .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .header h1 { 
            color: #333; 
            font-size: 1.8rem; 
            margin-bottom: 0.5rem; 
        }
        .header p { 
            color: #666; 
            font-size: 0.95rem;
        }
        .warning-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #92400e;
        }
        .warning-box strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #333; 
            font-weight: 600; 
        }
        .form-group input { 
            width: 100%; 
            padding: 0.875rem; 
            border: 2px solid #e1e5e9; 
            border-radius: 10px; 
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus { 
            outline: none; 
            border-color: #667eea; 
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.875rem;
        }
        .btn { 
            width: 100%; 
            padding: 0.875rem; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none; 
            border-radius: 10px; 
            font-size: 1rem; 
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .alert-error { 
            background: #fee2e2; 
            color: #dc2626; 
            padding: 1rem; 
            border-radius: 10px; 
            margin-bottom: 1.5rem; 
            border: 2px solid #fecaca;
            font-weight: 500;
        }
        .requirements {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .requirements h3 {
            color: #374151;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .requirements ul {
            list-style: none;
            padding: 0;
        }
        .requirements li {
            color: #6b7280;
            font-size: 0.875rem;
            padding: 0.25rem 0;
            padding-left: 1.5rem;
            position: relative;
        }
        .requirements li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🔐</div>
            <h1>Changement de mot de passe obligatoire</h1>
            <p>Vous devez définir un nouveau mot de passe</p>
        </div>
        
        <?php if($reason === 'temporary'): ?>
            <div class="warning-box">
                <strong>Mot de passe temporaire détecté</strong>
                Vous vous êtes connecté avec un mot de passe temporaire. Pour des raisons de sécurité, 
                vous devez définir un nouveau mot de passe personnel avant de continuer.
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="requirements">
            <h3>Exigences pour le mot de passe :</h3>
            <ul>
                <li>Minimum 6 caractères</li>
                <li>Différent du mot de passe temporaire</li>
                <li>Facile à retenir pour vous</li>
            </ul>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Nouveau mot de passe :</label>
                <input type="password" name="new_password" required minlength="6" 
                       placeholder="Entrez votre nouveau mot de passe">
            </div>
            
            <div class="form-group">
                <label>Confirmer le mot de passe :</label>
                <input type="password" name="confirm_password" required minlength="6"
                       placeholder="Confirmez votre nouveau mot de passe">
                <small>Les deux mots de passe doivent être identiques</small>
            </div>
            
            <button type="submit" class="btn">Définir mon nouveau mot de passe</button>
        </form>
        
        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
            <small style="color: #6b7280;">
                Vous ne pouvez pas accéder à votre compte tant que vous n'avez pas changé votre mot de passe.
            </small>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            form.addEventListener('submit', function(e) {
                if(newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Les mots de passe ne correspondent pas');
                    confirmPassword.focus();
                }
            });
            
            newPassword.focus();
        });
    </script>
</body>
</html>