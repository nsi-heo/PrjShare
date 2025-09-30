<?php
// classes/Expense.php - Version V4.3 avec règlements totaux
class Expense {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function addExpense($groupId, $title, $amount, $paidBy, $createdBy, $participants, $mode = 'classique') {
        $this->conn->beginTransaction();
        
        try {
											   
            $query = "INSERT INTO expenses (group_id, title, amount, paid_by, created_by, expense_mode) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $title, $amount, $paidBy, $createdBy, $mode]);
            $expenseId = $this->conn->lastInsertId();
            
																	   
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
    
													
    public function calculateStayBalances($groupId) {
        try {
            require_once 'classes/Group.php';
            $groupManager = new Group($this->conn);
            
            $group = $groupManager->getGroupById($groupId);
            if (!$group || !$group['stay_mode_enabled']) {
                return [];
            }
            
														
            $stayExpenses = $this->getGroupExpensesByMode($groupId, 'sejour');
            $stayPeriods = $groupManager->getMemberStayPeriods($groupId);
            
            if (empty($stayExpenses) || empty($stayPeriods)) {
                return [];
            }
            
													 
            $totalStayExpenses = 0;
            foreach ($stayExpenses as $expense) {
                $totalStayExpenses += $expense['amount'];
            }
            
															   
            $groupStartDate = new DateTime($group['stay_start_date']);
            $groupEndDate = new DateTime($group['stay_end_date']);
            $totalGroupDays = $groupEndDate->diff($groupStartDate)->days + 1;
            
																	 
            $totalCoefficients = 0;
            foreach ($stayPeriods as $period) {
                $totalCoefficients += $period['coefficient'];
            }
            
            if ($totalCoefficients == 0 || $totalGroupDays == 0) {
                return [];
            }
            
														 
            $costPerDayPerCoefficient = $totalStayExpenses / $totalGroupDays / $totalCoefficients;
            
												
            $balances = [];
            foreach ($stayPeriods as $period) {
                $memberStartDate = new DateTime($period['start_date']);
                $memberEndDate = new DateTime($period['end_date']);
                $memberDays = $memberEndDate->diff($memberStartDate)->days + 1;
                
                $memberShare = $costPerDayPerCoefficient * $memberDays * $period['coefficient'];
                $balances[$period['member_name']] = -$memberShare;
            }
            
													 
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
																
        $expenses = $this->getGroupExpensesByMode($groupId, 'classique');
        $balances = [];
        
        foreach($expenses as $expense) {
            $participants = $this->getExpenseParticipants($expense['id']);
            
													  
            if(!isset($balances[$expense['paid_by']])) {
                $balances[$expense['paid_by']] = 0;
            }
            
											 
            $balances[$expense['paid_by']] += $expense['amount'];
            
											 
            foreach($participants as $participant) {
                if(!isset($balances[$participant['member_name']])) {
                    $balances[$participant['member_name']] = 0;
                }
                $balances[$participant['member_name']] -= $participant['share'];
            }
        }
        
        return $balances;
    }
    
    // NOUVEAU V4.3 : Calculer les bilans combinés (classique + séjour)
    public function calculateCombinedBalances($groupId) {
        $classiqueBalances = $this->calculateBalances($groupId);
        $sejourBalances = $this->calculateStayBalances($groupId);
        
        $combinedBalances = [];
        
        // Fusionner les deux tableaux de bilans
        $allMembers = array_unique(array_merge(
            array_keys($classiqueBalances),
            array_keys($sejourBalances)
        ));
        
        foreach ($allMembers as $member) {
            $classique = $classiqueBalances[$member] ?? 0;
            $sejour = $sejourBalances[$member] ?? 0;
            $combinedBalances[$member] = $classique + $sejour;
        }
        
        return $combinedBalances;
    }
    
													
    public function calculateStayDebts($groupId) {
        $balances = $this->calculateStayBalances($groupId);
        return $this->calculateDebtsFromBalances($balances);
    }
    
    public function calculateDebts($groupId) {
        $balances = $this->calculateBalances($groupId);
        return $this->calculateDebtsFromBalances($balances);
    }
    
    // NOUVEAU V4.3 : Calculer les dettes combinées
    public function calculateCombinedDebts($groupId) {
        $balances = $this->calculateCombinedBalances($groupId);
        return $this->calculateDebtsFromBalances($balances);
    }
    
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
    
																						
										
		
			 
													
																						   
												 
																   
			
												 
																				   
															 
											   
			
												
																												
															 
			
															 
													
																				  
			 
			
								  
						
							   
									
						 
		 
	 
	
												
																		 
														   
								  
											 
									 
											  
	 
	
    public function updateExpenseWithModifier($expenseId, $title, $amount, $paidBy, $participants, $modifiedBy, $mode = null) {
        $this->conn->beginTransaction();
        
        try {
																		
            if ($mode !== null) {
                $query = "UPDATE expenses SET title = ?, amount = ?, paid_by = ?, expense_mode = ?, modified_by = ?, modified_at = NOW() WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$title, $amount, $paidBy, $mode, $modifiedBy, $expenseId]);
            } else {
                $query = "UPDATE expenses SET title = ?, amount = ?, paid_by = ?, modified_by = ?, modified_at = NOW() WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$title, $amount, $paidBy, $modifiedBy, $expenseId]);
            }
            
												 
            $deleteQuery = "DELETE FROM expense_participants WHERE expense_id = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->execute([$expenseId]);
            
												
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