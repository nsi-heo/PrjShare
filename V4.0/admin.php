<?php
// admin.php - Interface d'administration
require_once 'auth.php';
requireAdmin();
require_once 'classes/User.php';
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$userManager = new User($db);
$groupManager = new Group($db);

// Traitement des actions admin
if($_POST) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'update_user_status') {
        $userId = $_POST['user_id'];
        $status = $_POST['status'];
        $userManager->updateUserStatus($userId, $status);
    }
    
    if($action === 'delete_user') {
        $userId = $_POST['user_id'];
        $userManager->deleteUser($userId);
    }
    
    if($action === 'delete_group') {
        $groupId = $_POST['group_id'];
        $groupManager->deleteGroup($groupId);
    }
}

$users = $userManager->getAllUsers();
$groups = $groupManager->getAllGroups();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Shareman</title>
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
        .container { 
            max-width: 1200px; 
            margin: 2rem auto; 
            padding: 0 2rem; 
        }
        .section { 
            background: white; 
            margin-bottom: 2rem; 
            padding: 1.5rem; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .section h2 { margin-bottom: 1rem; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; }
        .btn { 
            padding: 0.5rem 1rem; 
            background: #667eea; 
            color: white; 
            text-decoration: none; 
            border-radius: 6px; 
            border: none;
            cursor: pointer;
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }
        .btn:hover { background: #5a67d8; }
        .btn.danger { background: #e53e3e; }
        .btn.danger:hover { background: #c53030; }
        .status-select { padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px; }
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
        <h1>🛠️ Administration Shareman</h1>
        <div>
            <a href="create_group.php" class="btn">Créer un groupe</a>
            <a href="dashboard.php" class="btn">← Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <!-- Gestion des utilisateurs -->
        <div class="section">
            <h2>👤 Gestion des utilisateurs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $user['status'] ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_user_status">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="visiteur" <?= $user['status'] === 'visiteur' ? 'selected' : '' ?>>Visiteur</option>
                                        <option value="utilisateur" <?= $user['status'] === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                                        <option value="administrateur" <?= $user['status'] === 'administrateur' ? 'selected' : '' ?>>Administrateur</option>
                                    </select>
                                </form>
                                
                                <?php if($user['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cet utilisateur?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn danger">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Gestion des groupes -->
        <div class="section">
            <h2>👥 Gestion des groupes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom du groupe</th>
                        <th>Description</th>
                        <th>Créé par</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($groups as $group): ?>
                        <tr>
                            <td><?= htmlspecialchars($group['name']) ?></td>
                            <td><?= htmlspecialchars($group['description'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?></td>
                            <td><?= date('d/m/Y', strtotime($group['created_at'])) ?></td>
                            <td>
                                <a href="group.php?id=<?= $group['id'] ?>" class="btn">Voir</a>
                                <a href="edit_group.php?id=<?= $group['id'] ?>" class="btn">Modifier</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce groupe et toutes ses données?')">
                                    <input type="hidden" name="action" value="delete_group">
                                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                    <button type="submit" class="btn danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
