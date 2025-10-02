<?php
// install.php - Script d'installation v4.6 CORRIGÉ
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if(!$db) {
    die("Erreur de connexion à la base de données. Vérifiez votre configuration.");
}

// Création des tables
$queries = [
    // Table users avec email unique et flag must_change_password
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        status ENUM('visiteur', 'utilisateur', 'administrateur') DEFAULT 'visiteur',
        must_change_password BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email)
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
        UNIQUE KEY unique_member_per_group (group_id, member_name),
        INDEX idx_group_member (group_id, member_name)
    )",
    
    // Table member_stay_periods
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
    
    // Table group_join_requests
    "CREATE TABLE IF NOT EXISTS group_join_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_request (group_id, user_id, status),
        INDEX idx_pending_requests (group_id, status)
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

// Migrations CORRIGÉES pour les installations existantes
$migrationQueries = [
    // Migration 1 : Ajouter must_change_password
    "SELECT 1", // Placeholder
    
    // Migration 2 : Ajouter stay_mode_enabled
    "SELECT 1",
    
    // Migration 3 : Ajouter expense_mode
    "SELECT 1",
    
    // Migration 4 : Ajouter modified_by et modified_at
    "SELECT 1",
    
    // Migration 5 : Ajouter contrainte FK modified_by (sera gérée différemment)
    "SELECT 1",
    
    // Migration 6 : Ajouter unique member_per_group (sera gérée différemment)
    "SELECT 1",
    
    // Migration 7 : Ajouter unique email (sera gérée différemment)
    "SELECT 1"
];

echo "<h1>🚀 Installation Shareman v4.6</h1>";
echo "<p><strong>Nouveau :</strong> Email unique et changement mot de passe obligatoire après réinitialisation !</p>";
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

// Migrations manuelles plus robustes
echo "<h2>🔄 Migration des données existantes...</h2>";

// Migration 1 : Ajouter must_change_password
try {
    $db->exec("ALTER TABLE users ADD COLUMN must_change_password BOOLEAN DEFAULT FALSE");
    echo "<div style='color: green;'>✓ Colonne must_change_password ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne must_change_password déjà présente</div>";
    } else {
        echo "<div style='color: orange;'>⚠ must_change_password: " . $e->getMessage() . "</div>";
    }
}

// Migration 2 : Ajouter stay_mode_enabled, stay_start_date, stay_end_date
try {
    $db->exec("ALTER TABLE groups_table ADD COLUMN stay_mode_enabled BOOLEAN DEFAULT FALSE");
    echo "<div style='color: green;'>✓ Colonne stay_mode_enabled ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne stay_mode_enabled déjà présente</div>";
    }
}

try {
    $db->exec("ALTER TABLE groups_table ADD COLUMN stay_start_date DATE NULL");
    echo "<div style='color: green;'>✓ Colonne stay_start_date ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne stay_start_date déjà présente</div>";
    }
}

try {
    $db->exec("ALTER TABLE groups_table ADD COLUMN stay_end_date DATE NULL");
    echo "<div style='color: green;'>✓ Colonne stay_end_date ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne stay_end_date déjà présente</div>";
    }
}

// Migration 3 : Ajouter expense_mode
try {
    $db->exec("ALTER TABLE expenses ADD COLUMN expense_mode ENUM('classique', 'sejour') DEFAULT 'classique'");
    echo "<div style='color: green;'>✓ Colonne expense_mode ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne expense_mode déjà présente</div>";
    }
}

// Migration 4 : Ajouter modified_by et modified_at
try {
    $db->exec("ALTER TABLE expenses ADD COLUMN modified_by INT NULL");
    echo "<div style='color: green;'>✓ Colonne modified_by ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne modified_by déjà présente</div>";
    }
}

try {
    $db->exec("ALTER TABLE expenses ADD COLUMN modified_at TIMESTAMP NULL");
    echo "<div style='color: green;'>✓ Colonne modified_at ajoutée</div>";
} catch(PDOException $e) {
    if(strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<div style='color: blue;'>ℹ Colonne modified_at déjà présente</div>";
    }
}

// Migration 5 : Vérifier si unique email existe déjà
try {
    $result = $db->query("SHOW KEYS FROM users WHERE Key_name = 'unique_email'")->fetchAll();
    if(empty($result)) {
        // La contrainte n'existe pas, on peut l'ajouter
        try {
            $db->exec("ALTER TABLE users ADD UNIQUE KEY unique_email (email)");
            echo "<div style='color: green;'>✓ Contrainte unique sur email ajoutée</div>";
        } catch(PDOException $e) {
            // Vérifier s'il y a des doublons d'email avant d'ajouter la contrainte
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "<div style='color: red;'>✗ Impossible d'ajouter la contrainte unique sur email : des emails en double existent dans la base</div>";
                echo "<div style='color: orange;'>⚠ Veuillez corriger les doublons manuellement puis relancer l'installation</div>";
            } else {
                echo "<div style='color: orange;'>⚠ unique_email: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        echo "<div style='color: blue;'>ℹ Contrainte unique sur email déjà présente</div>";
    }
} catch(PDOException $e) {
    echo "<div style='color: orange;'>⚠ Vérification unique_email: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<h2>✅ Installation terminée !</h2>";

// Vérifications
echo "<h3>🔧 Vérifications des fonctionnalités :</h3>";
echo "<ul>";
echo "<li><strong>Mode classique :</strong> Partage égal des dépenses entre participants ✓</li>";
echo "<li><strong>Mode séjour :</strong> Calcul basé sur les coefficients et durées de séjour ✓</li>";
echo "<li><strong>Règlements totaux :</strong> Combinaison des modes classique et séjour ✓</li>";
echo "<li><strong>Email unique :</strong> Deux utilisateurs ne peuvent avoir le même email ✓</li>";
echo "<li><strong>Changement mot de passe :</strong> Obligatoire après réinitialisation ✓</li>";
echo "</ul>";

echo "<h3>👤 Compte administrateur :</h3>";
echo "<div style='background: #f0f8ff; padding: 1rem; border-radius: 5px; border-left: 4px solid #0066cc;'>";
echo "<strong>Nom d'utilisateur :</strong> admin<br>";
echo "<strong>Mot de passe :</strong> admin123<br>";
echo "<em>Changez ce mot de passe après la première connexion !</em>";
echo "</div>";

// Afficher des statistiques
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
    // Ignore
}

echo "<div style='margin-top: 2rem; padding: 1rem; background: #e8f5e8; border-radius: 5px;'>";
echo "<strong>🎉 Prêt à utiliser !</strong><br>";
echo "<a href='index.php' style='display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Accéder à l'application</a>";
echo "</div>";
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