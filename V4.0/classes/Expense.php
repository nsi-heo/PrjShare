<?php
// classes/Expense.php
class Expense {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function addExpense($groupId, $title, $amount, $paidBy, $createdBy, $participants) {
        $this->conn->beginTransaction();
        
        try {
            // Ajouter la dépense
            $query = "INSERT INTO expenses (group_id, title, amount, paid_by, created_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $title, $amount, $paidBy, $createdBy]);
            $expenseId = $this->conn->lastInsertId();
            
            // Ajouter les participants
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
    
    public function calculateBalances($groupId) {
        // Récupérer toutes les dépenses du groupe
        $expenses = $this->getGroupExpenses($groupId);
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
    
    public function calculateDebts($groupId) {
        $balances = $this->calculateBalances($groupId);
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
	
	public function updateExpenseWithModifier($expenseId, $title, $amount, $paidBy, $participants, $modifiedBy) {
        $this->conn->beginTransaction();
        
        try {
            // Mettre à jour la dépense principale avec qui l'a modifiée
            $query = "UPDATE expenses SET title = ?, amount = ?, paid_by = ?, modified_by = ?, modified_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$title, $amount, $paidBy, $modifiedBy, $expenseId]);
            
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