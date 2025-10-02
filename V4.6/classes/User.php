<?php
// classes/User.php - V4.6 Correction complète
class User {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
	
	
    public function login($username, $password) {
        $query = "SELECT id, username, email, password, status, must_change_password FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    public function register($username, $email, $password) {
        // Vérifier si le username existe déjà
        if($this->usernameExists($username)) {
            return ['status' => 'error', 'message' => 'Ce nom d\'utilisateur existe déjà'];
        }
        
        // Vérifier si l'email existe déjà
        if($this->emailExists($email)) {
            return ['status' => 'error', 'message' => 'Cette adresse email est déjà utilisée'];
        }
        
        try {
            $query = "INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 'visiteur')";
            $stmt = $this->conn->prepare($query);
            
            if($stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)])) {
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de la création du compte'];
        } catch(PDOException $e) {
            error_log("Erreur inscription: " . $e->getMessage());
            
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if(strpos($e->getMessage(), 'email') !== false) {
                    return ['status' => 'error', 'message' => 'Cette adresse email est déjà utilisée'];
                }
                return ['status' => 'error', 'message' => 'Ce nom d\'utilisateur existe déjà'];
            }
            
            return ['status' => 'error', 'message' => 'Erreur système lors de l\'inscription'];
        }
    }
    
    public function emailExists($email, $excludeUserId = null) {
        try {
            if($excludeUserId) {
                $query = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$email, $excludeUserId]);
            } else {
                $query = "SELECT COUNT(*) FROM users WHERE email = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$email]);
            }
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification email: " . $e->getMessage());
            return true;
        }
    }
    
    public function usernameExists($username, $excludeUserId = null) {
        try {
            if($excludeUserId) {
                $query = "SELECT COUNT(*) FROM users WHERE username = ? AND id != ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$username, $excludeUserId]);
            } else {
                $query = "SELECT COUNT(*) FROM users WHERE username = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$username]);
            }
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Erreur vérification username: " . $e->getMessage());
            return true;
        }
    }
    
    public function getUserById($id) {
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllUsers() {
        $query = "SELECT id, username, email, status, created_at FROM users ORDER BY username";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateUserStatus($userId, $status) {
        $query = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$status, $userId]);
    }
    
    public function updateUserEmail($userId, $newEmail) {
        if($this->emailExists($newEmail, $userId)) {
            return ['status' => 'error', 'message' => 'Cette adresse email est déjà utilisée par un autre compte'];
        }
        
        try {
            $query = "UPDATE users SET email = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if($stmt->execute([$newEmail, $userId])) {
                return ['status' => 'success', 'message' => 'Email mis à jour avec succès'];
            }
            return ['status' => 'error', 'message' => 'Erreur lors de la mise à jour'];
        } catch(PDOException $e) {
            error_log("Erreur mise à jour email: " . $e->getMessage());
            
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['status' => 'error', 'message' => 'Cette adresse email est déjà utilisée'];
            }
            
            return ['status' => 'error', 'message' => 'Erreur système'];
        }
    }
    
    public function deleteUser($userId) {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId]);
    }
}
?>