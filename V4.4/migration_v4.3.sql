-- migration_v4.3.sql
-- Script de migration de Shareman V4.1 vers V4.3
-- Date: 2024
-- IMPORTANT: Faire une sauvegarde avant d'exécuter ce script

-- ============================================
-- 1. CRÉATION DE LA NOUVELLE TABLE
-- ============================================

-- Table pour les demandes d'intégration aux groupes
CREATE TABLE IF NOT EXISTS group_join_requests (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. VÉRIFICATIONS
-- ============================================

-- Vérifier que toutes les tables nécessaires existent
SELECT 'Vérification des tables...' AS status;

SELECT 
    CASE 
        WHEN COUNT(*) = 6 THEN 'OK - Toutes les tables existent'
        ELSE 'ERREUR - Tables manquantes'
    END AS result
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
    AND table_name IN (
        'users', 
        'groups_table', 
        'group_members', 
        'member_stay_periods',
        'expenses', 
        'expense_participants'
    );

-- ============================================
-- 3. DONNÉES DE TEST (OPTIONNEL)
-- ============================================

-- Insérer quelques demandes de test (décommenter si besoin)
/*
INSERT INTO group_join_requests (group_id, user_id, status) VALUES
    (1, 2, 'pending'),
    (2, 3, 'pending');
*/

-- ============================================
-- 4. STATISTIQUES POST-MIGRATION
-- ============================================

SELECT 'Migration terminée - Statistiques:' AS status;

SELECT 
    (SELECT COUNT(*) FROM users) AS total_users,
    (SELECT COUNT(*) FROM users WHERE status = 'visiteur') AS visiteurs,
    (SELECT COUNT(*) FROM users WHERE status = 'utilisateur') AS utilisateurs,
    (SELECT COUNT(*) FROM users WHERE status = 'administrateur') AS administrateurs,
    (SELECT COUNT(*) FROM groups_table) AS total_groupes,
    (SELECT COUNT(*) FROM expenses) AS total_depenses,
    (SELECT COUNT(*) FROM group_join_requests WHERE status = 'pending') AS demandes_en_attente;

-- ============================================
-- 5. REQUÊTES UTILES POST-MIGRATION
-- ============================================

-- Voir toutes les demandes en attente avec détails
/*
SELECT 
    jr.id,
    u.username,
    u.email,
    u.status as statut_utilisateur,
    g.name as nom_groupe,
    jr.created_at as date_demande
FROM group_join_requests jr
JOIN users u ON jr.user_id = u.id
JOIN groups_table g ON jr.group_id = g.id
WHERE jr.status = 'pending'
ORDER BY jr.created_at DESC;
*/

-- Voir les groupes avec nombre de membres et demandes
/*
SELECT 
    g.id,
    g.name as nom_groupe,
    COUNT(DISTINCT gm.id) as nombre_membres,
    COUNT(DISTINCT jr.id) as demandes_en_attente
FROM groups_table g
LEFT JOIN group_members gm ON g.id = gm.group_id
LEFT JOIN group_join_requests jr ON g.id = jr.group_id AND jr.status = 'pending'
GROUP BY g.id, g.name
ORDER BY g.name;
*/

-- ============================================
-- 6. NETTOYAGE (OPTIONNEL)
-- ============================================

-- Supprimer les demandes rejetées de plus de 30 jours
/*
DELETE FROM group_join_requests 
WHERE status = 'rejected' 
    AND processed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
*/

-- Supprimer les demandes en attente de plus de 90 jours
/*
DELETE FROM group_join_requests 
WHERE status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
*/

-- ============================================
-- 7. ROLLBACK (EN CAS DE PROBLÈME)
-- ============================================

-- Si vous devez annuler la migration, exécutez:
/*
DROP TABLE IF EXISTS group_join_requests;
-- Puis restaurez votre sauvegarde
*/

-- ============================================
-- FIN DU SCRIPT DE MIGRATION V4.3
-- ============================================

SELECT 'Migration V4.3 terminée avec succès!' AS status;