<?php
// classes/Group.php - Version avec mode séjour
class Group {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function createGroup($name, $description, $createdBy) {
        try {
            $query = "INSERT INTO groups_table (name, description, created_by) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            
            if($stmt->execute([$name, $description, $createdBy])) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erreur création groupe: " . $e->getMessage());
            return false;
        }
    }
    
    // NOUVEAU : Configuration du mode séjour
    public function configureStayMode($groupId, $startDate, $endDate) {
        try {
            if (strtotime($startDate) > strtotime($endDate)) {
                return ['status' => 'error', 'message' => 'La date de début doit être antérieure à la date de fin'];
            }
            
            $query = "UPDATE groups_table 
                     SET stay_mode_enabled = TRUE, stay_start_date = ?, stay_end_date = ? 
                     WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$startDate, $endDate, $groupId])) {
                // Créer les périodes par défaut pour tous les membres existants
                $this->createDefaultStayPeriodsForMembers($groupId, $startDate, $endDate);
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de la configuration'];
        } catch(PDOException $e) {
            error_log("Erreur configuration séjour: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    // NOUVEAU : Créer les périodes par défaut pour tous les membres
    private function createDefaultStayPeriodsForMembers($groupId, $startDate, $endDate) {
        try {
            $members = $this->getGroupMembers($groupId);
            
            $query = "INSERT INTO member_stay_periods (group_id, member_name, start_date, end_date, coefficient) 
                     VALUES (?, ?, ?, ?, 1.00)";
            $stmt = $this->conn->prepare($query);
            
            foreach ($members as $member) {
                $stmt->execute([$groupId, $member['member_name'], $startDate, $endDate]);
            }
        } catch(PDOException $e) {
            error_log("Erreur création périodes par défaut: " . $e->getMessage());
        }
    }
    
    // NOUVEAU : Mettre à jour une période de séjour pour un membre
    public function updateMemberStayPeriod($groupId, $memberName, $startDate, $endDate, $coefficient) {
        try {
            // Vérifier que les dates sont dans la période du groupe
            $group = $this->getGroupById($groupId);
            if (!$group || !$group['stay_mode_enabled']) {
                return ['status' => 'error', 'message' => 'Le mode séjour n\'est pas activé pour ce groupe'];
            }
            
            $groupStart = strtotime($group['stay_start_date']);
            $groupEnd = strtotime($group['stay_end_date']);
            $memberStart = strtotime($startDate);
            $memberEnd = strtotime($endDate);
            
            if ($memberStart < $groupStart || $memberEnd > $groupEnd || $memberStart > $memberEnd) {
                return ['status' => 'error', 'message' => 'Les dates doivent être comprises dans la période de séjour du groupe'];
            }
            
            // Insérer ou mettre à jour
            $query = "INSERT INTO member_stay_periods (group_id, member_name, start_date, end_date, coefficient) 
                     VALUES (?, ?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE 
                     start_date = VALUES(start_date), 
                     end_date = VALUES(end_date), 
                     coefficient = VALUES(coefficient),
                     updated_at = NOW()";
            
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$groupId, $memberName, $startDate, $endDate, $coefficient])) {
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de la mise à jour'];
            
        } catch(PDOException $e) {
            error_log("Erreur mise à jour période séjour: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    // NOUVEAU : Récupérer les périodes de séjour des membres
    public function getMemberStayPeriods($groupId) {
        try {
            $query = "SELECT * FROM member_stay_periods WHERE group_id = ? ORDER BY member_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération périodes séjour: " . $e->getMessage());
            return [];
        }
    }
    
    // NOUVEAU : Créer une période par défaut pour un nouveau membre
    public function createDefaultStayPeriodForMember($groupId, $memberName) {
        try {
            $group = $this->getGroupById($groupId);
            if ($group && $group['stay_mode_enabled']) {
                $query = "INSERT INTO member_stay_periods (group_id, member_name, start_date, end_date, coefficient) 
                         VALUES (?, ?, ?, ?, 1.00)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$groupId, $memberName, $group['stay_start_date'], $group['stay_end_date']]);
            }
        } catch(PDOException $e) {
            error_log("Erreur création période par défaut: " . $e->getMessage());
        }
    }
    
    // NOUVEAU : Désactiver le mode séjour
    public function disableStayMode($groupId) {
        try {
            $query = "UPDATE groups_table 
                     SET stay_mode_enabled = FALSE, stay_start_date = NULL, stay_end_date = NULL 
                     WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$groupId])) {
                // Supprimer toutes les périodes de séjour des membres
                $deleteQuery = "DELETE FROM member_stay_periods WHERE group_id = ?";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->execute([$groupId]);
                
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de la désactivation'];
        } catch(PDOException $e) {
            error_log("Erreur désactivation séjour: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    public function getAllGroups() {
        try {
            $query = "SELECT g.*, u.username as creator_name FROM groups_table g 
                      LEFT JOIN users u ON g.created_by = u.id ORDER BY g.name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération groupes: " . $e->getMessage());
            return [];
        }
    }
    
    public function getGroupById($id) {
        try {
            $query = "SELECT g.*, u.username as creator_name FROM groups_table g 
                      LEFT JOIN users u ON g.created_by = u.id WHERE g.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération groupe: " . $e->getMessage());
            return false;
        }
    }
    
    public function addMemberToGroup($groupId, $memberName, $userId = null) {
        try {
            $query = "INSERT INTO group_members (group_id, user_id, member_name, status) VALUES (?, ?, ?, 'active')";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$groupId, $userId, $memberName])) {
                // Créer une période de séjour par défaut si le mode est activé
                $this->createDefaultStayPeriodForMember($groupId, $memberName);
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erreur ajout membre: " . $e->getMessage());
            return false;
        }
    }
    
    public function getGroupMembers($groupId) {
        try {
            $query = "SELECT gm.*, u.username, u.status as user_status FROM group_members gm 
                      LEFT JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? ORDER BY gm.member_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération membres: " . $e->getMessage());
            return [];
        }
    }
    
    public function removeMemberFromGroup($memberId, $groupId) {
        try {
            // D'abord récupérer le nom du membre
            $memberQuery = "SELECT member_name FROM group_members WHERE id = ? AND group_id = ?";
            $memberStmt = $this->conn->prepare($memberQuery);
            $memberStmt->execute([$memberId, $groupId]);
            $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($member) {
                // Supprimer la période de séjour
                $deleteStayQuery = "DELETE FROM member_stay_periods WHERE group_id = ? AND member_name = ?";
                $deleteStayStmt = $this->conn->prepare($deleteStayQuery);
                $deleteStayStmt->execute([$groupId, $member['member_name']]);
            }
            
            // Supprimer le membre
            $query = "DELETE FROM group_members WHERE id = ? AND group_id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$memberId, $groupId]);
        } catch(PDOException $e) {
            error_log("Erreur suppression membre: " . $e->getMessage());
            return false;
        }
    }
    
    // Méthodes existantes (inchangées)...
    public function isUserInGroup($groupId, $userId) {
        try {
            $query = "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $userId]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification membre: " . $e->getMessage());
            return false;
        }
    }
    
    public function isMemberNameInGroup($groupId, $memberName) {
        try {
            $query = "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND member_name = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $memberName]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification nom membre: " . $e->getMessage());
            return false;
        }
    }
    
    public function groupNameExists($name, $excludeId = null) {
        try {
            if($excludeId) {
                $query = "SELECT COUNT(*) FROM groups_table WHERE name = ? AND id != ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$name, $excludeId]);
            } else {
                $query = "SELECT COUNT(*) FROM groups_table WHERE name = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$name]);
            }
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification nom groupe: " . $e->getMessage());
            return true;
        }
    }
    
    public function updateGroup($id, $name, $description) {
        try {
            $query = "UPDATE groups_table SET name = ?, description = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$name, $description, $id]);
        } catch(PDOException $e) {
            error_log("Erreur mise à jour groupe: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteGroup($groupId) {
        try {
            $query = "DELETE FROM groups_table WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$groupId]);
        } catch(PDOException $e) {
            error_log("Erreur suppression groupe: " . $e->getMessage());
            return false;
        }
    }
    
    public function linkMemberToUser($groupId, $memberName, $userId) {
        try {
            $query = "SELECT id FROM group_members WHERE group_id = ? AND member_name = ? AND user_id IS NULL";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $memberName]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($member) {
                $updateQuery = "UPDATE group_members SET user_id = ? WHERE id = ?";
                $updateStmt = $this->conn->prepare($updateQuery);
                return $updateStmt->execute([$userId, $member['id']]);
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erreur liaison membre: " . $e->getMessage());
            return false;
        }
    }
    
    public function hasUnlinkedMemberWithName($groupId, $memberName) {
        try {
            $query = "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND member_name = ? AND user_id IS NULL";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $memberName]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification membre non-lié: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUnlinkedMemberByName($groupId, $memberName) {
        try {
            $query = "SELECT * FROM group_members WHERE group_id = ? AND member_name = ? AND user_id IS NULL";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $memberName]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération membre non-lié: " . $e->getMessage());
            return false;
        }
    }
    
    public function addMemberToGroupWithConflictCheck($groupId, $memberName, $userId = null) {
        try {
            if($userId !== null) {
                if($this->hasUnlinkedMemberWithName($groupId, $memberName)) {
                    return ['status' => 'conflict', 'action' => 'link_existing'];
                }
                
                if($this->isUserInGroup($groupId, $userId)) {
                    return ['status' => 'error', 'message' => 'Cet utilisateur est déjà membre du groupe'];
                }
            }
            
            if($userId === null) {
                if($this->isMemberNameInGroup($groupId, $memberName)) {
                    return ['status' => 'error', 'message' => 'Ce nom est déjà utilisé dans ce groupe'];
                }
            }
            
            if($this->addMemberToGroup($groupId, $memberName, $userId)) {
                return ['status' => 'success', 'message' => 'Membre ajouté avec succès'];
            } else {
                return ['status' => 'error', 'message' => 'Erreur lors de l\'ajout du membre'];
            }
            
        } catch(Exception $e) {
            error_log("Erreur ajout membre avec vérification: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
}
?>