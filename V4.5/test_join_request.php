<?php
// test_join_request.php
// Script de test pour diagnostiquer les problèmes d'approbation de demandes
require_once 'auth.php';
requireAdmin(); // Seuls les admins peuvent utiliser ce script
require_once 'classes/Group.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);
$userManager = new User($db);

$requestId = $_GET['request_id'] ?? 0;

if(!$requestId) {
    die("Paramètre request_id manquant");
}

// Récupérer la demande
$query = "SELECT jr.*, u.username, u.email, u.status as user_status, g.name as group_name 
          FROM group_join_requests jr
          JOIN users u ON jr.user_id = u.id
          JOIN groups_table g ON jr.group_id = g.id
          WHERE jr.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$request) {
    die("Demande introuvable");
}

// Diagnostics
$checks = [];

// Check 1: Statut de la demande
$checks[] = [
    'label' => 'Statut de la demande',
    'value' => $request['status'],
    'ok' => $request['status'] === 'pending',
    'message' => $request['status'] === 'pending' ? 'La demande est en attente' : 'La demande a déjà été traitée'
];

// Check 2: Utilisateur déjà membre ?
$isAlreadyMember = $groupManager->isUserInGroup($request['group_id'], $request['user_id']);
$checks[] = [
    'label' => 'Utilisateur déjà membre',
    'value' => $isAlreadyMember ? 'OUI' : 'NON',
    'ok' => !$isAlreadyMember,
    'message' => $isAlreadyMember ? 'L\'utilisateur est déjà membre du groupe' : 'L\'utilisateur n\'est pas encore membre'
];

// Check 3: Nom disponible ?
$checkQuery = "SELECT id, user_id, member_name FROM group_members 
              WHERE group_id = ? AND member_name = ?";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->execute([$request['group_id'], $request['username']]);
$existingMember = $checkStmt->fetch(PDO::FETCH_ASSOC);

if($existingMember) {
    if($existingMember['user_id'] === null) {
        // Membre non-lié → Sera lié au compte
        $checks[] = [
            'label' => 'Conflit de nom détecté',
            'value' => 'Membre non-lié existant',
            'ok' => true,
            'message' => '✓ Le nom "' . $request['username'] . '" existe comme membre non-lié. Le compte sera lié automatiquement.'
        ];
    } else {
        // Membre déjà lié → BLOQUÉ
        $checks[] = [
            'label' => 'Conflit de nom BLOQUANT',
            'value' => 'Membre déjà lié',
            'ok' => false,
            'message' => '✗ Le nom "' . $request['username'] . '" est déjà utilisé par un membre lié à un compte. La demande sera REJETÉE.'
        ];
    }
} else {
    // Nom disponible
    $checks[] = [
        'label' => 'Nom disponible',
        'value' => 'OUI',
        'ok' => true,
        'message' => 'Le nom est disponible, le membre sera ajouté normalement'
    ];
}

// Check 4: Groupe existe ?
$group = $groupManager->getGroupById($request['group_id']);
$checks[] = [
    'label' => 'Groupe valide',
    'value' => $group ? 'OUI' : 'NON',
    'ok' => (bool)$group,
    'message' => $group ? 'Le groupe existe' : 'ERREUR: Le groupe n\'existe pas'
];

// Check 5: Utilisateur valide ?
$user = $userManager->getUserById($request['user_id']);
$checks[] = [
    'label' => 'Utilisateur valide',
    'value' => $user ? 'OUI' : 'NON',
    'ok' => (bool)$user,
    'message' => $user ? 'L\'utilisateur existe' : 'ERREUR: L\'utilisateur n\'existe pas'
];

// Action de test
$testResult = null;
if($_POST && $_POST['action'] === 'simulate') {
    $testResult = $groupManager->approveJoinRequest($requestId);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Approbation Demande #<?= $requestId ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            background: #f9fafb;
        }
        h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .info-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .check-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border-left: 4px solid #e5e7eb;
        }
        .check-item.ok {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .check-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .check-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .check-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .check-value {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 1rem;
            margin-right: 0.5rem;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .result-box {
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            font-weight: 600;
        }
        .result-success {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
        }
        .result-error {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <h1>🔬 Test Approbation - Demande #<?= $requestId ?></h1>
    
    <div class="info-box">
        <h2 style="margin-top: 0;">Informations de la demande</h2>
        <div class="info-row">
            <span><strong>Utilisateur :</strong></span>
            <span><?= htmlspecialchars($request['username']) ?> (<?= htmlspecialchars($request['email']) ?>)</span>
        </div>
        <div class="info-row">
            <span><strong>Statut utilisateur :</strong></span>
            <span><?= htmlspecialchars($request['user_status']) ?></span>
        </div>
        <div class="info-row">
            <span><strong>Groupe demandé :</strong></span>
            <span><?= htmlspecialchars($request['group_name']) ?></span>
        </div>
        <div class="info-row">
            <span><strong>Date de demande :</strong></span>
            <span><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span><strong>Statut :</strong></span>
            <span><strong><?= strtoupper($request['status']) ?></strong></span>
        </div>
    </div>
    
    <div class="info-box">
        <h2 style="margin-top: 0;">Vérifications préalables</h2>
        
        <?php foreach($checks as $check): ?>
            <div class="check-item <?= $check['ok'] ? 'ok' : ($check['value'] === 'ERREUR' ? 'error' : 'warning') ?>">
                <div class="check-label">
                    <?= $check['ok'] ? '✓' : '⚠' ?> <?= htmlspecialchars($check['label']) ?>
                </div>
                <div class="check-value">
                    <strong>Valeur:</strong> <?= htmlspecialchars($check['value']) ?><br>
                    <strong>Info:</strong> <?= htmlspecialchars($check['message']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if($testResult): ?>
        <div class="result-box <?= $testResult['status'] === 'success' ? 'result-success' : 'result-error' ?>">
            <?= $testResult['status'] === 'success' ? '✓' : '✗' ?> 
            <?= htmlspecialchars($testResult['message']) ?>
        </div>
    <?php endif; ?>
    
    <div class="info-box">
        <h2 style="margin-top: 0;">Actions</h2>
        
        <?php if($request['status'] === 'pending'): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="simulate">
                <button type="submit" class="btn btn-success" 
                        onclick="return confirm('Approuver cette demande ?')">
                    ✓ Approuver maintenant
                </button>
            </form>
            
            <a href="admin.php" class="btn btn-secondary">Annuler</a>
        <?php else: ?>
            <p>Cette demande a déjà été traitée (statut: <?= $request['status'] ?>)</p>
            <a href="admin.php" class="btn btn-secondary">Retour à l'admin</a>
        <?php endif; ?>
    </div>
    
    <div class="info-box" style="background: #f3f4f6;">
        <h3 style="margin-top: 0;">Membres actuels du groupe</h3>
        <?php 
        $members = $groupManager->getGroupMembers($request['group_id']);
        if(empty($members)): ?>
            <p>Aucun membre dans ce groupe</p>
        <?php else: ?>
            <ul>
                <?php foreach($members as $member): ?>
                    <li>
                        <strong><?= htmlspecialchars($member['member_name']) ?></strong>
                        <?= $member['user_id'] ? ' (lié au compte)' : ' (non lié)' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    
    <a href="admin.php" class="btn btn-primary">← Retour à l'administration</a>
</body>
</html>