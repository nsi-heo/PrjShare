<?php
// fix_missing_stay_periods.php
// Script pour détecter et corriger les membres sans période de séjour
// À exécuter une seule fois après la correction

require_once 'config/database.php';
require_once 'classes/Group.php';

$database = new Database();
$db = $database->getConnection();

if(!$db) {
    die("Erreur de connexion à la base de données.");
}

$groupManager = new Group($db);

// Récupérer tous les groupes avec mode séjour activé
$query = "SELECT id, name, stay_start_date, stay_end_date 
          FROM groups_table 
          WHERE stay_mode_enabled = TRUE";
$stmt = $db->prepare($query);
$stmt->execute();
$stayGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>🔍 Vérification des périodes de séjour</h1>";
echo "<hr>";

$totalFixed = 0;
$totalMembers = 0;

foreach($stayGroups as $group) {
    echo "<h2>Groupe : " . htmlspecialchars($group['name']) . "</h2>";
    echo "<p><strong>Période :</strong> " . date('d/m/Y', strtotime($group['stay_start_date'])) . 
         " au " . date('d/m/Y', strtotime($group['stay_end_date'])) . "</p>";
    
    // Récupérer tous les membres du groupe
    $members = $groupManager->getGroupMembers($group['id']);
    $totalMembers += count($members);
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 1rem 0;'>";
    echo "<tr style='background: #f3f4f6;'>
            <th>Membre</th>
            <th>Période de séjour</th>
            <th>Action</th>
          </tr>";
    
    foreach($members as $member) {
        $memberName = $member['member_name'];
        
        // Vérifier si une période existe
        $checkQuery = "SELECT * FROM member_stay_periods 
                      WHERE group_id = ? AND member_name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$group['id'], $memberName]);
        $period = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($memberName) . "</td>";
        
        if($period) {
            // Période existe
            echo "<td style='color: green;'>✓ Existe (du " . 
                 date('d/m/Y', strtotime($period['start_date'])) . 
                 " au " . date('d/m/Y', strtotime($period['end_date'])) . 
                 ", coef: " . $period['coefficient'] . ")</td>";
            echo "<td style='color: green;'>OK</td>";
        } else {
            // Période manquante - la créer
            echo "<td style='color: red;'>✗ Manquante</td>";
            
            try {
                $insertQuery = "INSERT INTO member_stay_periods 
                               (group_id, member_name, start_date, end_date, coefficient) 
                               VALUES (?, ?, ?, ?, 1.00)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->execute([
                    $group['id'], 
                    $memberName, 
                    $group['stay_start_date'], 
                    $group['stay_end_date']
                ]);
                
                echo "<td style='color: green;'>✓ Créée avec succès</td>";
                $totalFixed++;
            } catch(PDOException $e) {
                echo "<td style='color: red;'>✗ Erreur : " . htmlspecialchars($e->getMessage()) . "</td>";
            }
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";
echo "<h2>📊 Résumé</h2>";
echo "<ul>";
echo "<li><strong>Groupes avec mode séjour :</strong> " . count($stayGroups) . "</li>";
echo "<li><strong>Total de membres vérifiés :</strong> " . $totalMembers . "</li>";
echo "<li><strong>Périodes créées :</strong> " . $totalFixed . "</li>";
echo "</ul>";

if($totalFixed > 0) {
    echo "<div style='background: #d1fae5; border: 1px solid #10b981; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<strong>✅ Correction terminée !</strong><br>";
    echo $totalFixed . " période(s) de séjour ont été créée(s) pour les membres manquants.";
    echo "</div>";
} else {
    echo "<div style='background: #dbeafe; border: 1px solid #3b82f6; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<strong>ℹ️ Aucune correction nécessaire</strong><br>";
    echo "Tous les membres ont déjà leurs périodes de séjour.";
    echo "</div>";
}

echo "<p style='margin-top: 2rem;'>";
echo "<a href='dashboard.php' style='display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 8px;'>Retour au dashboard</a>";
echo "</p>";
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
        background: #f9fafb;
    }
    
    h1, h2 {
        color: #1f2937;
        margin-bottom: 1rem;
    }
    
    h1 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px;
        text-align: center;
    }
    
    table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    th {
        background: #f3f4f6;
        color: #374151;
        font-weight: 600;
        text-align: left;
    }
    
    td {
        border-bottom: 1px solid #e5e7eb;
    }
    
    hr {
        border: none;
        height: 2px;
        background: linear-gradient(90deg, transparent, #667eea, transparent);
        margin: 2rem 0;
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
</style>