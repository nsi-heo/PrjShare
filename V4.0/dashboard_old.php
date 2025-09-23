<?php
// dashboard.php - Tableau de bord principal
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);

$groups = $groupManager->getAllGroups();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Shareman</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f7fa;
        }
        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { font-size: 1.8rem; }
        .navbar .user-info { display: flex; gap: 1rem; align-items: center; }
        .container { 
            max-width: 1200px; 
            margin: 2rem auto; 
            padding: 0 2rem; 
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem; 
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            background: #667eea; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover { background: #5a67d8; }
        .btn.danger { background: #e53e3e; }
        .btn.danger:hover { background: #c53030; }
        .groups-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
            gap: 1.5rem; 
        }
        .group-card { 
            background: white; 
            padding: 1.5rem; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .group-card:hover { transform: translateY(-2px); }
        .group-card h3 { color: #333; margin-bottom: 0.5rem; }
        .group-card p { color: #666; margin-bottom: 1rem; }
        .group-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .group-actions .btn { padding: 0.5rem 1rem; font-size: 0.9rem; }
        .status-badge { 
            padding: 0.25rem 0.5rem; 
            border-radius: 4px; 
            font-size: 0.8rem; 
            font-weight: bold; 
        }
        .status-visiteur { background: #fed7d7; color: #c53030; }
        .status-utilisateur { background: #c6f6d5; color: #276749; }
        .status-administrateur { background: #bee3f8; color: #2a69ac; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>💰 Shareman</h1>
        <div class="user-info">
            <span class="status-badge status-<?= getUserStatus() ?>">
                <?= ucfirst(getUserStatus()) ?>
            </span>
            <span>Bienvenue, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <?php if(isAdmin()): ?>
                <a href="admin.php" class="btn">Administration</a>
            <?php endif; ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn danger">Déconnexion</button>
            </form>
        </div>
    </nav>
    
    <div class="container">
        <div class="header">
            <h2>Mes groupes</h2>
            <?php if(isAdmin()): ?>
                <a href="create_group.php" class="btn">Créer un groupe</a>
            <?php endif; ?>
        </div>
        
        <div class="groups-grid">
            <?php foreach($groups as $group): ?>
                <div class="group-card">
                    <h3><?= htmlspecialchars($group['name']) ?></h3>
                    <p><?= htmlspecialchars($group['description'] ?: 'Aucune description') ?></p>
                    <p><small>Créé par: <?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?></small></p>
                    
                    <div class="group-actions">
                        <a href="group.php?id=<?= $group['id'] ?>" class="btn">Voir le groupe</a>
                        <?php if(isAdmin()): ?>
                            <a href="edit_group.php?id=<?= $group['id'] ?>" class="btn">Modifier</a>
                            <a href="delete_group.php?id=<?= $group['id'] ?>" class="btn danger" 
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe?')">Supprimer</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($groups)): ?>
                <div class="group-card">
                    <h3>Aucun groupe trouvé</h3>
                    <p>Commencez par créer votre premier groupe de dépenses!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
