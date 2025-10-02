<?php
// request_join.php - Gestion des demandes d'intégration aux groupes
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);

$groupId = $_GET['group_id'] ?? 0;
$group = $groupManager->getGroupById($groupId);

if(!$group) {
    header('Location: dashboard.php');
    exit;
}

// Traiter la demande
if($_POST && $_POST['action'] === 'send_request') {
    $result = $groupManager->createJoinRequest($groupId, $_SESSION['user_id']);
    
    if($result['status'] === 'success') {
        header('Location: dashboard.php?request_sent=1');
        exit;
    } else {
        $error = $result['message'];
    }
}

// Vérifier si l'utilisateur est déjà membre
if($groupManager->isUserInGroup($groupId, $_SESSION['user_id'])) {
    header('Location: group.php?id=' . $groupId);
    exit;
}

// Vérifier si une demande existe déjà
$hasPendingRequest = $groupManager->hasPendingRequest($groupId, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande d'intégration - Shareman</title>
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
        
        .card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2.5rem; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .card-header h2 {
            color: #1f2937;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .card-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .group-info {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .group-info h3 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        
        .group-info p {
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .info-box {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #1e40af;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #92400e;
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid #fecaca;
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
            margin-bottom: 1rem;
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
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }
            
            .card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Demande d'intégration</h1>
            <div>
                <a href="dashboard.php" class="btn">← Retour au dashboard</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Rejoindre un groupe</h2>
                <p>Demandez l'accès au groupe</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if($hasPendingRequest): ?>
                <div class="warning-box">
                    <strong>⏳ Demande en attente</strong><br>
                    Vous avez déjà envoyé une demande pour rejoindre ce groupe. 
                    Un administrateur l'examinera prochainement.
                </div>
                <a href="dashboard.php" class="btn-secondary">Retour au dashboard</a>
            <?php else: ?>
                <div class="group-info">
                    <h3><?= htmlspecialchars($group['name']) ?></h3>
                    <p><strong>Description :</strong> <?= htmlspecialchars($group['description'] ?: 'Aucune description') ?></p>
                    <p><strong>Créé par :</strong> <?= htmlspecialchars($group['creator_name'] ?: 'Inconnu') ?></p>
                </div>
                
                <div class="info-box">
                    <strong>ℹ️ À savoir :</strong><br>
                    Votre demande sera examinée par un administrateur. 
                    <?php if(getUserStatus() === 'visiteur'): ?>
                        Si votre demande est acceptée, votre statut passera automatiquement à "Utilisateur".
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="send_request">
                    <button type="submit" class="btn-primary">
                        Envoyer la demande d'intégration
                    </button>
                </form>
                
                <a href="dashboard.php" class="btn-secondary">
                    Annuler
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>