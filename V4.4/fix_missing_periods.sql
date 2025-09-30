-- fix_missing_periods.sql
-- Script SQL pour créer les périodes de séjour manquantes

-- ============================================
-- 1. DIAGNOSTIC : Identifier les membres sans période
-- ============================================

SELECT 
    'DIAGNOSTIC : Membres sans période de séjour dans les groupes en mode séjour' as Information;

SELECT 
    g.id as group_id,
    g.name as nom_groupe,
    gm.id as member_id,
    gm.member_name,
    g.stay_start_date,
    g.stay_end_date,
    'PERIODE MANQUANTE' as statut
FROM groups_table g
INNER JOIN group_members gm ON g.id = gm.group_id
LEFT JOIN member_stay_periods msp ON g.id = msp.group_id AND gm.member_name = msp.member_name
WHERE g.stay_mode_enabled = TRUE
  AND msp.id IS NULL
ORDER BY g.id, gm.member_name;

-- ============================================
-- 2. CORRECTION : Créer les périodes manquantes
-- ============================================

INSERT INTO member_stay_periods (group_id, member_name, start_date, end_date, coefficient)
SELECT 
    g.id as group_id,
    gm.member_name,
    g.stay_start_date,
    g.stay_end_date,
    1.00 as coefficient
FROM groups_table g
INNER JOIN group_members gm ON g.id = gm.group_id
LEFT JOIN member_stay_periods msp ON g.id = msp.group_id AND gm.member_name = msp.member_name
WHERE g.stay_mode_enabled = TRUE
  AND msp.id IS NULL;

-- ============================================
-- 3. VÉRIFICATION : Compter les périodes créées
-- ============================================

SELECT 
    CONCAT('✓ ', ROW_COUNT(), ' période(s) de séjour créée(s)') as Resultat;

-- ============================================
-- 4. VÉRIFICATION FINALE : Plus de périodes manquantes
-- ============================================

SELECT 
    'VERIFICATION : Contrôle après correction' as Information;

SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ OK - Aucune période manquante'
        ELSE CONCAT('✗ ATTENTION - ', COUNT(*), ' période(s) encore manquante(s)')
    END as Resultat
FROM groups_table g
INNER JOIN group_members gm ON g.id = gm.group_id
LEFT JOIN member_stay_periods msp ON g.id = msp.group_id AND gm.member_name = msp.member_name
WHERE g.stay_mode_enabled = TRUE
  AND msp.id IS NULL;

-- ============================================
-- 5. STATISTIQUES POST-CORRECTION
-- ============================================

SELECT 
    'STATISTIQUES : État actuel du système' as Information;

SELECT 
    g.id,
    g.name as nom_groupe,
    COUNT(DISTINCT gm.id) as nb_membres,
    COUNT(DISTINCT msp.id) as nb_periodes,
    CASE 
        WHEN COUNT(DISTINCT gm.id) = COUNT(DISTINCT msp.id) THEN '✓ OK'
        ELSE '✗ Incohérent'
    END as coherence
FROM groups_table g
LEFT JOIN group_members gm ON g.id = gm.group_id
LEFT JOIN member_stay_periods msp ON g.id = msp.group_id AND gm.member_name = msp.member_name
WHERE g.stay_mode_enabled = TRUE
GROUP BY g.id, g.name
ORDER BY g.name;

-- ============================================
-- 6. DÉTAIL DES PÉRIODES CRÉÉES
-- ============================================

SELECT 
    'DETAIL : Périodes de séjour par groupe' as Information;

SELECT 
    g.id as group_id,
    g.name as nom_groupe,
    msp.member_name,
    msp.start_date,
    msp.end_date,
    msp.coefficient,
    DATEDIFF(msp.end_date, msp.start_date) + 1 as nb_jours,
    (DATEDIFF(msp.end_date, msp.start_date) + 1) * msp.coefficient as jours_ponderes
FROM groups_table g
INNER JOIN member_stay_periods msp ON g.id = msp.group_id
WHERE g.stay_mode_enabled = TRUE
ORDER BY g.id, msp.member_name;

-- ============================================
-- REQUÊTES UTILES POUR LE DEBUG
-- ============================================

-- Voir tous les groupes en mode séjour
/*
SELECT 
    id,
    name,
    stay_start_date,
    stay_end_date,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as nb_membres,
    (SELECT COUNT(*) FROM member_stay_periods WHERE group_id = g.id) as nb_periodes
FROM groups_table g
WHERE stay_mode_enabled = TRUE;
*/

-- Supprimer UNE période spécifique (en cas d'erreur)
/*
DELETE FROM member_stay_periods 
WHERE group_id = ? AND member_name = ?;
*/

-- Supprimer TOUTES les périodes d'un groupe (pour recommencer)
/*
DELETE FROM member_stay_periods WHERE group_id = ?;
*/