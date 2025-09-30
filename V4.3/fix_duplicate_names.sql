-- fix_duplicate_names.sql
-- Script pour ajouter une contrainte d'unicité sur les noms de membres par groupe
-- À exécuter après avoir corrigé les éventuels doublons existants

-- ============================================
-- 1. VÉRIFIER LES DOUBLONS EXISTANTS
-- ============================================

SELECT 
    group_id, 
    member_name, 
    COUNT(*) as nombre_doublons
FROM group_members
GROUP BY group_id, member_name
HAVING COUNT(*) > 1;

-- Si des doublons existent, les afficher avec détails
SELECT 
    gm.id,
    gm.group_id,
    g.name as nom_groupe,
    gm.member_name,
    gm.user_id,
    u.username,
    gm.joined_at
FROM group_members gm
LEFT JOIN groups_table g ON gm.group_id = g.id
LEFT JOIN users u ON gm.user_id = u.id
WHERE (gm.group_id, gm.member_name) IN (
    SELECT group_id, member_name
    FROM group_members
    GROUP BY group_id, member_name
    HAVING COUNT(*) > 1
)
ORDER BY gm.group_id, gm.member_name, gm.joined_at;

-- ============================================
-- 2. CORRIGER LES DOUBLONS (SI NÉCESSAIRE)
-- ============================================

-- Option A : Renommer les doublons automatiquement
-- ATTENTION : À adapter selon vos besoins
/*
UPDATE group_members gm1
SET member_name = CONCAT(
    gm1.member_name, 
    '_', 
    (SELECT COUNT(*) + 1 
     FROM group_members gm2 
     WHERE gm2.group_id = gm1.group_id 
       AND gm2.member_name = gm1.member_name 
       AND gm2.id < gm1.id)
)
WHERE (gm1.group_id, gm1.member_name) IN (
    SELECT group_id, member_name
    FROM (
        SELECT group_id, member_name
        FROM group_members
        GROUP BY group_id, member_name
        HAVING COUNT(*) > 1
    ) as duplicates
);
*/

-- Option B : Supprimer les doublons en gardant le plus ancien
-- ATTENTION : Cela supprime définitivement des données !
/*
DELETE gm1 FROM group_members gm1
INNER JOIN group_members gm2 
WHERE gm1.group_id = gm2.group_id
  AND gm1.member_name = gm2.member_name
  AND gm1.id > gm2.id;
*/

-- ============================================
-- 3. AJOUTER LA CONTRAINTE D'UNICITÉ
-- ============================================

-- Vérifier si la contrainte existe déjà
SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'group_members'
  AND CONSTRAINT_NAME = 'unique_member_per_group';

-- Ajouter la contrainte unique sur (group_id, member_name)
ALTER TABLE group_members
ADD CONSTRAINT unique_member_per_group 
UNIQUE (group_id, member_name);

-- ============================================
-- 4. VÉRIFICATION POST-MIGRATION
-- ============================================

-- Vérifier que la contrainte a été ajoutée
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'group_members'
  AND CONSTRAINT_NAME = 'unique_member_per_group';

-- Vérifier qu'il n'y a plus de doublons
SELECT 
    group_id, 
    member_name, 
    COUNT(*) as nombre
FROM group_members
GROUP BY group_id, member_name
HAVING COUNT(*) > 1;

-- Si le résultat est vide, c'est parfait !

SELECT 'Contrainte d\'unicité ajoutée avec succès!' AS status;