<?php
// group.php - Version avec mode séjour
require_once 'auth.php';
requireAuth();
require_once 'classes/Group.php';
require_once 'classes/Expense.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$groupManager = new Group($db);
$expenseManager = new Expense($db);
$userManager = new User($db);

$groupId = $_GET['id'] ?? 0;
$group = $groupManager->getGroupById($groupId);

if(!$group) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$showLinkDialog = false;
$conflictData = [];

// Traitement des formulaires
if($_POST) {
    $action = $_POST['action'] ?? '';
    
    // Configuration du mode séjour
    if($action === 'configure_stay_mode' && getUserStatus() !== 'visiteur') {
        $startDate = $_POST['stay_start_date'] ?? '';
        $endDate = $_POST['stay_end_date'] ?? '';
        
        if (empty($startDate) || empty($endDate)) {
            $error = "Les dates de début et de fin sont requises";
        } else {
            $result = $groupManager->configureStayMode($groupId, $startDate, $endDate);
            if ($result['status'] === 'success') {
                $success = "Mode séjour activé avec succès ! Tous les membres ont une période par défaut.";
                $group = $groupManager->getGroupById($groupId); // Recharger les données
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Désactiver le mode séjour
    if($action === 'disable_stay_mode' && getUserStatus() !== 'visiteur') {
        $result = $groupManager->disableStayMode($groupId);
        if ($result['status'] === 'success') {
            $success = "Mode séjour désactivé. Toutes les dépenses sont maintenant en mode classique.";
            $group = $groupManager->getGroupById($groupId); // Recharger les données
        } else {
            $error = $result['message'];
        }
    }
    
    // Mise à jour d'une période de séjour
    if($action === 'update_stay_period' && getUserStatus() !== 'visiteur') {
        $memberName = $_POST['member_name'] ?? '';
        $startDate = $_POST['member_start_date'] ?? '';
        $endDate = $_POST['member_end_date'] ?? '';
        $coefficient = floatval($_POST['coefficient'] ?? 1.0);
        
        if (empty($memberName) || empty($startDate) || empty($endDate)) {
            $error = "Tous les champs sont requis pour la période de séjour";
        } else {
            $result = $groupManager->updateMemberStayPeriod($groupId, $memberName, $startDate, $endDate, $coefficient);
            if ($result['status'] === 'success') {
                $success = "Période de séjour mise à jour pour " . htmlspecialchars($memberName);
            } else {
                $error = $result['message'];
            }
        }
    }
    
    if($action === 'add_expense' && getUserStatus() !== 'visiteur') {
        $title = $_POST['title'];
        $amount = floatval($_POST['amount']);
        $paidBy = $_POST['paid_by'];
        $participants = $_POST['participants'] ?? [];
        $expenseMode = $_POST['expense_mode'] ?? 'classique';
        
        if(!empty($participants)) {
            if($expenseManager->addExpense($groupId, $title, $amount, $paidBy, $_SESSION['user_id'], $participants, $expenseMode)) {
                $success = "Dépense ajoutée avec succès en mode " . $expenseMode . " !";
            } else {
                $error = "Erreur lors de l'ajout de la dépense";
            }
        } else {
            $error = "Veuillez sélectionner au moins un participant";
        }
    }
    
// Section à remplacer dans group.php - Traitement formulaire add_member

if($action === 'add_member' && getUserStatus() !== 'visiteur') {
    $memberType = $_POST['member_type'] ?? '';
    $memberName = '';
    $userId = null;
    
    if($memberType === 'existing_user') {
        $userId = $_POST['existing_user_id'];
        $userData = $userManager->getUserById($userId);
        
        if($userData) {
            $memberName = $userData['username'];
            
            $result = $groupManager->addMemberToGroupWithConflictCheck($groupId, $memberName, $userId);
            
            switch($result['status']) {
                case 'success':
                    $success = $result['message'];
                    
                    // IMPORTANT : Créer la période de séjour si mode activé
                    if($group['stay_mode_enabled']) {
                        $periodCreated = $groupManager->createDefaultStayPeriodForMember($groupId, $memberName);
                        if($periodCreated) {
                            $success .= " Une période de séjour par défaut a été créée du " . 
                                       date('d/m/Y', strtotime($group['stay_start_date'])) . 
                                       " au " . date('d/m/Y', strtotime($group['stay_end_date'])) . 
                                       " avec un coefficient de 1.00.";
                        } else {
                            $success .= " Attention : La période de séjour n'a pas pu être créée automatiquement. Veuillez la créer manuellement dans la section Mode Séjour.";
                        }
                    }
                    
                    // Recharger les données du groupe pour afficher les changements
                    $group = $groupManager->getGroupById($groupId);
                    $members = $groupManager->getGroupMembers($groupId);
                    $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
                    $stayBalances = $expenseManager->calculateStayBalances($groupId);
                    $stayDebts = $expenseManager->calculateStayDebts($groupId);
                    break;
                    
                case 'error':
                    $error = $result['message'];
                    break;
                    
                case 'conflict':
                    $showLinkDialog = true;
                    $conflictData = [
                        'user_id' => $userId,
                        'username' => $memberName,
                        'existing_member' => $groupManager->getUnlinkedMemberByName($groupId, $memberName)
                    ];
                    break;
            }
        } else {
            $error = "Utilisateur introuvable";
        }
        
    } else if($memberType === 'new_member') {
        $memberName = trim($_POST['new_member_name']);
        
        if(empty($memberName)) {
            $error = "Le nom du membre est requis";
        } else {
            $result = $groupManager->addMemberToGroupWithConflictCheck($groupId, $memberName, null);
            
            if($result['status'] === 'success') {
                $success = $result['message'] . " Il pourra créer un compte avec ce nom plus tard.";
                
                // IMPORTANT : Créer la période de séjour si mode activé
                if($group['stay_mode_enabled']) {
                    $periodCreated = $groupManager->createDefaultStayPeriodForMember($groupId, $memberName);
                    if($periodCreated) {
                        $success .= " Une période de séjour par défaut a été créée du " . 
                                   date('d/m/Y', strtotime($group['stay_start_date'])) . 
                                   " au " . date('d/m/Y', strtotime($group['stay_end_date'])) . 
                                   " avec un coefficient de 1.00.";
                    } else {
                        $success .= " Attention : La période de séjour n'a pas pu être créée automatiquement. Veuillez la créer manuellement dans la section Mode Séjour.";
                    }
                }
                
                // Recharger les données du groupe pour afficher les changements
                $group = $groupManager->getGroupById($groupId);
                $members = $groupManager->getGroupMembers($groupId);
                $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
                $stayBalances = $expenseManager->calculateStayBalances($groupId);
                $stayDebts = $expenseManager->calculateStayDebts($groupId);
            } else {
                $error = $result['message'];
            }
        }
    }
}

// IMPORTANT : Action de liaison membre existant
if($action === 'link_member' && getUserStatus() !== 'visiteur') {
    $userId = $_POST['user_id'];
    $memberName = $_POST['member_name'];
    
    if($groupManager->linkMemberToUser($groupId, $memberName, $userId)) {
        $success = "Le membre existant a été lié au compte utilisateur avec succès !";
        
        // Vérifier si période de séjour existe, sinon la créer
        if($group['stay_mode_enabled']) {
            $periodCreated = $groupManager->createDefaultStayPeriodForMember($groupId, $memberName);
            if($periodCreated) {
                $success .= " Une période de séjour a été créée automatiquement.";
            }
        }
        
        // Recharger les données
        $group = $groupManager->getGroupById($groupId);
        $members = $groupManager->getGroupMembers($groupId);
        $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
        $stayBalances = $expenseManager->calculateStayBalances($groupId);
        $stayDebts = $expenseManager->calculateStayDebts($groupId);
    } else {
        $error = "Erreur lors de la liaison du membre";
    }
}

// IMPORTANT : Action ajout comme nouveau membre (conflit)
if($action === 'add_as_new' && getUserStatus() !== 'visiteur') {
    $userId = $_POST['user_id'];
    $memberName = $_POST['member_name'];
    
    $newMemberName = $memberName . " (utilisateur)";
    $counter = 2;
    
    while($groupManager->isMemberNameInGroup($groupId, $newMemberName)) {
        $newMemberName = $memberName . " (utilisateur " . $counter . ")";
        $counter++;
    }
    
    if($groupManager->addMemberToGroup($groupId, $newMemberName, $userId)) {
        $success = "L'utilisateur a été ajouté avec le nom \"" . $newMemberName . "\" pour éviter la confusion.";
        
        // Créer période de séjour si nécessaire
        if($group['stay_mode_enabled']) {
            $periodCreated = $groupManager->createDefaultStayPeriodForMember($groupId, $newMemberName);
            if($periodCreated) {
                $success .= " Une période de séjour a été créée automatiquement.";
            }
        }
        
        // Recharger les données
        $group = $groupManager->getGroupById($groupId);
        $members = $groupManager->getGroupMembers($groupId);
        $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
        $stayBalances = $expenseManager->calculateStayBalances($groupId);
        $stayDebts = $expenseManager->calculateStayDebts($groupId);
    } else {
        $error = "Erreur lors de l'ajout du membre";
    }
}
    
    if($action === 'remove_member' && isAdmin()) {
        $memberId = $_POST['member_id'];
        if($groupManager->removeMemberFromGroup($memberId, $groupId)) {
            $success = "Membre retiré du groupe";
        } else {
            $error = "Erreur lors de la suppression du membre";
        }
    }
    
    if($action === 'edit_expense' && getUserStatus() !== 'visiteur') {
        $expenseId = $_POST['expense_id'];
        $title = $_POST['title'];
        $amount = floatval($_POST['amount']);
        $paidBy = $_POST['paid_by'];
        $participants = $_POST['participants'] ?? [];
        $expenseMode = $_POST['expense_mode'] ?? 'classique';
        
        if(!isAdmin() && !$expenseManager->canUserModifyExpense($expenseId, $_SESSION['user_id'])) {
            $error = "Vous ne pouvez modifier que vos propres dépenses";
        } elseif(!empty($participants)) {
            if($expenseManager->updateExpenseWithModifier($expenseId, $title, $amount, $paidBy, $participants, $_SESSION['user_id'], $expenseMode)) {
                $success = "Dépense modifiée avec succès !";
            } else {
                $error = "Erreur lors de la modification de la dépense";
            }
        } else {
            $error = "Veuillez sélectionner au moins un participant";
        }
    }
    
    if($action === 'delete_expense' && getUserStatus() !== 'visiteur') {
        $expenseId = (int)$_POST['expense_id'];
        
        $canDelete = isAdmin() || $expenseManager->canUserModifyExpense($expenseId, $_SESSION['user_id']);
        
        if (!$canDelete) {
            $error = "Vous ne pouvez supprimer que vos propres dépenses";
        } else {
            if($expenseManager->deleteExpense($expenseId)) {
                $success = "Dépense supprimée avec succès !";
            } else {
                $error = "Erreur lors de la suppression de la dépense";
            }
        }
    }
}

$members = $groupManager->getGroupMembers($groupId);
$expenses = $expenseManager->getGroupExpenses($groupId);
$balances = $expenseManager->calculateBalances($groupId);
$debts = $expenseManager->calculateDebts($groupId);

// Données spécifiques au mode séjour
$stayPeriods = [];
$stayBalances = [];
$stayDebts = [];

if ($group['stay_mode_enabled']) {
    $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
    $stayBalances = $expenseManager->calculateStayBalances($groupId);
    $stayDebts = $expenseManager->calculateStayDebts($groupId);
}

$allUsers = $userManager->getAllUsers();
$availableUsers = array_filter($allUsers, function($user) use ($groupManager, $groupId) {
    return !$groupManager->isUserInGroup($groupId, $user['id']);
});
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> - Shareman</title>
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
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn:hover { 
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal h3 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .modal p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #374151; 
            font-weight: 600; 
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus, 
        .form-group select:focus { 
            outline: none; 
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr;
            gap: 1rem; 
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .member-card {
            background: #f9fafb;
            padding: 1.25rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .member-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .member-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .member-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-linked {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-unlinked {
            background: #fef3c7;
            color: #92400e;
        }
        
        .radio-group {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal;
            cursor: pointer;
        }
        
        .radio-group input[type="radio"] {
            width: auto;
        }
        
        .conditional-field {
            display: none;
        }
        
        .conditional-field.show {
            display: block;
        }
        
        .checkbox-group { 
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .checkbox-group label { 
            display: flex; 
            align-items: center;
            margin-bottom: 0;
            font-weight: normal;
        }
        
        .checkbox-group input[type="checkbox"] { 
            margin-right: 0.5rem; 
            width: auto; 
        }

        /* Styles pour le mode séjour */
        .stay-mode-section {
            border-left: 4px solid #10b981;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        }
        
        .stay-mode-disabled {
            background: #f9fafb;
            border: 2px dashed #d1d5db;
        }
        
        .stay-period-card {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .stay-period-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .expense-mode-toggle {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: #f3f4f6;
            padding: 0.5rem;
            border-radius: 8px;
        }
        
        .tab-btn {
            padding: 0.5rem 1rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tab-btn.active {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .balance-mode-indicator {
            display: inline-block;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .mode-classique {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .mode-sejour {
            background: #d1fae5;
            color: #065f46;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .members-grid {
                grid-template-columns: 1fr;
            }
            
            .member-card {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .modal {
                width: 95%;
                padding: 1.5rem;
            }
            
            .modal-actions {
                flex-direction: column;
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stay-period-form {
                grid-template-columns: 1fr;
            }
            
            .tab-buttons {
                flex-direction: column;
            }
        }
		
        .expense-form {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .expenses-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .expense-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .expense-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .balances-grid .balance-card {
            transition: transform 0.2s;
        }
        
        .balances-grid .balance-card:hover {
            transform: translateY(-2px);
        }
        
        .debt-card {
            transition: transform 0.2s;
        }
        
        .debt-card:hover {
            transform: translateY(-2px);
        }
		
        .edit-expense-form {
            border-left: 4px solid #4f46e5;
        }
        
        .edit-expense-form input, 
        .edit-expense-form select {
            font-size: 0.9rem;
        }
        
        .edit-expense-form button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <!-- Modal de conflit de noms -->
    <?php if($showLinkDialog): ?>
    <div class="modal-overlay">
        <div class="modal">
            <h3>Conflit de nom détecté</h3>
            <div class="alert-warning" style="margin-bottom: 1rem;">
                Un membre non-lié avec le nom "<strong><?= htmlspecialchars($conflictData['username']) ?></strong>" 
                existe déjà dans ce groupe.
            </div>
            <p>Que souhaitez-vous faire ?</p>
            
            <div class="modal-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="link_member">
                    <input type="hidden" name="user_id" value="<?= $conflictData['user_id'] ?>">
                    <input type="hidden" name="member_name" value="<?= htmlspecialchars($conflictData['username']) ?>">
                    <button type="submit" class="btn-primary">
                        Lier au membre existant
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="add_as_new">
                    <input type="hidden" name="user_id" value="<?= $conflictData['user_id'] ?>">
                    <input type="hidden" name="member_name" value="<?= htmlspecialchars($conflictData['username']) ?>">
                    <button type="submit" class="btn-secondary">
                        Ajouter comme nouveau membre
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <nav class="navbar">
        <div class="navbar-content">
            <h1><?= htmlspecialchars($group['name']) ?></h1>
            <div>
                <a href="dashboard.php" class="btn">← Retour au tableau de bord</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <!-- Section Mode Séjour -->
        <?php if(getUserStatus() !== 'visiteur'): ?>
        <div class="section <?= $group['stay_mode_enabled'] ? 'stay-mode-section' : 'stay-mode-disabled' ?>">
            <h2>🏖️ Mode Séjour</h2>
            
            <?php if(!$group['stay_mode_enabled']): ?>
                <div class="alert alert-warning">
                    <strong>Mode séjour désactivé.</strong> Activez-le pour gérer les dépenses avec des coefficients de participation et des périodes personnalisées.
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="configure_stay_mode">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de début du séjour :</label>
                            <input type="date" name="stay_start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Date de fin du séjour :</label>
                            <input type="date" name="stay_end_date" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Activer le mode séjour</button>
                </form>
                
            <?php else: ?>
                <div class="alert-success" style="margin-bottom: 1.5rem;">
                    <strong>Mode séjour actif</strong> du <?= date('d/m/Y', strtotime($group['stay_start_date'])) ?> 
                    au <?= date('d/m/Y', strtotime($group['stay_end_date'])) ?>
                    
                    <form method="POST" style="display: inline; float: right;">
                        <input type="hidden" name="action" value="disable_stay_mode">
                        <button type="submit" class="btn-danger" onclick="return confirm('Désactiver le mode séjour ? Toutes les périodes seront supprimées.')">
                            Désactiver
                        </button>
                    </form>
                </div>
                
                <h3 style="margin-bottom: 1rem;">Périodes de séjour des membres</h3>
                
                <?php foreach($members as $member): ?>
                    <?php 
                    $memberPeriod = null;
                    foreach($stayPeriods as $period) {
                        if($period['member_name'] === $member['member_name']) {
                            $memberPeriod = $period;
                            break;
                        }
                    }
                    ?>
                    
                    <div class="stay-period-card">
                        <form method="POST" class="stay-period-form">
                            <input type="hidden" name="action" value="update_stay_period">
                            <input type="hidden" name="member_name" value="<?= htmlspecialchars($member['member_name']) ?>">
                            
                            <div>
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                    <?= htmlspecialchars($member['member_name']) ?>
                                </label>
                            </div>
                            
                            <div>
                                <label>Date début :</label>
                                <input type="date" 
                                       name="member_start_date" 
                                       value="<?= $memberPeriod ? $memberPeriod['start_date'] : $group['stay_start_date'] ?>"
                                       min="<?= $group['stay_start_date'] ?>"
                                       max="<?= $group['stay_end_date'] ?>"
                                       required>
                            </div>
                            
                            <div>
                                <label>Date fin :</label>
                                <input type="date" 
                                       name="member_end_date" 
                                       value="<?= $memberPeriod ? $memberPeriod['end_date'] : $group['stay_end_date'] ?>"
                                       min="<?= $group['stay_start_date'] ?>"
                                       max="<?= $group['stay_end_date'] ?>"
                                       required>
                            </div>
                            
                            <div>
                                <label>Coefficient :</label>
                                <input type="number" 
                                       name="coefficient" 
                                       step="0.1" 
                                       min="0.1" 
                                       max="10"
                                       value="<?= $memberPeriod ? $memberPeriod['coefficient'] : '1.0' ?>"
                                       required>
                            </div>
                            
                            <div>
                                <button type="submit" class="btn-primary" style="padding: 0.5rem 1rem;">
                                    Mettre à jour
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Section Membres -->
        <div class="section">
            <h2>Membres du groupe</h2>
            
            <?php if(empty($members)): ?>
                <p>Aucun membre dans ce groupe.</p>
            <?php else: ?>
                <div class="members-grid">
                    <?php foreach($members as $member): ?>
                        <div class="member-card">
                            <div class="member-info">
                                <div class="member-name"><?= htmlspecialchars($member['member_name']) ?></div>
                                <span class="member-status <?= $member['user_id'] ? 'status-linked' : 'status-unlinked' ?>">
                                    <?= $member['user_id'] ? 'Compte lié' : 'Non lié' ?>
                                </span>
                            </div>
                            <?php if(isAdmin()): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Retirer ce membre du groupe?')">Retirer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if(getUserStatus() !== 'visiteur'): ?>
                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Ajouter un membre</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_member">
                    
                    <div class="form-group">
                        <label>Type de membre :</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="member_type" value="existing_user" onchange="toggleMemberFields()" required>
                                Utilisateur existant
                            </label>
                            <label>
                                <input type="radio" name="member_type" value="new_member" onchange="toggleMemberFields()" required>
                                Nouveau nom (sans compte)
                            </label>
                        </div>
                    </div>
                    
                    <div id="existing_user_field" class="form-group conditional-field">
                        <label>Sélectionner un utilisateur :</label>
                        <select name="existing_user_id">
                            <option value="">-- Choisir un utilisateur --</option>
                            <?php foreach($availableUsers as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="new_member_field" class="form-group conditional-field">
                        <label>Nom du nouveau membre :</label>
                        <input type="text" name="new_member_name" placeholder="Ex: Jean Dupont">
                    </div>
                    
                    <button type="submit" class="btn-primary">Ajouter le membre</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Section Dépenses avec onglets -->
        <div class="section">
            <h2>Dépenses du groupe</h2>
            
            <?php if($group['stay_mode_enabled']): ?>
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="showTab('all')">Toutes les dépenses</button>
                    <button class="tab-btn" onclick="showTab('classique')">Mode classique</button>
                    <button class="tab-btn" onclick="showTab('sejour')">Mode séjour</button>
                </div>
            <?php endif; ?>
            
            <?php if(getUserStatus() !== 'visiteur'): ?>
                <form method="POST" class="expense-form" style="margin-bottom: 2rem;">
                    <input type="hidden" name="action" value="add_expense">
                    
                    <?php if($group['stay_mode_enabled']): ?>
                        <div class="expense-mode-toggle">
                            <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Mode de la dépense :</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="expense_mode" value="classique" checked>
                                    Mode classique
                                </label>
                                <label>
                                    <input type="radio" name="expense_mode" value="sejour">
                                    Mode séjour
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Description de la dépense :</label>
                            <input type="text" name="title" required placeholder="Ex: Restaurant, Courses...">
                        </div>
                        
                        <div class="form-group">
                            <label>Montant (€) :</label>
                            <input type="number" name="amount" step="0.01" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Payé par :</label>
                        <select name="paid_by" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach($members as $member): ?>
                                <option value="<?= htmlspecialchars($member['member_name']) ?>">
                                    <?= htmlspecialchars($member['member_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Participants :</label>
                        <div class="checkbox-group">
                            <?php foreach($members as $member): ?>
                                <label>
                                    <input type="checkbox" name="participants[]" value="<?= htmlspecialchars($member['member_name']) ?>" checked>
                                    <?= htmlspecialchars($member['member_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Ajouter la dépense</button>
                </form>
            <?php endif; ?>
            
            <?php if(empty($expenses)): ?>
                <p style="color: #6b7280; text-align: center; padding: 2rem;">Aucune dépense enregistrée pour ce groupe.</p>
            <?php else: ?>
                <div class="expenses-list" id="expenses-container">
                    <?php foreach($expenses as $expense): ?>
                        <div class="expense-card expense-mode-<?= $expense['expense_mode'] ?>" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h4 style="color: #1f2937; margin-bottom: 0.5rem; font-size: 1.1rem;">
                                        <?= htmlspecialchars($expense['title']) ?>
                                        <span class="balance-mode-indicator mode-<?= $expense['expense_mode'] ?>">
                                            <?= ucfirst($expense['expense_mode']) ?>
                                        </span>
                                    </h4>
                                    <p style="color: #6b7280; font-size: 0.9rem;">
                                        Payé par <strong><?= htmlspecialchars($expense['paid_by']) ?></strong> 
                                        le <?= date('d/m/Y', strtotime($expense['created_at'])) ?>
                                    </p>
                                    <p style="color: #9ca3af; font-size: 0.8rem;">
                                        Créé par <?= htmlspecialchars($expense['creator_name'] ?: 'Inconnu') ?>
                                        <?php if($expense['modified_at']): ?>
                                            • Modifié par <?= htmlspecialchars($expense['modifier_name'] ?: 'Inconnu') ?> 
                                            le <?= date('d/m/Y', strtotime($expense['modified_at'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.5rem; font-weight: bold; color: #059669;"><?= number_format($expense['amount'], 2) ?> €</div>
                                </div>
                            </div>
                            
                            <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-bottom: 1rem;">
                                <strong style="color: #374151;">Participants :</strong>
                                <?php 
                                $participants = $expenseManager->getExpenseParticipants($expense['id']);
                                $participantNames = array_column($participants, 'member_name');
                                $sharePerPerson = count($participants) > 0 ? $expense['amount'] / count($participants) : 0;
                                ?>
                                <span style="color: #6b7280;">
                                    <?= htmlspecialchars(implode(', ', $participantNames)) ?>
                                    (<?= number_format($sharePerPerson, 2) ?> € chacun)
                                </span>
                            </div>
                            
                            <!-- Formulaire de modification -->
                            <div id="edit-form-<?= $expense['id'] ?>" class="edit-expense-form" style="display: none; background: #f1f5f9; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="edit_expense">
                                    <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                    
                                    <?php if($group['stay_mode_enabled']): ?>
                                        <div style="margin-bottom: 1rem;">
                                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Mode :</label>
                                            <div style="display: flex; gap: 1rem;">
                                                <label style="display: flex; align-items: center;">
                                                    <input type="radio" name="expense_mode" value="classique" 
                                                           <?= $expense['expense_mode'] === 'classique' ? 'checked' : '' ?> style="margin-right: 0.5rem;">
                                                    Classique
                                                </label>
                                                <label style="display: flex; align-items: center;">
                                                    <input type="radio" name="expense_mode" value="sejour" 
                                                           <?= $expense['expense_mode'] === 'sejour' ? 'checked' : '' ?> style="margin-right: 0.5rem;">
                                                    Séjour
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; margin-bottom: 1rem;">
                                        <input type="text" name="title" value="<?= htmlspecialchars($expense['title']) ?>" required 
                                               style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
                                        <input type="number" name="amount" step="0.01" value="<?= $expense['amount'] ?>" required
                                               style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
                                    </div>
                                    
                                    <div style="margin-bottom: 1rem;">
                                        <select name="paid_by" required style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; width: 100%;">
                                            <?php foreach($members as $member): ?>
                                                <option value="<?= htmlspecialchars($member['member_name']) ?>"
                                                        <?= $member['member_name'] === $expense['paid_by'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($member['member_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div style="margin-bottom: 1rem;">
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem;">
                                            <?php foreach($members as $member): ?>
                                                <label style="display: flex; align-items: center; font-size: 0.9rem;">
                                                    <input type="checkbox" name="participants[]" value="<?= htmlspecialchars($member['member_name']) ?>"
                                                           <?= in_array($member['member_name'], $participantNames) ? 'checked' : '' ?>
                                                           style="margin-right: 0.5rem;">
                                                    <?= htmlspecialchars($member['member_name']) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" style="background: #059669; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                                            Sauvegarder
                                        </button>
                                        <button type="button" onclick="hideEditForm(<?= $expense['id'] ?>)" 
                                                style="background: #6b7280; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Boutons d'action -->
                            <?php if(getUserStatus() !== 'visiteur'): ?>
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php 
                                    $canModify = isAdmin() || $expenseManager->canUserModifyExpense($expense['id'], $_SESSION['user_id']);
                                    ?>
                                    
                                    <?php if($canModify): ?>
                                        <button onclick="showEditForm(<?= $expense['id'] ?>)" 
                                                style="background: #4f46e5; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">
                                            Modifier
                                        </button>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette dépense ?')">
                                            <input type="hidden" name="action" value="delete_expense">
                                            <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                            <button type="submit" style="background: #ef4444; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">
                                                Supprimer
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #9ca3af; font-size: 0.8rem; font-style: italic;">
                                            Seul le créateur peut modifier/supprimer cette dépense
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section Bilans avec onglets si mode séjour activé -->
        <div class="section">
            <h2>Bilans des comptes</h2>
            
            <?php if($group['stay_mode_enabled']): ?>
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="showBalanceTab('classique')">Bilans classiques</button>
                    <button class="tab-btn" onclick="showBalanceTab('sejour')">Bilans séjour</button>
                </div>
                
                <!-- Bilans classiques -->
                <div id="balance-classique" class="balance-tab-content">
                    <h3 style="margin-bottom: 1rem;">Mode classique</h3>
                    <?php if(empty($balances)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">Aucune dépense classique pour calculer les bilans.</p>
                    <?php else: ?>
                        <div class="balances-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach($balances as $person => $balance): ?>
                                <div class="balance-card" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem;">
                                    <h4 style="color: #1f2937; margin-bottom: 1rem;"><?= htmlspecialchars($person) ?></h4>
                                    <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; 
                                                color: <?= $balance > 0 ? '#059669' : ($balance < 0 ? '#dc2626' : '#6b7280') ?>">
                                        <?= $balance > 0 ? '+' : '' ?><?= number_format($balance, 2) ?> €
                                    </div>
                                    <div style="font-size: 0.9rem; color: #6b7280;">
                                        <?php if($balance > 0.01): ?>
                                            À recevoir
                                        <?php elseif($balance < -0.01): ?>
                                            À payer
                                        <?php else: ?>
                                            Équilibré
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Bilans séjour -->
                <div id="balance-sejour" class="balance-tab-content" style="display: none;">
                    <h3 style="margin-bottom: 1rem;">Mode séjour</h3>
                    <?php if(empty($stayBalances)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">Aucune dépense séjour pour calculer les bilans.</p>
                    <?php else: ?>
                        <div class="balances-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach($stayBalances as $person => $balance): ?>
                                <div class="balance-card" style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 1.5rem;">
                                    <h4 style="color: #1f2937; margin-bottom: 1rem;"><?= htmlspecialchars($person) ?></h4>
                                    <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; 
                                                color: <?= $balance > 0 ? '#059669' : ($balance < 0 ? '#dc2626' : '#6b7280') ?>">
                                        <?= $balance > 0 ? '+' : '' ?><?= number_format($balance, 2) ?> €
                                    </div>
                                    <div style="font-size: 0.9rem; color: #6b7280;">
                                        <?php if($balance > 0.01): ?>
                                            À recevoir (séjour)
                                        <?php elseif($balance < -0.01): ?>
                                            À payer (séjour)
                                        <?php else: ?>
                                            Équilibré (séjour)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Bilans simples en mode classique uniquement -->
                <?php if(empty($balances)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">Aucune dépense pour calculer les bilans.</p>
                <?php else: ?>
                    <div class="balances-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                        <?php foreach($balances as $person => $balance): ?>
                            <div class="balance-card" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem;">
                                <h4 style="color: #1f2937; margin-bottom: 1rem;"><?= htmlspecialchars($person) ?></h4>
                                <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; 
                                            color: <?= $balance > 0 ? '#059669' : ($balance < 0 ? '#dc2626' : '#6b7280') ?>">
                                    <?= $balance > 0 ? '+' : '' ?><?= number_format($balance, 2) ?> €
                                </div>
                                <div style="font-size: 0.9rem; color: #6b7280;">
                                    <?php if($balance > 0.01): ?>
                                        À recevoir
                                    <?php elseif($balance < -0.01): ?>
                                        À payer
                                    <?php else: ?>
                                        Équilibré
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
		
		
		<!-- Section Règlements - À ajouter dans group.php après la section des bilans -->

<?php
// Calculer les règlements totaux (combinés)
$combinedBalances = $expenseManager->calculateCombinedBalances($groupId);
$combinedDebts = $expenseManager->calculateCombinedDebts($groupId);
?>

<!-- Section Règlements avec onglets -->
<div class="section">
    <h2>Qui doit quoi à qui ?</h2>
    
    <?php if($group['stay_mode_enabled']): ?>
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="showDebtTab('classique')">Règlements classiques</button>
            <button class="tab-btn" onclick="showDebtTab('sejour')">Règlements séjour</button>
            <button class="tab-btn" onclick="showDebtTab('total')">Règlements TOTAUX</button>
        </div>
        
        <!-- Règlements classiques -->
        <div id="debt-classique" class="debt-tab-content">
            <h3 style="margin-bottom: 1rem; color: #374151;">Mode classique</h3>
            <?php if(empty($debts)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                    <h3 style="color: #059669;">Tous les comptes classiques sont équilibrés !</h3>
                </div>
            <?php else: ?>
                <div class="debts-list">
                    <?php foreach($debts as $debt): ?>
                        <div class="debt-card" style="background: linear-gradient(135deg, #fef3c7, #fed7aa); border: 1px solid #f59e0b; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div style="flex: 1;">
                                    <strong style="color: #92400e; font-size: 1.1rem;">
                                        <?= htmlspecialchars($debt['from']) ?>
                                    </strong>
                                    <span style="color: #d97706; margin: 0 1rem;">doit</span>
                                    <strong style="color: #92400e; font-size: 1.1rem;">
                                        <?= htmlspecialchars($debt['to']) ?>
                                    </strong>
                                </div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #92400e;">
                                    <?= number_format($debt['amount'], 2) ?> €
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Règlements séjour -->
        <div id="debt-sejour" class="debt-tab-content" style="display: none;">
            <h3 style="margin-bottom: 1rem; color: #374151;">Mode séjour</h3>
            <?php if(empty($stayDebts)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                    <h3 style="color: #059669;">Tous les comptes séjour sont équilibrés !</h3>
                </div>
            <?php else: ?>
                <div class="debts-list">
                    <?php foreach($stayDebts as $debt): ?>
                        <div class="debt-card" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); border: 1px solid #10b981; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div style="flex: 1;">
                                    <strong style="color: #065f46; font-size: 1.1rem;">
                                        <?= htmlspecialchars($debt['from']) ?>
                                    </strong>
                                    <span style="color: #047857; margin: 0 1rem;">doit</span>
                                    <strong style="color: #065f46; font-size: 1.1rem;">
                                        <?= htmlspecialchars($debt['to']) ?>
                                    </strong>
                                    <span style="font-size: 0.875rem; color: #047857;">(séjour)</span>
                                </div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #065f46;">
                                    <?= number_format($debt['amount'], 2) ?> €
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- NOUVEAU V4.3 : Règlements TOTAUX (combinés) -->
        <div id="debt-total" class="debt-tab-content" style="display: none;">
            <h3 style="margin-bottom: 1rem; color: #374151;">Règlements TOTAUX (Classique + Séjour)</h3>
            <div style="background: #ede9fe; border: 2px solid #a78bfa; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; color: #5b21b6;">
                <strong>ℹ️ Info :</strong> Ces règlements combinent les dépenses en mode classique et en mode séjour pour un règlement global unique.
            </div>
            
            <?php if(empty($combinedDebts)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                    <h3 style="color: #059669;">Tous les comptes sont équilibrés !</h3>
                    <p>Personne ne doit d'argent à personne (classique + séjour).</p>
                </div>
            <?php else: ?>
                <div class="debts-list">
                    <?php foreach($combinedDebts as $debt): ?>
                        <div class="debt-card" style="background: linear-gradient(135deg, #ede9fe, #ddd6fe); border: 2px solid #a78bfa; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div style="flex: 1;">
                                    <strong style="color: #5b21b6; font-size: 1.1rem;">
                                        <?= htmlspecialchars($debt['from']) ?>
                                    </strong>
                                    <span style="color: #7c3aed; margin: 0 1rem;">doit</span>
                                    <strong style="color: #5b21b6; font-size: 1.1rem;">
                                        <?= htmlspecialchars($debt['to']) ?>
                                    </strong>
                                    <span style="font-size: 0.875rem; color: #7c3aed; font-weight: 600;">(TOTAL)</span>
                                </div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #5b21b6;">
                                    <?= number_format($debt['amount'], 2) ?> €
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Récapitulatif des bilans combinés -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 1.5rem; margin-top: 2rem;">
                    <h4 style="color: #374151; margin-bottom: 1rem;">Récapitulatif des bilans totaux</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach($combinedBalances as $person => $balance): ?>
                            <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <div style="font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                                    <?= htmlspecialchars($person) ?>
                                </div>
                                <div style="font-size: 1.25rem; font-weight: bold; 
                                            color: <?= $balance > 0 ? '#059669' : ($balance < 0 ? '#dc2626' : '#6b7280') ?>">
                                    <?= $balance > 0 ? '+' : '' ?><?= number_format($balance, 2) ?> €
                                </div>
                                <div style="font-size: 0.875rem; color: #6b7280;">
                                    <?php if($balance > 0.01): ?>
                                        À recevoir
                                    <?php elseif($balance < -0.01): ?>
                                        À payer
                                    <?php else: ?>
                                        Équilibré
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <!-- Règlements simples en mode classique uniquement -->
        <?php if(empty($debts)): ?>
            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h3 style="color: #059669;">Tous les comptes sont équilibrés !</h3>
                <p>Personne ne doit d'argent à personne.</p>
            </div>
        <?php else: ?>
            <div class="debts-list">
                <?php foreach($debts as $debt): ?>
                    <div class="debt-card" style="background: linear-gradient(135deg, #fef3c7, #fed7aa); border: 1px solid #f59e0b; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="flex: 1;">
                                <strong style="color: #92400e; font-size: 1.1rem;">
                                    <?= htmlspecialchars($debt['from']) ?>
                                </strong>
                                <span style="color: #d97706; margin: 0 1rem;">doit</span>
                                <strong style="color: #92400e; font-size: 1.1rem;">
                                    <?= htmlspecialchars($debt['to']) ?>
                                </strong>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #92400e;">
                                <?= number_format($debt['amount'], 2) ?> €
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div style="background: #e0f2fe; border: 1px solid #0284c7; border-radius: 8px; padding: 1rem; margin-top: 1.5rem;">
        <strong style="color: #0c4a6e;">💡 Conseil :</strong>
        <span style="color: #0369a1;">
            Ces règlements permettent d'équilibrer tous les comptes avec le minimum de transactions.
            <?php if($group['stay_mode_enabled']): ?>
                L'onglet "Règlements TOTAUX" combine les deux modes pour un règlement global simplifié.
            <?php endif; ?>
        </span>
    </div>
</div>


		
		


    
    <script>
        function toggleMemberFields() {
            const existingUser = document.querySelector('input[name="member_type"][value="existing_user"]');
            const newMember = document.querySelector('input[name="member_type"][value="new_member"]');
            const existingUserField = document.getElementById('existing_user_field');
            const newMemberField = document.getElementById('new_member_field');
            
            if (existingUser.checked) {
                existingUserField.classList.add('show');
                newMemberField.classList.remove('show');
                newMemberField.querySelector('input').value = '';
            } else if (newMember.checked) {
                newMemberField.classList.add('show');
                existingUserField.classList.remove('show');
                existingUserField.querySelector('select').value = '';
            }
        }
        
        // Fermer le modal en cliquant sur l'overlay
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });
        
        function showEditForm(expenseId) {
            document.getElementById('edit-form-' + expenseId).style.display = 'block';
        }
        
        function hideEditForm(expenseId) {
            document.getElementById('edit-form-' + expenseId).style.display = 'none';
        }
        
        // Gestion des onglets pour les dépenses
        function showTab(mode) {
            // Retirer la classe active de tous les boutons d'onglets
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            // Ajouter la classe active au bouton cliqué
            event.target.classList.add('active');
            
            // Afficher/masquer les dépenses selon le mode
            const expenses = document.querySelectorAll('.expense-card');
            expenses.forEach(expense => {
                if (mode === 'all') {
                    expense.style.display = 'block';
                } else {
                    if (expense.classList.contains('expense-mode-' + mode)) {
                        expense.style.display = 'block';
                    } else {
                        expense.style.display = 'none';
                    }
                }
            });
        }
        
        // Gestion des onglets pour les bilans
        function showBalanceTab(mode) {
            // Retirer la classe active de tous les boutons d'onglets de bilans
            const balanceSection = Array.from(document.querySelectorAll('.section')).find(section => section.querySelector('h2')?.textContent.includes('Bilans')) || null;
            
            if (balanceSection) {
                balanceSection.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');
                
                // Masquer tous les contenus d'onglets
                balanceSection.querySelectorAll('.balance-tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                
                // Afficher le contenu sélectionné
                const targetContent = document.getElementById('balance-' + mode);
                if (targetContent) {
                    targetContent.style.display = 'block';
                }
            }
        }
        
        // Gestion des onglets pour les dettes
        function showDebtTab(mode) {
    const debtSection = Array.from(document.querySelectorAll('.section')).find(section => 
        section.querySelector('h2')?.textContent.includes('doit')
    ) || null;
    
    if (debtSection) {
        debtSection.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        debtSection.querySelectorAll('.debt-tab-content').forEach(content => {
            content.style.display = 'none';
        });
        
        const targetContent = document.getElementById('debt-' + mode);
        if (targetContent) {
            targetContent.style.display = 'block';
        }
    }
}
		
		
		
		
		
    </script>

<script>
    // NOUVEAU V4.5 : Afficher les règlements totaux par défaut si le mode séjour est activé
    document.addEventListener('DOMContentLoaded', function() {
        <?php if($group['stay_mode_enabled']): ?>
            // Au chargement, afficher directement l'onglet "Règlements TOTAUX"
            const debtSection = Array.from(document.querySelectorAll('.section')).find(section => 
                section.querySelector('h2')?.textContent.includes('doit')
            );
            
            if (debtSection) {
                // Activer l'onglet "TOTAUX"
                const totalTab = Array.from(debtSection.querySelectorAll('.tab-btn')).find(btn => 
                    btn.textContent.includes('TOTAUX')
                );
                
                if (totalTab) {
                    // Désactiver tous les onglets
                    debtSection.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                    // Activer l'onglet TOTAUX
                    totalTab.classList.add('active');
                    
                    // Masquer tous les contenus
                    debtSection.querySelectorAll('.debt-tab-content').forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    // Afficher le contenu TOTAUX
                    const totalContent = document.getElementById('debt-total');
                    if (totalContent) {
                        totalContent.style.display = 'block';
                    }
                }
            }
        <?php endif; ?>
    });
</script>


</body>
</html>