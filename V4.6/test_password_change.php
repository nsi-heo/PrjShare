<?php
require_once 'auth.php';
requireAuth();
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>Test changement mot de passe</h1>";

// Vérifier l'état actuel
$query = "SELECT id, username, must_change_password FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>État actuel :</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

// Test de mise à jour
if(isset($_GET['test']) && $_GET['test'] == '1') {
    echo "<h2>Tentative de mise à jour...</h2>";
    
    $newPassword = 'test123456';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updateQuery = "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    
    if($updateStmt->execute([$hashedPassword, $_SESSION['user_id']])) {
        echo "<p style='color: green;'>✓ Mise à jour réussie</p>";
        echo "<p>Lignes affectées : " . $updateStmt->rowCount() . "</p>";
        
        // Vérifier le résultat
        $checkStmt = $db->prepare($query);
        $checkStmt->execute([$_SESSION['user_id']]);
        $userAfter = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>État après mise à jour :</h3>";
        echo "<pre>";
        print_r($userAfter);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Échec de la mise à jour</p>";
        echo "<pre>";
        print_r($updateStmt->errorInfo());
        echo "</pre>";
    }
}

echo "<hr>";
echo "<a href='?test=1'>Lancer le test de mise à jour</a>";
?>