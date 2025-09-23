<?php
// classes/Group.php - Version corrigée
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
            // Log l'erreur si nécessaire
            error_log("Erreur création groupe: " . $e->getMessage());
            return false;
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
            return $stmt->execute([$groupId, $userId, $memberName]);
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
            return true; // En cas d'erreur, on considère que le nom existe pour éviter les doublons
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
            // Les suppressions en cascade sont gérées par les contraintes de clé étrangère
            $query = "DELETE FROM groups_table WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$groupId]);
        } catch(PDOException $e) {
            error_log("Erreur suppression groupe: " . $e->getMessage());
            return false;
        }
    }
    
    public function removeMemberFromGroup($memberId, $groupId) {
        try {
            $query = "DELETE FROM group_members WHERE id = ? AND group_id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$memberId, $groupId]);
        } catch(PDOException $e) {
            error_log("Erreur suppression membre: " . $e->getMessage());
            return false;
        }
    }
	
	public function linkMemberToUser($groupId, $memberName, $userId) {
    try {
        // Vérifier qu'un membre non-lié avec ce nom existe
        $query = "SELECT id FROM group_members WHERE group_id = ? AND member_name = ? AND user_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$groupId, $memberName]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($member) {
            // Lier le membre existant à l'utilisateur
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
        // Si on ajoute un utilisateur existant
        if($userId !== null) {
            // Vérifier s'il y a un membre non-lié avec le même nom
            if($this->hasUnlinkedMemberWithName($groupId, $memberName)) {
                // Proposer de lier le membre existant au lieu de créer un doublon
                return ['status' => 'conflict', 'action' => 'link_existing'];
            }
            
            // Vérifier que l'utilisateur n'est pas déjà membre
            if($this->isUserInGroup($groupId, $userId)) {
                return ['status' => 'error', 'message' => 'Cet utilisateur est déjà membre du groupe'];
            }
        }
        
        // Si on ajoute un nouveau nom sans compte
        if($userId === null) {
            // Vérifier que le nom n'existe pas déjà (lié ou non-lié)
            if($this->isMemberNameInGroup($groupId, $memberName)) {
                return ['status' => 'error', 'message' => 'Ce nom est déjà utilisé dans ce groupe'];
            }
        }
        
        // Ajouter le membre
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