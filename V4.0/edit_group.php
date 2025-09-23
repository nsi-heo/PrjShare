<?php
// edit_group.php - Modification d'un groupe
require_once 'auth.php';
requireAdmin();
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);

$groupId = $_GET['id'] ?? 0;
$group = $groupManager->getGroupById($groupId);

if(!$group) {
    header('Location: dashboard.php');
    exit;
}

if($_POST) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if(empty($name)) {
        $error = "Le nom du groupe est requis";
    } else {
        // Vérifier si le nom existe déjà (en excluant le groupe actuel)
        if($groupManager->groupNameExists($name, $groupId)) {
            $error = "Un autre groupe avec ce nom existe déjà. Veuillez choisir un autre nom.";
        } else {
            if($groupManager->updateGroup($groupId, $name, $description)) {
                header('Location: group.php?id=' . $groupId . '&updated=1');
                exit;
            } else {
                $error = "Erreur lors de la modification du groupe";
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
    <title>Modifier le groupe - Shareman</title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8fafc;
            line-height: 1.6;
        }
        
        .navbar { 
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 1rem;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            padding: 0.5rem 1rem; 
            background: rgba(255,255,255,0.2);
            color: white; 
            text-decoration: none; 
            border-radius: 6px; 
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
        }
        
        .btn:hover { 
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: #10b981;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .container { 
            max-width: 800px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
        }
        
        .card { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        
        .card-header p {
            color: #6b7280;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #374151; 
            font-weight: 500; 
            font-size: 0.875rem;
        }
        
        .form-group input, 
        .form-group textarea { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 1rem;
            transition: all 0.2s;
            background: #fff;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus { 
            outline: none; 
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-group textarea { 
            height: 120px; 
            resize: vertical; 
        }
        
        .error { 
            background: #fee2e2; 
            color: #dc2626; 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1.5rem; 
            border: 1px solid #fecaca;
            font-size: 0.875rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .btn-cancel {
            background: #6b7280;
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            background: #4b5563;
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
            
            .card-body {
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-cancel {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Modifier le groupe</h1>
            <div>
                <a href="group.php?id=<?= $groupId ?>" class="btn">← Retour au groupe</a>
                <a href="dashboard.php" class="btn">Dashboard</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><?= htmlspecialchars($group['name']) ?></h2>
                <p>Modifiez les informations du groupe</p>
            </div>
            
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Nom du groupe *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?= htmlspecialchars($group['name']) ?>" 
                               required 
                               placeholder="Ex: Vacances été 2024">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" 
                                  name="description" 
                                  placeholder="Décrivez le groupe et son objectif..."><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Enregistrer les modifications
                        </button>
                        <a href="dashboard.php" class="btn-cancel">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>