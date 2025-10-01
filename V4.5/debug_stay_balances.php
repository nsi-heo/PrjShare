<?php
// debug_stay_balances.php
// Script de diagnostic pour les bilans en mode séjour
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';
require_once 'classes/Expense.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);
$expenseManager = new Expense($db);

$groupId = $_GET['id'] ?? 0;
$group = $groupManager->getGroupById($groupId);

if(!$group) {
    die("Groupe introuvable");
}

if(!$group['stay_mode_enabled']) {
    die("Le mode séjour n'est pas activé sur ce groupe");
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic Bilans Séjour - <?= htmlspecialchars($group['name']) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
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
        .section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #1f2937;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #e5e7eb;
        }
        th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
        .highlight {
            background: #fef3c7;
            font-weight: bold;
        }
        .success {
            color: #059669;
            font-weight: bold;
        }
        .error {
            color: #dc2626;
            font-weight: bold;
        }
        .formula {
            background: #ede9fe;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: monospace;
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
    </style>
</head>
<body>
    <h1>🔍 Diagnostic Bilans Séjour : <?= htmlspecialchars($group['name']) ?></h1>
    
    <!-- Informations du groupe -->
    <div class="section">
        <h2>📅 Informations du séjour</h2>
        <table>
            <tr>
                <th>Période du séjour</th>
                <td><?= date('d/m/Y', strtotime($group['stay_start_date'])) ?> au <?= date('d/m/Y', strtotime($group['stay_end_date'])) ?></td>
            </tr>
            <tr>
                <th>Durée totale</th>
                <td>
                    <?php 
                    $groupStart = new DateTime($group['stay_start_date']);
                    $groupEnd = new DateTime($group['stay_end_date']);
                    $totalDays = $groupEnd->diff($groupStart)->days + 1;
                    echo $totalDays . " jours";
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Périodes des membres -->
    <div class="section">
        <h2>👥 Périodes de séjour des membres</h2>
        <?php
        $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
        $memberWeightedDays = [];
        $totalWeightedDays = 0;
        ?>
        
        <table>
            <tr>
                <th>Membre</th>
                <th>Date début</th>
                <th>Date fin</th>
                <th>Nb jours</th>
                <th>Coefficient</th>
                <th>Jours pondérés</th>
            </tr>
            <?php foreach($stayPeriods as $period): 
                $memberStart = new DateTime($period['start_date']);
                $memberEnd = new DateTime($period['end_date']);
                $memberDays = $memberEnd->diff($memberStart)->days + 1;
                $weightedDays = $memberDays * $period['coefficient'];
                $memberWeightedDays[$period['member_name']] = $weightedDays;
                $totalWeightedDays += $weightedDays;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($period['member_name']) ?></strong></td>
                <td><?= date('d/m/Y', strtotime($period['start_date'])) ?></td>
                <td><?= date('d/m/Y', strtotime($period['end_date'])) ?></td>
                <td><?= $memberDays ?></td>
                <td><?= $period['coefficient'] ?></td>
                <td class="highlight"><?= number_format($weightedDays, 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="highlight">
                <td colspan="5"><strong>TOTAL jours pondérés</strong></td>
                <td><strong><?= number_format($totalWeightedDays, 2) ?></strong></td>
            </tr>
        </table>
        
        <div class="formula">
            <strong>Formule :</strong> Jours pondérés = Nb jours × Coefficient<br>
            <strong>Total :</strong> <?= number_format($totalWeightedDays, 2) ?> jours pondérés
        </div>
    </div>
    
    <!-- Dépenses en mode séjour -->
    <div class="section">
        <h2>💰 Dépenses en mode séjour</h2>
        <?php
        $stayExpenses = $expenseManager->getGroupExpensesByMode($groupId, 'sejour');
        $totalExpenses = 0;
        ?>
        
        <?php if(empty($stayExpenses)): ?>
            <p class="error">Aucune dépense en mode séjour trouvée</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Description</th>
                    <th>Montant</th>
                    <th>Payé par</th>
                    <th>Date</th>
                </tr>
                <?php foreach($stayExpenses as $expense): 
                    $totalExpenses += $expense['amount'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($expense['title']) ?></td>
                    <td><?= number_format($expense['amount'], 2) ?> €</td>
                    <td><?= htmlspecialchars($expense['paid_by']) ?></td>
                    <td><?= date('d/m/Y', strtotime($expense['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="highlight">
                    <td><strong>TOTAL</strong></td>
                    <td colspan="3"><strong><?= number_format($totalExpenses, 2) ?> €</strong></td>
                </tr>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Calcul des parts -->
    <div class="section">
        <h2>🧮 Calcul des parts de chaque membre</h2>
        
        <div class="formula">
            <strong>Formule :</strong> Part du membre = (Jours pondérés du membre / Total jours pondérés) × Total dépenses<br>
            <strong>Total dépenses :</strong> <?= number_format($totalExpenses, 2) ?> €<br>
            <strong>Total jours pondérés :</strong> <?= number_format($totalWeightedDays, 2) ?>
        </div>
        
        <table>
            <tr>
                <th>Membre</th>
                <th>Jours pondérés</th>
                <th>Pourcentage</th>
                <th>Part à payer</th>
            </tr>
            <?php 
            $memberShares = [];
            foreach($memberWeightedDays as $memberName => $weightedDays): 
                $percentage = ($weightedDays / $totalWeightedDays) * 100;
                $share = ($weightedDays / $totalWeightedDays) * $totalExpenses;
                $memberShares[$memberName] = $share;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($memberName) ?></strong></td>
                <td><?= number_format($weightedDays, 2) ?></td>
                <td><?= number_format($percentage, 2) ?>%</td>
                <td class="highlight"><?= number_format($share, 2) ?> €</td>
            </tr>
            <?php endforeach; ?>
            <tr class="highlight">
                <td colspan="3"><strong>TOTAL</strong></td>
                <td><strong><?= number_format(array_sum($memberShares), 2) ?> €</strong></td>
            </tr>
        </table>
        
        <?php if(abs(array_sum($memberShares) - $totalExpenses) > 0.01): ?>
            <p class="error">⚠️ ATTENTION : Le total des parts (<?= number_format(array_sum($memberShares), 2) ?> €) 
            ne correspond pas au total des dépenses (<?= number_format($totalExpenses, 2) ?> €)</p>
        <?php else: ?>
            <p class="success">✓ Vérification OK : Le total des parts correspond au total des dépenses</p>
        <?php endif; ?>
    </div>
    
    <!-- Paiements effectués -->
    <div class="section">
        <h2>💳 Paiements effectués</h2>
        
        <table>
            <tr>
                <th>Membre</th>
                <th>Montant payé</th>
            </tr>
            <?php 
            $memberPayments = [];
            foreach($stayExpenses as $expense) {
                if(!isset($memberPayments[$expense['paid_by']])) {
                    $memberPayments[$expense['paid_by']] = 0;
                }
                $memberPayments[$expense['paid_by']] += $expense['amount'];
            }
            
            // Ajouter les membres qui n'ont rien payé
            foreach($memberWeightedDays as $memberName => $dummy) {
                if(!isset($memberPayments[$memberName])) {
                    $memberPayments[$memberName] = 0;
                }
            }
            
            foreach($memberPayments as $memberName => $paid): ?>
            <tr>
                <td><strong><?= htmlspecialchars($memberName) ?></strong></td>
                <td><?= number_format($paid, 2) ?> €</td>
            </tr>
            <?php endforeach; ?>
            <tr class="highlight">
                <td><strong>TOTAL</strong></td>
                <td><strong><?= number_format(array_sum($memberPayments), 2) ?> €</strong></td>
            </tr>
        </table>
    </div>
    
    <!-- Bilans finaux -->
    <div class="section">
        <h2>📊 Bilans finaux</h2>
        
        <div class="formula">
            <strong>Formule :</strong> Bilan = Montant payé - Part à payer<br>
            Si positif : à recevoir | Si négatif : à payer
        </div>
        
        <table>
            <tr>
                <th>Membre</th>
                <th>Montant payé (A)</th>
                <th>Part à payer (B)</th>
                <th>Bilan (A - B)</th>
                <th>Statut</th>
            </tr>
            <?php 
            $balances = [];
            foreach($memberWeightedDays as $memberName => $dummy):
                $paid = $memberPayments[$memberName] ?? 0;
                $toPay = $memberShares[$memberName] ?? 0;
                $balance = $paid - $toPay;
                $balances[$memberName] = $balance;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($memberName) ?></strong></td>
                <td><?= number_format($paid, 2) ?> €</td>
                <td><?= number_format($toPay, 2) ?> €</td>
                <td class="highlight <?= $balance > 0 ? 'success' : 'error' ?>">
                    <?= $balance > 0 ? '+' : '' ?><?= number_format($balance, 2) ?> €
                </td>
                <td>
                    <?php if($balance > 0.01): ?>
                        <span class="success">À recevoir</span>
                    <?php elseif($balance < -0.01): ?>
                        <span class="error">À payer</span>
                    <?php else: ?>
                        Équilibré
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="highlight">
                <td colspan="3"><strong>TOTAL</strong></td>
                <td><strong><?= number_format(array_sum($balances), 2) ?> €</strong></td>
                <td></td>
            </tr>
        </table>
        
        <?php if(abs(array_sum($balances)) > 0.01): ?>
            <p class="error">⚠️ ATTENTION : Le total des bilans (<?= number_format(array_sum($balances), 2) ?> €) 
            devrait être 0. Il y a une erreur de calcul.</p>
        <?php else: ?>
            <p class="success">✓ Vérification OK : Le total des bilans est équilibré (somme = 0)</p>
        <?php endif; ?>
    </div>
    
    <!-- Comparaison avec le système -->
    <div class="section">
        <h2>🔄 Comparaison avec les bilans du système</h2>
        
        <?php $systemBalances = $expenseManager->calculateStayBalances($groupId); ?>
        
        <table>
            <tr>
                <th>Membre</th>
                <th>Bilan calculé ici</th>
                <th>Bilan système</th>
                <th>Différence</th>
            </tr>
            <?php foreach($balances as $memberName => $calculatedBalance): 
                $systemBalance = $systemBalances[$memberName] ?? 0;
                $diff = abs($calculatedBalance - $systemBalance);
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($memberName) ?></strong></td>
                <td><?= number_format($calculatedBalance, 2) ?> €</td>
                <td><?= number_format($systemBalance, 2) ?> €</td>
                <td class="<?= $diff > 0.01 ? 'error' : 'success' ?>">
                    <?= $diff > 0.01 ? '⚠️ ' : '✓ ' ?><?= number_format($diff, 2) ?> €
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <?php 
        $hasErrors = false;
        foreach($balances as $memberName => $calculatedBalance) {
            $systemBalance = $systemBalances[$memberName] ?? 0;
            if(abs($calculatedBalance - $systemBalance) > 0.01) {
                $hasErrors = true;
                break;
            }
        }
        ?>
        
        <?php if($hasErrors): ?>
            <p class="error">⚠️ Des différences ont été détectées entre les calculs. Vérifiez le code de calculateStayBalances().</p>
        <?php else: ?>
            <p class="success">✓ Les bilans du système correspondent aux calculs manuels. Tout est OK!</p>
        <?php endif; ?>
    </div>
    
    <a href="group.php?id=<?= $groupId ?>" class="btn">← Retour au groupe</a>
</body>
</html>