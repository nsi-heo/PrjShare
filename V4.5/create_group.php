<?php
// create_group.php - Création de groupe (version corrigée)
require_once 'auth.php';
requireAdmin();
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);

$error = '';
$success = '';

if($_POST) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if(empty($name)) {
        $error = "Le nom du groupe est requis";
    } else {
        // Vérifier si le nom du groupe existe déjà
        $checkQuery = "SELECT id FROM groups_table WHERE name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$name]);
        
        if($checkStmt->fetch()) {
            $error = "Un groupe avec ce nom existe déjà. Veuillez choisir un autre nom.";
        } else {
            // Créer le groupe
            $groupId = $groupManager->createGroup($name, $description, $_SESSION['user_id']);
            
            if($groupId) {
                header('Location: group.php?id=' . $groupId . '&created=1');
                exit;
            } else {
                $error = "Erreur lors de la création du groupe. Veuillez réessayer.";
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
    <title>Créer un groupe - Shareman</title>
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
            flex-direction: column;
        }
        
        .navbar { 
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 1rem;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .navbar h1 { 
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .btn { 
            display: inline-flex;
            align-items: center;
            padding: 0.6rem 1.2rem; 
            background: rgba(255, 255, 255, 0.2);
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn:hover { 
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .container { 
            max-width: 600px; 
            margin: 2rem auto; 
            padding: 0 1rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2.5rem; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h2 {
            color: #1f2937;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #374151; 
            font-weight: 600; 
            font-size: 0.9rem;
        }
        
        .form-group input, 
        .form-group textarea { 
            width: 100%; 
            padding: 1rem; 
            border: 2px solid #e5e7eb; 
            border-radius: 12px; 
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus { 
            outline: none; 
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group textarea { 
            height: 120px; 
            resize: vertical; 
            font-family: inherit;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
        }
        
        .btn-secondary {
            width: 100%;
            background: #6b7280;
            color: white;
            padding: 1rem 1.5rem;
            text-decoration: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            display: block;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        .alert { 
            padding: 1rem; 
            border-radius: 12px; 
            margin-bottom: 1.5rem; 
            font-weight: 500;
            border: 2px solid;
        }
        
        .alert-error { 
            background: #fee2e2; 
            color: #dc2626; 
            border-color: #fecaca;
        }
        
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
            border-color: #a7f3d0;
        }
        
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                text-align: center;
            }
            
            .container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }
            
            .form-card {
                padding: 2rem;
            }
            
            .form-header h2 {
                font-size: 1.5rem;
            }
            
            .form-group input,
            .form-group textarea {
                padding: 0.875rem;
            }
            
            .btn-primary {
                padding: 0.875rem 1.25rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Créer un groupe</h1>
            <div>
                <a href="dashboard.php" class="btn">← Retour au dashboard</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h2>Nouveau groupe</h2>
                <p>Créez un groupe pour partager vos dépenses</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="name">Nom du groupe *</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required 
                           maxlength="100"
                           placeholder="Ex: Vacances été 2024"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <small>Le nom doit être unique et ne pas dépasser 100 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" 
                              name="description" 
                              placeholder="Décrivez le groupe et son objectif..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <small>Optionnel - aidez les membres à comprendre le but du groupe</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        Créer le groupe
                    </button>
                    <a href="dashboard.php" class="btn-secondary">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-focus sur le champ nom
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('name').focus();
        });
        
        // Validation côté client
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            
            if(!name) {
                e.preventDefault();
                alert('Le nom du groupe est requis.');
                document.getElementById('name').focus();
                return;
            }
            
            if(name.length > 100) {
                e.preventDefault();
                alert('Le nom du groupe ne peut pas dépasser 100 caractères.');
                document.getElementById('name').focus();
                return;
            }
        });
    </script>
</body>
</html>