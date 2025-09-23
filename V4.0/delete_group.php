<?php
// delete_group.php - Suppression d'un groupe
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

// Compter les membres et dépenses
$members = $groupManager->getGroupMembers($groupId);
$expenseQuery = "SELECT COUNT(*) FROM expenses WHERE group_id = ?";
$expenseStmt = $db->prepare($expenseQuery);
$expenseStmt->execute([$groupId]);
$expenseCount = $expenseStmt->fetchColumn();

if($_POST && $_POST['action'] === 'confirm_delete') {
    if($groupManager->deleteGroup($groupId)) {
        header('Location: dashboard.php?deleted=1');
        exit;
    } else {
        $error = "Erreur lors de la suppression du groupe";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer le groupe - Shareman</title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #fef2f2;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar { 
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
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
        
        .container { 
            max-width: 600px; 
            margin: 2rem auto; 
            padding: 0 1rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .danger-card { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.2);
            border: 2px solid #fecaca;
            overflow: hidden;
            width: 100%;
        }
        
        .danger-header {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #fecaca;
        }
        
        .danger-icon {
            width: 64px;
            height: 64px;
            background: #dc2626;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }
        
        .danger-header h2 {
            font-size: 1.5rem;
            color: #dc2626;
            margin-bottom: 0.5rem;
        }
        
        .danger-header p {
            color: #b91c1c;
            font-weight: 500;
        }
        
        .danger-body {
            padding: 2rem;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .warning-box h4 {
            color: #d97706;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .warning-list {
            list-style: none;
            padding: 0;
        }
        
        .warning-list li {
            color: #92400e;
            padding: 0.25rem 0;
            position: relative;
            padding-left: 1.5rem;
        }
        
        .warning-list li:before {
            content: '•';
            color: #f59e0b;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        
        .group-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .group-info h4 {
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            color: #6b7280;
        }
        
        .confirmation-text {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        
        .btn-cancel {
            background: #6b7280;
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            text-align: center;
        }
        
        .btn-cancel:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        .error { 
            background: #fee2e2; 
            color: #dc2626; 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1.5rem; 
            border: 1px solid #fecaca;
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
            
            .danger-header,
            .danger-body {
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-danger,
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
            <h1>Supprimer le groupe</h1>
            <div>
                <a href="group.php?id=<?= $groupId ?>" class="btn">← Retour au groupe</a>
                <a href="dashboard.php" class="btn">Dashboard</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="danger-card">
            <div class="danger-header">
                <div class="danger-icon">⚠</div>
                <h2>Zone de danger</h2>
                <p>Cette action ne peut pas être annulée</p>
            </div>
            
            <div class="danger-body">
                <?php if(isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="warning-box">
                    <h4>Cette suppression entraînera la perte définitive de :</h4>
                    <ul class="warning-list">
                        <li>Toutes les informations du groupe</li>
                        <li>Tous les membres du groupe (<?= count($members) ?> membres)</li>
                        <li>Toutes les dépenses enregistrées (<?= $expenseCount ?> dépenses)</li>
                        <li>L'historique complet des transactions</li>
                        <li>Les calculs de remboursements</li>
                    </ul>
                </div>
                
                <div class="group-info">
                    <h4>Informations du groupe à supprimer :</h4>
                    <div class="info-item">
                        <strong>Nom :</strong>
                        <span><?= htmlspecialchars($group['name']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Description :</strong>
                        <span><?= htmlspecialchars($group['description'] ?: 'Aucune') ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Créateur :</strong>
                        <span><?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Membres :</strong>
                        <span><?= count($members) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Dépenses :</strong>
                        <span><?= $expenseCount ?></span>
                    </div>
                </div>
                
                <div class="confirmation-text">
                    <strong>Attention :</strong> En confirmant cette suppression, vous acceptez que toutes les données 
                    associées à ce groupe soient définitivement perdues. Cette action ne peut pas être annulée.
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="confirm_delete">
                    <div class="form-actions">
                        <button type="submit" 
                                class="btn-danger" 
                                onclick="return confirm('Êtes-vous ABSOLUMENT certain de vouloir supprimer ce groupe ? Cette action est irréversible.')">
                            Oui, supprimer définitivement
                        </button>
                        <a href="dashboard.php" class="btn-cancel">
                            Annuler et retourner au dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>