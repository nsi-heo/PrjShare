<?php
// dashboard.php - Tableau de bord principal (version améliorée)
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);

$groups = $groupManager->getAllGroups();

// Message de confirmation de suppression
$showDeleteMessage = isset($_GET['deleted']) && $_GET['deleted'] == '1';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Shareman</title>
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
            background: linear-gradient(45deg, #fff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
        
        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
            color: white;
        }
        
        .welcome-section h2 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }
        
        .welcome-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            color: white;
        }
        
        .header h3 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.8rem 1.6rem;
            font-size: 1rem;
            border: none;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        .success-message {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid #6ee7b7;
            font-weight: 500;
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
        
        .group-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #9ca3af;
        }
        
        .group-meta svg {
            width: 16px;
            height: 16px;
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
        
        .group-actions .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .group-actions .btn-secondary {
            background: #6b7280;
        }
        
        .group-actions .btn-secondary:hover {
            background: #4b5563;
        }
        
        .group-actions .btn-danger {
            background: #ef4444;
            border-color: #ef4444;
        }
        
        .group-actions .btn-danger:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 2px dashed #d1d5db;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #6b7280;
            margin-bottom: 2rem;
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
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            opacity: 0.8;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .user-info {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .container {
                padding: 1rem 0.5rem;
            }
            
            .welcome-section h2 {
                font-size: 1.8rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .group-card {
                padding: 1.5rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Shareman</h1>
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
                    <button type="submit" class="btn btn-danger">Déconnexion</button>
                </form>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-section">
            <h2>Tableau de bord</h2>
            <p>Gérez vos groupes et vos dépenses partagées</p>
        </div>
        
        <?php if($showDeleteMessage): ?>
            <div class="success-message">
                Le groupe a été supprimé avec succès.
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($groups) ?></div>
                <div class="stat-label">Groupes total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= getUserStatus() === 'administrateur' ? 'Admin' : 'User' ?></div>
                <div class="stat-label">Votre statut</div>
            </div>
        </div>
        
        <div class="header">
            <h3>Mes groupes</h3>
            <?php if(isAdmin()): ?>
                <a href="create_group.php" class="btn btn-primary">+ Créer un groupe</a>
            <?php endif; ?>
        </div>
        
        <?php if(empty($groups)): ?>
            <div class="empty-state">
                <h3>Aucun groupe trouvé</h3>
                <p>Commencez par créer votre premier groupe de dépenses partagées!</p>
                <?php if(isAdmin()): ?>
                    <a href="create_group.php" class="btn btn-primary">Créer mon premier groupe</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php foreach($groups as $group): ?>
                    <div class="group-card">
                        <h3><?= htmlspecialchars($group['name']) ?></h3>
                        <p><?= htmlspecialchars($group['description'] ?: 'Aucune description disponible') ?></p>
                        <div class="group-meta">
                            <span>👤 Créé par <?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?></span>
                        </div>
                        
                        <div class="group-actions">
                            <a href="group.php?id=<?= $group['id'] ?>" class="btn">Voir le groupe</a>
                            <?php if(isAdmin()): ?>
                                <a href="edit_group.php?id=<?= $group['id'] ?>" class="btn btn-secondary">Modifier</a>
                                <a href="delete_group.php?id=<?= $group['id'] ?>" class="btn btn-danger" 
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe?')">Supprimer</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>