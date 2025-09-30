<?php
// classes/Group.php - Version V4.3 avec gestion des demandes d'intégration
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
    
    // NOUVEAU V4.3 : Créer une demande d'intégration à un groupe
    public function createJoinRequest($groupId, $userId) {
        try {
            // Vérifier si l'utilisateur est déjà membre
            if ($this->isUserInGroup($groupId, $userId)) {
                return ['status' => 'error', 'message' => 'Vous êtes déjà membre de ce groupe'];
            }
            
            // Vérifier si une demande existe déjà
            $checkQuery = "SELECT COUNT(*) FROM group_join_requests WHERE group_id = ? AND user_id = ? AND status = 'pending'";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$groupId, $userId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                return ['status' => 'error', 'message' => 'Vous avez déjà une demande en attente pour ce groupe'];
            }
            
            // Créer la demande
            $query = "INSERT INTO group_join_requests (group_id, user_id, status) VALUES (?, ?, 'pending')";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$groupId, $userId])) {
                return ['status' => 'success', 'message' => 'Demande d\'intégration envoyée avec succès'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de l\'envoi de la demande'];
        } catch(PDOException $e) {
            error_log("Erreur création demande intégration: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    // NOUVEAU V4.3 : Récupérer les demandes d'intégration en attente
    public function getPendingJoinRequests($groupId = null) {
        try {
            if ($groupId) {
                $query = "SELECT jr.*, u.username, u.email, g.name as group_name 
                         FROM group_join_requests jr
                         JOIN users u ON jr.user_id = u.id
                         JOIN groups_table g ON jr.group_id = g.id
                         WHERE jr.group_id = ? AND jr.status = 'pending'
                         ORDER BY jr.created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$groupId]);
            } else {
                $query = "SELECT jr.*, u.username, u.email, g.name as group_name 
                         FROM group_join_requests jr
                         JOIN users u ON jr.user_id = u.id
                         JOIN groups_table g ON jr.group_id = g.id
                         WHERE jr.status = 'pending'
                         ORDER BY jr.created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération demandes: " . $e->getMessage());
            return [];
        }
    }
    
    // NOUVEAU V4.3 : Approuver une demande d'intégration
    public function approveJoinRequest($requestId) {
        try {
            // Récupérer les détails de la demande
            $query = "SELECT jr.*, u.username FROM group_join_requests jr
                     JOIN users u ON jr.user_id = u.id
                     WHERE jr.id = ? AND jr.status = 'pending'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                return ['status' => 'error', 'message' => 'Demande introuvable'];
            }
            
            // Vérifier si l'utilisateur est déjà membre
            if ($this->isUserInGroup($request['group_id'], $request['user_id'])) {
                // Mettre à jour la demande comme approuvée même si déjà membre
                $updateQuery = "UPDATE group_join_requests SET status = 'approved', processed_at = NOW() WHERE id = ?";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->execute([$requestId]);
                return ['status' => 'error', 'message' => 'Cet utilisateur est déjà membre du groupe'];
            }
            
            // Vérifier si le nom existe déjà dans le groupe
            $memberName = $request['username'];
            if ($this->isMemberNameInGroup($request['group_id'], $memberName)) {
                // Le nom existe déjà, proposer un nom alternatif
                $counter = 2;
                $originalName = $memberName;
                while ($this->isMemberNameInGroup($request['group_id'], $memberName)) {
                    $memberName = $originalName . '_' . $counter;
                    $counter++;
                }
                // Utiliser le nom modifié
            }
            
            $this->conn->beginTransaction();
            
            // Ajouter le membre au groupe avec le nom (éventuellement modifié)
            $addQuery = "INSERT INTO group_members (group_id, user_id, member_name, status) 
                        VALUES (?, ?, ?, 'active')";
            $addStmt = $this->conn->prepare($addQuery);
            $addStmt->execute([$request['group_id'], $request['user_id'], $memberName]);
            
            // Créer une période de séjour par défaut si nécessaire
            $this->createDefaultStayPeriodForMember($request['group_id'], $memberName);
            
            // Mettre à jour le statut de la demande
            $updateQuery = "UPDATE group_join_requests SET status = 'approved', processed_at = NOW() WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([$requestId]);
            
            // Mettre à jour le statut de l'utilisateur si c'est un visiteur
            $userQuery = "UPDATE users SET status = 'utilisateur' WHERE id = ? AND status = 'visiteur'";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->execute([$request['user_id']]);
            
            $this->conn->commit();
            
            $message = 'Demande approuvée avec succès';
            if ($memberName !== $request['username']) {
                $message .= '. Le membre a été ajouté sous le nom "' . $memberName . '" car le nom "' . $request['username'] . '" était déjà utilisé.';
            }
            
            return ['status' => 'success', 'message' => $message];
        } catch(PDOException $e) {
            $this->conn->rollback();
            error_log("Erreur approbation demande: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    // NOUVEAU V4.3 : Rejeter une demande d'intégration
    public function rejectJoinRequest($requestId) {
        try {
            $query = "UPDATE group_join_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$requestId])) {
                return ['status' => 'success', 'message' => 'Demande rejetée'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors du rejet'];
        } catch(PDOException $e) {
            error_log("Erreur rejet demande: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    // NOUVEAU V4.3 : Vérifier si l'utilisateur a une demande en attente
    public function hasPendingRequest($groupId, $userId) {
        try {
            $query = "SELECT COUNT(*) FROM group_join_requests WHERE group_id = ? AND user_id = ? AND status = 'pending'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$groupId, $userId]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification demande: " . $e->getMessage());
            return false;
        }
    }
    
    // NOUVEAU V4.3 : Récupérer les groupes pour un utilisateur (avec filtre)
    public function getGroupsForUser($userId, $onlyMemberGroups = false) {
        try {
            if ($onlyMemberGroups) {
                // Seulement les groupes où l'utilisateur est membre
                $query = "SELECT DISTINCT g.*, u.username as creator_name,
                                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                                1 as is_member,
                                (g.created_by = ?) as is_creator
                         FROM groups_table g
                         LEFT JOIN users u ON g.created_by = u.id
                         INNER JOIN group_members gm ON g.id = gm.group_id
                         WHERE gm.user_id = ?
                         ORDER BY g.name";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$userId, $userId]);
            } else {
                // Tous les groupes avec info d'appartenance
                $query = "SELECT g.*, u.username as creator_name,
                                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND user_id = ?) as is_member,
                                (g.created_by = ?) as is_creator
                         FROM groups_table g
                         LEFT JOIN users u ON g.created_by = u.id
                         ORDER BY g.name";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$userId, $userId]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Erreur récupération groupes utilisateur: " . $e->getMessage());
            return [];
        }
    }
    
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
                $this->createDefaultStayPeriodsForMembers($groupId, $startDate, $endDate);
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de la configuration'];
        } catch(PDOException $e) {
            error_log("Erreur configuration séjour: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
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
    
    public function updateMemberStayPeriod($groupId, $memberName, $startDate, $endDate, $coefficient) {
        try {
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
    
    public function disableStayMode($groupId) {
        try {
            $query = "UPDATE groups_table 
                     SET stay_mode_enabled = FALSE, stay_start_date = NULL, stay_end_date = NULL 
                     WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$groupId])) {
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
            $memberQuery = "SELECT member_name FROM group_members WHERE id = ? AND group_id = ?";
            $memberStmt = $this->conn->prepare($memberQuery);
            $memberStmt->execute([$memberId, $groupId]);
            $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($member) {
                $deleteStayQuery = "DELETE FROM member_stay_periods WHERE group_id = ? AND member_name = ?";
                $deleteStayStmt = $this->conn->prepare($deleteStayQuery);
                $deleteStayStmt->execute([$groupId, $member['member_name']]);
            }
            
            $query = "DELETE FROM group_members WHERE id = ? AND group_id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$memberId, $groupId]);
        } catch(PDOException $e) {
            error_log("Erreur suppression membre: " . $e->getMessage());
            return false;
        }
    }
    
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
            // Vérifier d'abord si le nom existe déjà dans le groupe (prioritaire)
            if($this->isMemberNameInGroup($groupId, $memberName)) {
                if($userId !== null) {
                    // Un utilisateur essaie de rejoindre avec un nom déjà pris
                    return ['status' => 'conflict', 'action' => 'link_existing'];
                } else {
                    // Tentative d'ajout d'un nom déjà existant
                    return ['status' => 'error', 'message' => 'Ce nom est déjà utilisé dans ce groupe'];
                }
            }
            
            // Vérifier si l'utilisateur est déjà membre du groupe
            if($userId !== null && $this->isUserInGroup($groupId, $userId)) {
                return ['status' => 'error', 'message' => 'Cet utilisateur est déjà membre du groupe'];
            }
            
            // Tout est OK, ajouter le membre
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