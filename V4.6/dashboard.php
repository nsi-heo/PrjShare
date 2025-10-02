
<?php
// dashboard.php - V4.5 avec affichage par défaut des groupes membres
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);

// NOUVEAU V4.5 : Par défaut, afficher uniquement les groupes dont on est membre
// Sauf si l'utilisateur coche explicitement "show_all"
$showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';

if(isset($_GET['password_changed']) && $_GET['password_changed'] == '1'): ?>

    <div class="success-message">
        Votre mot de passe a été changé avec succès. Vous pouvez maintenant utiliser l'application normalement.
    </div>

 <?php endif;

// Pour les administrateurs : par défaut membres uniquement, sauf si show_all=1
// Pour les autres : toujours membres uniquement, sauf si show_all=1
if (isAdmin()) {
    $groups = $groupManager->getGroupsForUser($_SESSION['user_id'], !$showAll);
} else {
    $groups = $groupManager->getGroupsForUser($_SESSION['user_id'], !$showAll);
}

$showDeleteMessage = isset($_GET['deleted']) && $_GET['deleted'] == '1';
$showRequestMessage = isset($_GET['request_sent']) && $_GET['request_sent'] == '1';

// Calculer le nombre de groupes dont l'utilisateur est membre
$memberCount = 0;
$createdCount = 0;
foreach($groups as $g) {
    if(isset($g['is_member']) && $g['is_member'] > 0) {
        $memberCount++;
    }
    if(isset($g['is_creator']) && $g['is_creator'] > 0) {
        $createdCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Shareman V4.5</title>
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
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .user-info { 
            display: flex; 
            gap: 1rem; 
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-badge { 
            padding: 0.4rem 0.8rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-visiteur { 
            background: linear-gradient(135deg, #fef3c7, #fcd34d);
            color: #92400e; 
        }
        
        .status-utilisateur { 
            background: linear-gradient(135deg, #dcfce7, #86efac);
            color: #166534; 
        }
        
        .status-administrateur { 
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1e40af; 
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
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 2rem 1rem; 
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .filter-section label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-weight: 500;
        }
        
        .filter-section input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .success-message, .info-message {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid #6ee7b7;
            font-weight: 500;
        }
        
        .info-message {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .groups-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 1.5rem; 
        }
        
        .group-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem; 
            border-radius: 20px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .group-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .group-card.non-member {
            opacity: 0.8;
        }
        
        .group-card.non-member::before {
            background: linear-gradient(90deg, #9ca3af, #6b7280);
        }
        
        .group-card:hover { 
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }
        
        .group-card h3 { 
            color: #1f2937; 
            margin-bottom: 0.8rem;
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .group-card p { 
            color: #6b7280; 
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        
        .membership-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .badge-member {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-non-member {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .group-actions { 
            display: flex; 
            gap: 0.75rem; 
            flex-wrap: wrap; 
        }
        
        .group-actions .btn { 
            padding: 0.6rem 1rem; 
            font-size: 0.875rem;
            background: #667eea;
            color: white;
            border: none;
            flex: 1;
            min-width: 100px;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .groups-grid {
                grid-template-columns: 1fr;
            }
            
            .group-actions {
                flex-direction: column;
            }
            
            .group-actions .btn {
                flex: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Shareman V4.5</h1>
            <div class="user-info">
                <span class="status-badge status-<?= getUserStatus() ?>">
                    <?= ucfirst(getUserStatus()) ?>
                </span>
                <span>Bienvenue, <?= htmlspecialchars($_SESSION['username']) ?></span>
				<a href="edit_profile.php" class="btn">Mon profil</a>
                <?php if(isAdmin()): ?>
                    <a href="admin.php" class="btn">Administration</a>
                <?php endif; ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-danger">Déconnexion</button>
                </form>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <?php if($showDeleteMessage): ?>
            <div class="success-message">Le groupe a été supprimé avec succès.</div>
        <?php endif; ?>
        
        <?php if($showRequestMessage): ?>
            <div class="info-message">
                Votre demande d'intégration a été envoyée. Elle sera examinée par un administrateur.
            </div>
        <?php endif; ?>
        
        <!-- NOUVEAU V4.5 : Filtre inversé - par défaut on montre les groupes membres -->
        <div class="filter-section">
            <label>
                <input type="checkbox" 
                       id="filter-toggle"
                       <?= $showAll ? 'checked' : '' ?>
                       onchange="toggleFilter(this.checked)">
                Afficher tous les groupes du site (y compris ceux où je ne suis pas membre)
            </label>
        </div>
        
        <!-- Header avec statistiques -->
        <div style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); padding: 1.5rem; border-radius: 16px; color: white; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin-bottom: 0.5rem;">
                        <?php if($showAll): ?>
                            Tous les groupes
                        <?php else: ?>
                            Mes groupes
                        <?php endif; ?>
                    </h2>
                    <p style="opacity: 0.9; font-size: 0.9rem;">
                        <?= $memberCount ?> groupe(s) dont vous êtes membre
                        <?php if($createdCount > 0): ?>
                            • <?= $createdCount ?> groupe(s) créé(s) par vous
                        <?php endif; ?>
                    </p>
                </div>
                <?php if(isAdmin()): ?>
                    <a href="create_group.php" class="btn btn-primary">+ Créer un groupe</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(empty($groups)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: rgba(255, 255, 255, 0.9); border-radius: 20px;">
                <h3 style="color: #374151; margin-bottom: 1rem;">Aucun groupe trouvé</h3>
                <p style="color: #6b7280;">
                    <?php if($showAll): ?>
                        Aucun groupe disponible sur le site.
                    <?php else: ?>
                        Vous n'êtes membre d'aucun groupe. Demandez l'accès à un groupe existant ou créez-en un nouveau.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php foreach($groups as $group): 
                    $isMember = isset($group['is_member']) && $group['is_member'] > 0;
                    $isCreator = isset($group['is_creator']) && $group['is_creator'] > 0;
                    $hasPendingRequest = !$isMember && $groupManager->hasPendingRequest($group['id'], $_SESSION['user_id']);
                ?>
                    <div class="group-card <?= !$isMember ? 'non-member' : '' ?>">
                        <h3>
                            <?= htmlspecialchars($group['name']) ?>
                            <?php if($isMember): ?>
                                <span class="membership-badge badge-member">Membre</span>
                            <?php elseif($hasPendingRequest): ?>
                                <span class="membership-badge badge-pending">Demande en attente</span>
                            <?php else: ?>
                                <span class="membership-badge badge-non-member">Non membre</span>
                            <?php endif; ?>
                            <?php if($isCreator): ?>
                                <span class="membership-badge" style="background: #dbeafe; color: #1e40af;">Créateur</span>
                            <?php endif; ?>
                        </h3>
                        <p><?= htmlspecialchars($group['description'] ?: 'Aucune description disponible') ?></p>
                        <div style="color: #9ca3af; font-size: 0.875rem; margin-bottom: 1rem;">
                            <span>Créé par <?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?></span>
                            <?php if(isset($group['member_count'])): ?>
                                <span> • <?= $group['member_count'] ?> membre(s)</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="group-actions">
                            <?php if($isMember): ?>
                                <!-- Actions pour les membres -->
                                <a href="group.php?id=<?= $group['id'] ?>" class="btn">Voir le groupe</a>
                                <?php if(isAdmin()): ?>
                                    <a href="edit_group.php?id=<?= $group['id'] ?>" class="btn" style="background: #6b7280;">Modifier</a>
                                    <a href="delete_group.php?id=<?= $group['id'] ?>" class="btn btn-danger" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe?')">Supprimer</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Actions pour les non-membres -->
                                <?php if($hasPendingRequest): ?>
                                    <button class="btn" disabled style="opacity: 0.6; cursor: not-allowed;">
                                        Demande en attente
                                    </button>
                                <?php else: ?>
                                    <a href="request_join.php?group_id=<?= $group['id'] ?>" 
                                       class="btn btn-warning"
                                       onclick="return confirm('Demander à rejoindre ce groupe?')">
                                        Demander l'accès
                                    </a>
                                <?php endif; ?>
                                <a href="group_preview.php?id=<?= $group['id'] ?>" class="btn" style="background: #6b7280;">
                                    Aperçu
                                </a>
                                <?php if(isAdmin()): ?>
                                    <!-- Admin peut modifier/supprimer même s'il n'est pas membre -->
                                    <a href="edit_group.php?id=<?= $group['id'] ?>" class="btn" style="background: #6b7280;">Modifier</a>
                                    <a href="delete_group.php?id=<?= $group['id'] ?>" class="btn btn-danger" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe?')">Supprimer</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleFilter(checked) {
            const url = new URL(window.location);
            if (checked) {
                // Checkbox cochée = montrer tous les groupes
                url.searchParams.set('show_all', '1');
            } else {
                // Checkbox décochée = montrer uniquement mes groupes (comportement par défaut V4.5)
                url.searchParams.delete('show_all');
            }
            window.location = url.toString();
        }
    </script>
</body>
</html>