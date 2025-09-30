<?php
// admin.php - Interface d'administration V4.3
require_once 'auth.php';
requireAdmin();
require_once 'classes/User.php';
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$userManager = new User($db);
$groupManager = new Group($db);

$success = '';
$error = '';

// Traitement des actions admin
if($_POST) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'update_user_status') {
        $userId = $_POST['user_id'];
        $status = $_POST['status'];
        if($userManager->updateUserStatus($userId, $status)) {
            $success = "Statut utilisateur mis à jour";
        }
    }
    
    if($action === 'delete_user') {
        $userId = $_POST['user_id'];
        if($userManager->deleteUser($userId)) {
            $success = "Utilisateur supprimé";
        }
    }
    
    if($action === 'delete_group') {
        $groupId = $_POST['group_id'];
        if($groupManager->deleteGroup($groupId)) {
            $success = "Groupe supprimé";
        }
    }
    
    // NOUVEAU V4.3 : Gestion des demandes
    if($action === 'approve_request') {
        $requestId = $_POST['request_id'];
        $result = $groupManager->approveJoinRequest($requestId);
        if($result['status'] === 'success') {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    if($action === 'reject_request') {
        $requestId = $_POST['request_id'];
        $result = $groupManager->rejectJoinRequest($requestId);
        if($result['status'] === 'success') {
            $success = "Demande rejetée";
        } else {
            $error = $result['message'];
        }
    }
}

$users = $userManager->getAllUsers();
$groups = $groupManager->getAllGroups();
$pendingRequests = $groupManager->getPendingJoinRequests();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Shareman V4.3</title>
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            font-size: 1.8rem;
            font-weight: 600;
        }
        .container { 
            max-width: 1200px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
        }
        .section { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin-bottom: 2rem; 
            padding: 2rem; 
            border-radius: 16px; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.1); 
        }
        .section h2 { 
            margin-bottom: 1.5rem; 
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1rem; 
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td { 
            padding: 1rem; 
            text-align: left; 
            border-bottom: 1px solid #e2e8f0; 
        }
        th { 
            background: #f7fafc; 
            font-weight: 600;
            color: #374151;
        }
        .btn { 
            padding: 0.5rem 1rem; 
            background: #667eea; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            border: none;
            cursor: pointer;
            margin-right: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .btn:hover { 
            background: #5a67d8; 
            transform: translateY(-1px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .btn-success {
            background: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-danger { 
            background: #ef4444; 
        }
        .btn-danger:hover { 
            background: #dc2626; 
        }
        .status-select { 
            padding: 0.5rem; 
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            background: white;
        }
        .status-badge { 
            padding: 0.25rem 0.6rem; 
            border-radius: 12px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            display: inline-block;
        }
        .status-visiteur { 
            background: #fef3c7; 
            color: #92400e; 
        }
        .status-utilisateur { 
            background: #d1fae5; 
            color: #065f46; 
        }
        .status-administrateur { 
            background: #dbeafe; 
            color: #1e40af; 
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            opacity: 0.9;
            font-size: 0.875rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                text-align: center;
            }
            .container {
                padding: 1rem 0.5rem;
            }
            .section {
                padding: 1.5rem;
            }
            table {
                font-size: 0.875rem;
            }
            th, td {
                padding: 0.75rem 0.5rem;
            }
            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>🛠️ Administration Shareman V4.3</h1>
            <div>
                <a href="create_group.php" class="btn btn-primary">Créer un groupe</a>
                <a href="dashboard.php" class="btn">← Dashboard</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($users) ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($groups) ?></div>
                <div class="stat-label">Groupes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($pendingRequests) ?></div>
                <div class="stat-label">Demandes en attente</div>
            </div>
        </div>
        
        <!-- NOUVEAU V4.3 : Gestion des demandes d'intégration -->
        <div class="section">
            <h2>⏳ Demandes d'intégration en attente</h2>
            <?php if(empty($pendingRequests)): ?>
                <div class="empty-state">
                    <p>Aucune demande d'intégration en attente.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Statut actuel</th>
                            <th>Groupe demandé</th>
                            <th>Date de demande</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingRequests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['username']) ?></td>
                                <td><?= htmlspecialchars($request['email']) ?></td>
                                <td>
                                    <?php 
                                    $userStatus = $userManager->getUserById($request['user_id']);
                                    if($userStatus):
                                    ?>
                                        <span class="status-badge status-<?= $userStatus['status'] ?>">
                                            <?= ucfirst($userStatus['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($request['group_name']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_request">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" class="btn btn-success" 
                                                onclick="return confirm('Approuver cette demande ?')">
                                            ✓ Approuver
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject_request">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('Rejeter cette demande ?')">
                                            ✗ Rejeter
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
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
                                        <button type="submit" class="btn btn-danger">Supprimer</button>
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
                                    <button type="submit" class="btn btn-danger">Supprimer</button>
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