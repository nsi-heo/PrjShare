<?php
// classes/User.php
class User {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($username, $password) {
        $query = "SELECT id, username, email, password, status FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    public function register($username, $email, $password) {
        $query = "INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 'visiteur')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
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
    
    public function deleteUser($userId) {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId]);
    }
}
?>