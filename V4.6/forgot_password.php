<?php
// forgot_password.php - V4.5 Réinitialisation du mot de passe
session_start();
require_once 'config/database.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$userManager = new User($db);

$message = '';
$error = '';

if($_POST) {
    $email = trim($_POST['email'] ?? '');
    
    if(empty($email)) {
        $error = "L'adresse email est requise";
    } else {
        // Vérifier si l'email existe
        $query = "SELECT id, username, email FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user) {
            // Générer un mot de passe provisoire
            $tempPassword = bin2hex(random_bytes(8)); // 16 caractères
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // MODIFIÉ : Activer le flag must_change_password
			$updateQuery = "UPDATE users SET password = ?, must_change_password = TRUE WHERE id = ?";
			$updateStmt = $db->prepare($updateQuery);
			$updateStmt->execute([$hashedPassword, $user['id']]);
            
            // Envoyer l'email
            $to = $user['email'];
            $subject = "Shareman - Réinitialisation de votre mot de passe";
            $messageBody = "Bonjour " . htmlspecialchars($user['username']) . ",\n\n";
            $messageBody .= "Votre mot de passe provisoire est : " . $tempPassword . "\n\n";
            $messageBody .= "Veuillez vous connecter avec ce mot de passe et le modifier immédiatement dans votre profil.\n\n";
            $messageBody .= "Si vous n'avez pas demandé cette réinitialisation, veuillez contacter un administrateur.\n\n";
            $messageBody .= "Cordialement,\nL'équipe Shareman";
            
            $headers = "From: noreply@shareman.com\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            if(mail($to, $subject, $messageBody, $headers)) {
                $message = "Un mot de passe provisoire a été envoyé à votre adresse email. Veuillez vérifier votre boîte de réception.";
            } else {
                $error = "Erreur lors de l'envoi de l'email. Mot de passe provisoire : <strong>" . htmlspecialchars($tempPassword) . "</strong>";
            }
        } else {
            // Pour la sécurité, ne pas révéler si l'email existe ou non
            $message = "Si cette adresse email est enregistrée, un mot de passe provisoire a été envoyé.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Shareman V4.5</title>
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
            max-width: 450px;
            width: 90%;
        }
        .header { 
            text-align: center; 
            margin-bottom: 2rem; 
        }
        .header h1 { 
            color: #333; 
            font-size: 2rem; 
            margin-bottom: 0.5rem; 
        }
        .header p { 
            color: #666; 
            font-size: 0.95rem;
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
        .back-link { 
            text-align: center; 
            margin-top: 1.5rem; 
            color: #666; 
        }
        .back-link a { 
            color: #667eea; 
            text-decoration: none; 
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Mot de passe oublié</h1>
            <p>Entrez votre adresse email pour recevoir un mot de passe provisoire</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Adresse email :</label>
                <input type="email" name="email" required placeholder="votre@email.com">
            </div>
            <button type="submit" class="btn">Réinitialiser le mot de passe</button>
        </form>
        
        <div class="back-link">
            <a href="index.php">← Retour à la connexion</a>
        </div>
    </div>
</body>
</html>