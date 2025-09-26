<?php
// install.php - Script d'installation de la base de données v4.1
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if(!$db) {
    die("Erreur de connexion à la base de données. Vérifiez votre configuration.");
}

// Création des tables
$queries = [
    // Table users
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        status ENUM('visiteur', 'utilisateur', 'administrateur') DEFAULT 'visiteur',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Table groups_table avec colonnes pour le mode séjour
    "CREATE TABLE IF NOT EXISTS groups_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_by INT,
        stay_mode_enabled BOOLEAN DEFAULT FALSE,
        stay_start_date DATE NULL,
        stay_end_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id),
        INDEX idx_stay_dates (stay_start_date, stay_end_date)
    )",
    
    // Table group_members
    "CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT,
        user_id INT NULL,
        member_name VARCHAR(50) NOT NULL,
        status ENUM('pending', 'active') DEFAULT 'pending',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_group_member (group_id, member_name)
    )",
    
    // Table member_stay_periods (NOUVELLE)
    "CREATE TABLE IF NOT EXISTS member_stay_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        member_name VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        coefficient DECIMAL(5,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
        UNIQUE KEY unique_member_stay (group_id, member_name),
        INDEX idx_group_member_stay (group_id, member_name)
    )",
    
    // Table expenses avec colonne expense_mode
    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT,
        title VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        paid_by VARCHAR(50) NOT NULL,
        expense_mode ENUM('classique', 'sejour') DEFAULT 'classique',
        created_by INT,
        modified_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        modified_at TIMESTAMP NULL,
        FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_expenses_mode (group_id, expense_mode)
    )",
    
    // Table expense_participants
    "CREATE TABLE IF NOT EXISTS expense_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_id INT,
        member_name VARCHAR(50) NOT NULL,
        share DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
    )",
    
    // Utilisateur admin par défaut
    "INSERT IGNORE INTO users (username, email, password, status) VALUES 
    ('admin', 'admin@shareman.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'administrateur')"
];

// Migration pour les installations existantes
$migrationQueries = [
    // Ajouter les colonnes mode séjour si elles n'existent pas
    "ALTER TABLE groups_table 
     ADD COLUMN IF NOT EXISTS stay_mode_enabled BOOLEAN DEFAULT FALSE,
     ADD COLUMN IF NOT EXISTS stay_start_date DATE NULL,
     ADD COLUMN IF NOT EXISTS stay_end_date DATE NULL",
     
    // Ajouter l'index pour les dates de séjour
    "ALTER TABLE groups_table ADD INDEX IF NOT EXISTS idx_stay_dates (stay_start_date, stay_end_date)",
    
    // Ajouter la colonne expense_mode si elle n'existe pas
    "ALTER TABLE expenses 
     ADD COLUMN IF NOT EXISTS expense_mode ENUM('classique', 'sejour') DEFAULT 'classique'",
     
    // Ajouter l'index pour le mode des dépenses
    "ALTER TABLE expenses ADD INDEX IF NOT EXISTS idx_expenses_mode (group_id, expense_mode)",
    
    // Ajouter les colonnes modified_by et modified_at si elles n'existent pas
    "ALTER TABLE expenses 
     ADD COLUMN IF NOT EXISTS modified_by INT NULL,
     ADD COLUMN IF NOT EXISTS modified_at TIMESTAMP NULL",
     
    // Ajouter la contrainte de clé étrangère pour modified_by
    "ALTER TABLE expenses 
     ADD CONSTRAINT IF NOT EXISTS fk_expenses_modified_by 
     FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL"
];

echo "<h1>🚀 Installation Shareman v4.1</h1>";
echo "<p><strong>Nouveau :</strong> Mode séjour avec coefficients de participation et périodes personnalisées !</p>";
echo "<hr>";

// Exécuter les requêtes principales
echo "<h2>📦 Création des tables...</h2>";
foreach($queries as $i => $query) {
    try {
        $db->exec($query);
        echo "<div style='color: green;'>✓ Table " . ($i + 1) . " créée avec succès</div>";
    } catch(PDOException $e) {
        echo "<div style='color: orange;'>⚠ Table " . ($i + 1) . ": " . $e->getMessage() . "</div>";
    }
}

// Exécuter les migrations
echo "<h2>🔄 Migration des données existantes...</h2>";
foreach($migrationQueries as $i => $query) {
    try {
        $db->exec($query);
        echo "<div style='color: green;'>✓ Migration " . ($i + 1) . " appliquée avec succès</div>";
    } catch(PDOException $e) {
        echo "<div style='color: orange;'>⚠ Migration " . ($i + 1) . ": " . $e->getMessage() . "</div>";
    }
}

echo "<hr>";
echo "<h2>✅ Installation terminée !</h2>";

// Vérifier les fonctionnalités
echo "<h3>🔧 Vérification des fonctionnalités :</h3>";
echo "<ul>";
echo "<li><strong>Mode classique :</strong> Partage égal des dépenses entre participants ✓</li>";
echo "<li><strong>Mode séjour :</strong> Calcul basé sur les coefficients et durées de séjour ✓</li>";
echo "<li><strong>Gestion des membres :</strong> Comptes liés ou non-liés ✓</li>";
echo "<li><strong>Périodes personnalisées :</strong> Chaque membre peut avoir sa propre période ✓</li>";
echo "</ul>";

echo "<h3>👤 Compte administrateur :</h3>";
echo "<div style='background: #f0f8ff; padding: 1rem; border-radius: 5px; border-left: 4px solid #0066cc;'>";
echo "<strong>Nom d'utilisateur :</strong> admin<br>";
echo "<strong>Mot de passe :</strong> admin123<br>";
echo "<em>Changez ce mot de passe après la première connexion !</em>";
echo "</div>";

echo "<h3>🎯 Nouvelles fonctionnalités v4.1 :</h3>";
echo "<ul>";
echo "<li><strong>Mode séjour :</strong> Activez/désactivez pour chaque groupe</li>";
echo "<li><strong>Coefficients de participation :</strong> Ajustez la part de chaque membre (0.1 à 10.0)</li>";
echo "<li><strong>Périodes personnalisées :</strong> Chaque membre peut avoir des dates différentes</li>";
echo "<li><strong>Calcul automatique :</strong> Les parts sont calculées proportionnellement</li>";
echo "<li><strong>Double comptabilité :</strong> Bilans séparés pour mode classique et séjour</li>";
echo "</ul>";

echo "<div style='margin-top: 2rem; padding: 1rem; background: #e8f5e8; border-radius: 5px;'>";
echo "<strong>🎉 Prêt à utiliser !</strong><br>";
echo "<a href='index.php' style='display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Accéder à l'application</a>";
echo "</div>";

// Afficher des statistiques si des données existent
try {
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $groupCount = $db->query("SELECT COUNT(*) FROM groups_table")->fetchColumn();
    $expenseCount = $db->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
    
    if ($userCount > 1 || $groupCount > 0 || $expenseCount > 0) {
        echo "<h3>📊 Statistiques actuelles :</h3>";
        echo "<ul>";
        echo "<li><strong>Utilisateurs :</strong> $userCount</li>";
        echo "<li><strong>Groupes :</strong> $groupCount</li>";
        echo "<li><strong>Dépenses :</strong> $expenseCount</li>";
        echo "</ul>";
    }
} catch(PDOException $e) {
    // Ignore les erreurs de stats
}
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem;
        line-height: 1.6;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #333;
        min-height: 100vh;
    }
    
    h1, h2, h3 {
        color: #2c3e50;
        margin-bottom: 1rem;
    }
    
    h1 {
        text-align: center;
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    
    div {
        margin: 0.5rem 0;
        padding: 0.5rem;
        border-radius: 4px;
    }
    
    ul {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    li {
        margin-bottom: 0.5rem;
    }
    
    hr {
        border: none;
        height: 2px;
        background: linear-gradient(90deg, transparent, #667eea, transparent);
        margin: 2rem 0;
    }
</style>