<?php
// check_member_included.php
// Vérifier si un membre spécifique est bien pris en compte dans le mode séjour
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';
require_once 'classes/Expense.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);
$expenseManager = new Expense($db);

$groupId = $_GET['group_id'] ?? 0;
$memberName = $_GET['member_name'] ?? '';

$group = $groupManager->getGroupById($groupId);

if(!$group || !$group['stay_mode_enabled']) {
    die("Erreur : Groupe introuvable ou mode séjour non activé");
}

if(empty($memberName)) {
    die("Erreur : Nom du membre requis (paramètre member_name)");
}

// Vérifier si le membre existe dans le groupe
$members = $groupManager->getGroupMembers($groupId);
$memberExists = false;
foreach($members as $m) {
    if($m['member_name'] === $memberName) {
        $memberExists = true;
        break;
    }
}

if(!$memberExists) {
    die("Erreur : Le membre '$memberName' n'existe pas dans ce groupe");
}

// Récupérer la période de séjour du membre
$stayPeriods = $groupManager->getMemberStayPeriods($groupId);
$memberPeriod = null;
foreach($stayPeriods as $period) {
    if($period['member_name'] === $memberName) {
        $memberPeriod = $period;
        break;
    }
}

// Calculer les bilans
$stayBalances = $expenseManager->calculateStayBalances($groupId);
$memberBalance = $stayBalances[$memberName] ?? null;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérification : <?= htmlspecialchars($memberName) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
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
        .status-box {
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .status-ok {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
        }
        .status-error {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }
        .status-warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            color: #92400e;
        }
        .info-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #374151;
        }
        .info-value {
            color: #1f2937;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
        }
        ul {
            margin: 1rem 0;
            padding-left: 2rem;
        }
        li {
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Vérification : <?= htmlspecialchars($memberName) ?></h1>
    
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Groupe :</span>
            <span class="info-value"><?= htmlspecialchars($group['name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Période du séjour :</span>
            <span class="info-value">
                <?= date('d/m/Y', strtotime($group['stay_start_date'])) ?> 
                au 
                <?= date('d/m/Y', strtotime($group['stay_end_date'])) ?>
            </span>
        </div>
    </div>
    
    <!-- Test 1 : Membre existe -->
    <div class="status-box status-ok">
        ✓ Le membre existe dans le groupe
    </div>
    
    <!-- Test 2 : Période de séjour -->
    <?php if($memberPeriod): ?>
        <div class="status-box status-ok">
            ✓ Le membre a une période de séjour
        </div>
        
        <div class="info-box">
            <h3 style="margin-top: 0; color: #1f2937;">Période de séjour de <?= htmlspecialchars($memberName) ?></h3>
            <div class="info-row">
                <span class="info-label">Date début :</span>
                <span class="info-value"><?= date('d/m/Y', strtotime($memberPeriod['start_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date fin :</span>
                <span class="info-value"><?= date('d/m/Y', strtotime($memberPeriod['end_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Nombre de jours :</span>
                <span class="info-value">
                    <?php 
                    $start = new DateTime($memberPeriod['start_date']);
                    $end = new DateTime($memberPeriod['end_date']);
                    $days = $end->diff($start)->days + 1;
                    echo $days . " jours";
                    ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Coefficient :</span>
                <span class="info-value"><?= $memberPeriod['coefficient'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Jours pondérés :</span>
                <span class="info-value" style="font-weight: bold; color: #667eea;">
                    <?= number_format($days * $memberPeriod['coefficient'], 2) ?>
                </span>
            </div>
        </div>
    <?php else: ?>
        <div class="status-box status-error">
            ✗ PROBLÈME : Le membre n'a PAS de période de séjour
        </div>
        
        <div class="info-box">
            <h3 style="color: #991b1b;">Action requise :</h3>
            <ol>
                <li>Aller sur <a href="fix_missing_stay_periods.php">fix_missing_stay_periods.php</a> pour créer la période automatiquement</li>
                <li>OU aller dans le groupe → Mode Séjour → Ajouter manuellement la période</li>
            </ol>
        </div>
    <?php endif; ?>
    
    <!-- Test 3 : Bilan calculé -->
    <?php if($memberBalance !== null): ?>
        <div class="status-box status-ok">
            ✓ Le membre apparaît dans les bilans calculés
        </div>
        
        <div class="info-box">
            <h3 style="margin-top: 0; color: #1f2937;">Bilan de <?= htmlspecialchars($memberName) ?></h3>
            <div class="info-row">
                <span class="info-label">Bilan actuel :</span>
                <span class="info-value" style="font-size: 1.5rem; font-weight: bold; 
                      color: <?= $memberBalance > 0 ? '#059669' : ($memberBalance < 0 ? '#dc2626' : '#6b7280') ?>">
                    <?= $memberBalance > 0 ? '+' : '' ?><?= number_format($memberBalance, 2) ?> €
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Statut :</span>
                <span class="info-value">
                    <?php if($memberBalance > 0.01): ?>
                        <strong style="color: #059669;">À recevoir</strong>
                    <?php elseif($memberBalance < -0.01): ?>
                        <strong style="color: #dc2626;">À payer</strong>
                    <?php else: ?>
                        <strong style="color: #6b7280;">Équilibré</strong>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php else: ?>
        <div class="status-box status-error">
            ✗ PROBLÈME : Le membre n'apparaît PAS dans les bilans
        </div>
        
        <div class="info-box">
            <h3 style="color: #991b1b;">Causes possibles :</h3>
            <ul>
                <li>Pas de période de séjour (voir ci-dessus)</li>
                <li>Erreur dans le calcul des bilans</li>
                <li>Fichier Expense.php pas à jour</li>
            </ul>
            <h3 style="color: #991b1b;">Actions :</h3>
            <ol>
                <li>Vérifier que le fichier classes/Expense.php est à jour</li>
                <li>Vérifier la méthode calculateStayBalances()</li>
                <li>Lancer le diagnostic complet : <a href="debug_stay_balances.php?id=<?= $groupId ?>">debug_stay_balances.php</a></li>
            </ol>
        </div>
    <?php endif; ?>
    
    <!-- Résumé final -->
    <?php 
    $allOk = $memberExists && $memberPeriod && ($memberBalance !== null);
    ?>
    
    <div class="status-box <?= $allOk ? 'status-ok' : 'status-error' ?>">
        <?php if($allOk): ?>
            <h2 style="margin: 0;">✓ TOUT EST OK !</h2>
            <p style="margin: 0.5rem 0 0 0;">
                <?= htmlspecialchars($memberName) ?> est bien pris en compte dans le mode séjour.
                <?php if($memberBalance > 0.01): ?>
                    Il/Elle doit recevoir <?= number_format($memberBalance, 2) ?> €.
                <?php elseif($memberBalance < -0.01): ?>
                    Il/Elle doit payer <?= number_format(abs($memberBalance), 2) ?> €.
                <?php else: ?>
                    Son compte est équilibré.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <h2 style="margin: 0;">✗ PROBLÈME DÉTECTÉ</h2>
            <p style="margin: 0.5rem 0 0 0;">
                <?= htmlspecialchars($memberName) ?> n'est PAS correctement pris en compte.
                Suivez les actions indiquées ci-dessus.
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Détails techniques -->
    <div class="info-box" style="background: #f3f4f6;">
        <h3 style="margin-top: 0; color: #374151;">Informations techniques</h3>
        <div class="info-row">
            <span class="info-label">Nombre total de membres :</span>
            <span class="info-value"><?= count($members) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Membres avec période :</span>
            <span class="info-value"><?= count($stayPeriods) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Membres dans les bilans :</span>
            <span class="info-value"><?= count($stayBalances) ?></span>
        </div>
        <?php 
        $stayExpenses = $expenseManager->getGroupExpensesByMode($groupId, 'sejour');
        $totalExpenses = 0;
        foreach($stayExpenses as $e) {
            $totalExpenses += $e['amount'];
        }
        ?>
        <div class="info-row">
            <span class="info-label">Dépenses en mode séjour :</span>
            <span class="info-value"><?= count($stayExpenses) ?> (<?= number_format($totalExpenses, 2) ?> €)</span>
        </div>
    </div>
    
    <a href="group.php?id=<?= $groupId ?>" class="btn">← Retour au groupe</a>
    <a href="debug_stay_balances.php?id=<?= $groupId ?>" class="btn" style="background: #10b981;">Diagnostic complet</a>
</body>
</html>