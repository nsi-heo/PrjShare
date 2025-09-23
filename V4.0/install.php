// install.php - Script d'installation de la base de données
<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Création des tables
$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        status ENUM('visiteur', 'utilisateur', 'administrateur') DEFAULT 'visiteur',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS groups_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT,
        user_id INT NULL,
        member_name VARCHAR(50) NOT NULL,
        status ENUM('pending', 'active') DEFAULT 'pending',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT,
        title VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        paid_by VARCHAR(50) NOT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS expense_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_id INT,
        member_name VARCHAR(50) NOT NULL,
        share DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
    )",
    
    "INSERT IGNORE INTO users (username, email, password, status) VALUES 
    ('admin', 'admin@shareman.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'administrateur')"
	
	
	"ALTER TABLE expenses 
     ADD COLUMN IF NOT EXISTS modified_by INT NULL,
     ADD COLUMN IF NOT EXISTS modified_at TIMESTAMP NULL",
     
    "ALTER TABLE expenses 
     ADD FOREIGN KEY IF NOT EXISTS (modified_by) REFERENCES users(id)"
	 
	 "ALTER TABLE expenses 
	  ADD CONSTRAINT fk_expenses_modified_by 
	  FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL;"
];

foreach($queries as $query) {
    try {
        $db->exec($query);
        echo "✓ Requête exécutée avec succès<br>";
    } catch(PDOException $e) {
        echo "✗ Erreur: " . $e->getMessage() . "<br>";
    }
}

echo "<h3>Installation terminée !</h3>";
echo "<p>Compte admin créé: admin / admin123</p>";
echo "<a href='index.php'>Accéder à l'application</a>";
?>