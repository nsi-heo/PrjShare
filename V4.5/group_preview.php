<?php
// group_preview.php - Aperçu d'un groupe pour les non-membres
require_once 'auth.php';
requireAuth();
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

// Vérifier si l'utilisateur est membre
$isMember = $groupManager->isUserInGroup($groupId, $_SESSION['user_id']);
if($isMember) {
    header('Location: group.php?id=' . $groupId);
    exit;
}

$members = $groupManager->getGroupMembers($groupId);
$hasPendingRequest = $groupManager->hasPendingRequest($groupId, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> - Aperçu</title>
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
            line-height: 1.6;
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
        }
        
        .btn:hover { 
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 2rem 1rem; 
        }
        
        .section { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin-bottom: 2rem; 
            padding: 2rem; 
            border-radius: 16px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .section h2 { 
            margin-bottom: 1.5rem; 
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .warning-banner {
            background: linear-gradient(135deg, #fef3c7, #fed7aa);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #92400e;
        }
        
        .warning-banner h3 {
            margin-bottom: 0.5rem;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-banner {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 2px solid #3b82f6;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #1e40af;
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .member-card {
            background: #f9fafb;
            padding: 1.25rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .member-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .member-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-linked {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-unlinked {
            background: #fef3c7;
            color: #92400e;
        }
        
        .locked-content {
            text-align: center;
            padding: 3rem 2rem;
            background: #f3f4f6;
            border-radius: 12px;
            border: 2px dashed #d1d5db;
        }
        
        .locked-content .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .locked-content h3 {
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .locked-content p {
            color: #6b7280;
            margin-bottom: 2rem;
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
            
            .members-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1><?= htmlspecialchars($group['name']) ?> - Aperçu</h1>
            <div>
                <a href="dashboard.php" class="btn">← Retour au dashboard</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="warning-banner">
            <h3>🔒 Accès limité</h3>
            <p style="margin-bottom: 0;">
                Vous n'êtes pas membre de ce groupe. Vous pouvez voir les informations de base 
                mais pas les dépenses ni les bilans.
            </p>
        </div>
        
        <?php if($hasPendingRequest): ?>
            <div class="info-banner">
                <h3 style="color: #1e40af; margin-bottom: 0.5rem;">⏳ Demande en attente</h3>
                <p style="margin-bottom: 0;">
                    Votre demande d'intégration est en cours d'examen par un administrateur.
                </p>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 2rem;">
                <a href="request_join.php?group_id=<?= $groupId ?>" class="btn btn-primary" style="font-size: 1rem; padding: 0.75rem 2rem;">
                    Demander à rejoindre ce groupe
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Informations du groupe -->
        <div class="section">
            <h2>Informations du groupe</h2>
            <div style="background: #f9fafb; padding: 1.5rem; border-radius: 10px; border: 1px solid #e5e7eb;">
                <p style="margin-bottom: 1rem; color: #6b7280;">
                    <strong style="color: #374151;">Nom :</strong> <?= htmlspecialchars($group['name']) ?>
                </p>
                <p style="margin-bottom: 1rem; color: #6b7280;">
                    <strong style="color: #374151;">Description :</strong> 
                    <?= htmlspecialchars($group['description'] ?: 'Aucune description disponible') ?>
                </p>
                <p style="margin-bottom: 1rem; color: #6b7280;">
                    <strong style="color: #374151;">Créé par :</strong> 
                    <?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?>
                </p>
                <p style="color: #6b7280;">
                    <strong style="color: #374151;">Date de création :</strong> 
                    <?= date('d/m/Y', strtotime($group['created_at'])) ?>
                </p>
            </div>
        </div>
        
        <!-- Membres du groupe -->
        <div class="section">
            <h2>Membres du groupe (<?= count($members) ?>)</h2>
            
            <?php if(empty($members)): ?>
                <p style="color: #6b7280;">Aucun membre dans ce groupe.</p>
            <?php else: ?>
                <div class="members-grid">
                    <?php foreach($members as $member): ?>
                        <div class="member-card">
                            <div class="member-name"><?= htmlspecialchars($member['member_name']) ?></div>
                            <span class="member-status <?= $member['user_id'] ? 'status-linked' : 'status-unlinked' ?>">
                                <?= $member['user_id'] ? 'Compte lié' : 'Non lié' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Dépenses verrouillées -->
        <div class="section">
            <h2>Dépenses du groupe</h2>
            <div class="locked-content">
                <div class="icon">🔒</div>
                <h3>Contenu réservé aux membres</h3>
                <p>
                    Les dépenses et les bilans ne sont visibles que par les membres du groupe.
                    <?php if(!$hasPendingRequest): ?>
                        Demandez l'accès pour voir ces informations.
                    <?php endif; ?>
                </p>
                <?php if(!$hasPendingRequest): ?>
                    <a href="request_join.php?group_id=<?= $groupId ?>" class="btn btn-primary">
                        Demander l'accès
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bilans verrouillés -->
        <div class="section">
            <h2>Bilans des comptes</h2>
            <div class="locked-content">
                <div class="icon">🔒</div>
                <h3>Contenu réservé aux membres</h3>
                <p>
                    Les bilans et règlements ne sont visibles que par les membres du groupe.
                </p>
            </div>
        </div>
    </div>
</body>
</html>