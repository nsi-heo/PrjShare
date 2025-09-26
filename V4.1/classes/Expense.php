<?php
// classes/Expense.php - Version avec mode séjour
class Expense {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function addExpense($groupId, $title, $amount, $paidBy, $createdBy, $participants, $mode = 'classique') {
        $this->conn->beginTransaction();
        
        try {
            // Ajouter la dépense avec le mode
            $query = "INSERT INTO expenses (group_id, title, amount, paid_by, created_by, expense_mode) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $title, $amount, $paidBy, $createdBy, $mode]);
            $expenseId = $this->conn->lastInsertId();
            
            // Ajouter les participants (identique pour les deux modes)
            $query = "INSERT INTO expense_participants (expense_id, member_name, share) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            
            $sharePerPerson = $amount / count($participants);
            foreach($participants as $participant) {
                $stmt->execute([$expenseId, $participant, $sharePerPerson]);
            }
            
            $this->conn->commit();
            return $expenseId;
        } catch(Exception $e) {
            $this->conn->rollback();
            error_log("Erreur ajout dépense: " . $e->getMessage());
            return false;
        }
    }
    
    public function getGroupExpenses($groupId) {
        $query = "SELECT e.*, 
                         u1.username as creator_name,
                         u2.username as modifier_name,
                         e.modified_at
                  FROM expenses e 
                  LEFT JOIN users u1 ON e.created_by = u1.id 
                  LEFT JOIN users u2 ON e.modified_by = u2.id
                  WHERE e.group_id = ? 
                  ORDER BY e.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // NOUVEAU : Récupérer les dépenses par mode
    public function getGroupExpensesByMode($groupId, $mode = null) {
        if ($mode) {
            $query = "SELECT e.*, 
                             u1.username as creator_name,
                             u2.username as modifier_name,
                             e.modified_at
                      FROM expenses e 
                      LEFT JOIN users u1 ON e.created_by = u1.id 
                      LEFT JOIN users u2 ON e.modified_by = u2.id
                      WHERE e.group_id = ? AND e.expense_mode = ?
                      ORDER BY e.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $mode]);
        } else {
            return $this->getGroupExpenses($groupId);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getExpenseParticipants($expenseId) {
        $query = "SELECT * FROM expense_participants WHERE expense_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$expenseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function deleteExpense($expenseId) {
        $query = "DELETE FROM expenses WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$expenseId]);
    }
    
    // NOUVEAU : Calculer les bilans en mode séjour
    public function calculateStayBalances($groupId) {
        try {
            require_once 'classes/Group.php';
            $groupManager = new Group($this->conn);
            
            $group = $groupManager->getGroupById($groupId);
            if (!$group || !$group['stay_mode_enabled']) {
                return [];
            }
            
            // Récupérer les dépenses en mode séjour
            $stayExpenses = $this->getGroupExpensesByMode($groupId, 'sejour');
            $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
            
            if (empty($stayExpenses) || empty($stayPeriods)) {
                return [];
            }
            
            // Calculer le total des frais de séjour
            $totalStayExpenses = 0;
            foreach ($stayExpenses as $expense) {
                $totalStayExpenses += $expense['amount'];
            }
            
            // Calculer la durée du séjour du groupe en jours
            $groupStartDate = new DateTime($group['stay_start_date']);
            $groupEndDate = new DateTime($group['stay_end_date']);
            $totalGroupDays = $groupEndDate->diff($groupStartDate)->days + 1; // +1 pour inclure le dernier jour
            
            // Calculer la somme des coefficients de tous les membres
            $totalCoefficients = 0;
            foreach ($stayPeriods as $period) {
                $totalCoefficients += $period['coefficient'];
            }
            
            if ($totalCoefficients == 0 || $totalGroupDays == 0) {
                return [];
            }
            
            // Calculer le coût par jour par coefficient
            $costPerDayPerCoefficient = $totalStayExpenses / $totalGroupDays / $totalCoefficients;
            
            // Calculer la part de chaque membre
            $balances = [];
            foreach ($stayPeriods as $period) {
                $memberStartDate = new DateTime($period['start_date']);
                $memberEndDate = new DateTime($period['end_date']);
                $memberDays = $memberEndDate->diff($memberStartDate)->days + 1;
                
                $memberShare = $costPerDayPerCoefficient * $memberDays * $period['coefficient'];
                $balances[$period['member_name']] = -$memberShare; // Négatif car c'est ce qu'ils doivent
            }
            
            // Ajouter ce que chaque personne a payé
            foreach ($stayExpenses as $expense) {
                if (!isset($balances[$expense['paid_by']])) {
                    $balances[$expense['paid_by']] = 0;
                }
                $balances[$expense['paid_by']] += $expense['amount'];
            }
            
            return $balances;
            
        } catch (Exception $e) {
            error_log("Erreur calcul bilans séjour: " . $e->getMessage());
            return [];
        }
    }
    
    public function calculateBalances($groupId) {
        // Récupérer toutes les dépenses classiques du groupe
        $expenses = $this->getGroupExpensesByMode($groupId, 'classique');
        $balances = [];
        
        foreach($expenses as $expense) {
            $participants = $this->getExpenseParticipants($expense['id']);
            
            // Initialiser les balances si nécessaire
            if(!isset($balances[$expense['paid_by']])) {
                $balances[$expense['paid_by']] = 0;
            }
            
            // Celui qui a payé a un crédit
            $balances[$expense['paid_by']] += $expense['amount'];
            
            // Les participants ont une dette
            foreach($participants as $participant) {
                if(!isset($balances[$participant['member_name']])) {
                    $balances[$participant['member_name']] = 0;
                }
                $balances[$participant['member_name']] -= $participant['share'];
            }
        }
        
        return $balances;
    }
    
    // NOUVEAU : Calculer les dettes en mode séjour
    public function calculateStayDebts($groupId) {
        $balances = $this->calculateStayBalances($groupId);
        return $this->calculateDebtsFromBalances($balances);
    }
    
    public function calculateDebts($groupId) {
        $balances = $this->calculateBalances($groupId);
        return $this->calculateDebtsFromBalances($balances);
    }
    
    // NOUVEAU : Méthode commune pour calculer les dettes à partir des bilans
    private function calculateDebtsFromBalances($balances) {
        $debts = [];
        
        $creditors = [];
        $debtors = [];
        
        foreach($balances as $person => $balance) {
            if($balance > 0.01) {
                $creditors[$person] = $balance;
            } elseif($balance < -0.01) {
                $debtors[$person] = abs($balance);
            }
        }
        
        // Calculer qui doit combien à qui
        foreach($debtors as $debtor => $debt) {
            foreach($creditors as $creditor => $credit) {
                if($debt <= 0 || $credit <= 0) continue;
                
                $payment = min($debt, $credit);
                if($payment > 0.01) {
                    $debts[] = [
                        'from' => $debtor,
                        'to' => $creditor,
                        'amount' => round($payment, 2)
                    ];
                    
                    $debt -= $payment;
                    $creditors[$creditor] -= $payment;
                }
            }
        }
        
        return $debts;
    }
    
    public function updateExpense($expenseId, $title, $amount, $paidBy, $participants) {
        $this->conn->beginTransaction();
        
        try {
            // Mettre à jour la dépense principale
            $query = "UPDATE expenses SET title = ?, amount = ?, paid_by = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$title, $amount, $paidBy, $expenseId]);
            
            // Supprimer les anciens participants
            $deleteQuery = "DELETE FROM expense_participants WHERE expense_id = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->execute([$expenseId]);
            
            // Ajouter les nouveaux participants
            $insertQuery = "INSERT INTO expense_participants (expense_id, member_name, share) VALUES (?, ?, ?)";
            $insertStmt = $this->conn->prepare($insertQuery);
            
            $sharePerPerson = $amount / count($participants);
            foreach($participants as $participant) {
                $insertStmt->execute([$expenseId, $participant, $sharePerPerson]);
            }
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    public function getExpenseById($expenseId) {
        $query = "SELECT e.*, u.username as creator_name FROM expenses e 
                  LEFT JOIN users u ON e.created_by = u.id 
                  WHERE e.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$expenseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateExpenseWithModifier($expenseId, $title, $amount, $paidBy, $participants, $modifiedBy, $mode = null) {
        $this->conn->beginTransaction();
        
        try {
            // Si le mode est spécifié, l'inclure dans la mise à jour
            if ($mode !== null) {
                $query = "UPDATE expenses SET title = ?, amount = ?, paid_by = ?, expense_mode = ?, modified_by = ?, modified_at = NOW() WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$title, $amount, $paidBy, $mode, $modifiedBy, $expenseId]);
            } else {
                $query = "UPDATE expenses SET title = ?, amount = ?, paid_by = ?, modified_by = ?, modified_at = NOW() WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$title, $amount, $paidBy, $modifiedBy, $expenseId]);
            }
            
            // Supprimer les anciens participants
            $deleteQuery = "DELETE FROM expense_participants WHERE expense_id = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->execute([$expenseId]);
            
            // Ajouter les nouveaux participants
            $insertQuery = "INSERT INTO expense_participants (expense_id, member_name, share) VALUES (?, ?, ?)";
            $insertStmt = $this->conn->prepare($insertQuery);
            
            $sharePerPerson = $amount / count($participants);
            foreach($participants as $participant) {
                $insertStmt->execute([$expenseId, $participant, $sharePerPerson]);
            }
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    public function canUserModifyExpense($expenseId, $userId) {
        try {
            $query = "SELECT created_by FROM expenses WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$expense) {
                return false;
            }
            
            return (int)$expense['created_by'] === (int)$userId;
        } catch(PDOException $e) {
            error_log("Erreur canUserModifyExpense: " . $e->getMessage());
            return false;
        }
    }
}
?>