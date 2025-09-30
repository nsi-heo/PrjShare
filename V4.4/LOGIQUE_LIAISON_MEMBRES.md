# Logique de liaison des membres lors de l'approbation

## Principe

Lorsqu'un utilisateur demande à intégrer un groupe, trois cas de figure peuvent se présenter selon l'existence ou non de son nom dans le groupe.

## Les 3 cas possibles

### CAS 1 : Nom disponible ✓

**Situation :** Le nom d'utilisateur n'existe pas dans le groupe

**Action :** Ajout normal du membre

**Résultat :**
```
Groupe avant : Alice, Bob
Demande : Charlie demande l'accès
Groupe après : Alice, Bob, Charlie (lié au compte)
```

**Message :** "Demande approuvée avec succès"

---

### CAS 2 : Nom existe mais membre non-lié ✓

**Situation :** Un membre avec ce nom existe mais n'est pas lié à un compte utilisateur

**Action :** Lier le membre existant au compte utilisateur (UPDATE au lieu d'INSERT)

**Résultat :**
```
Groupe avant : 
  - Alice (liée)
  - Bob (non-lié) ← membre sans compte
  
Demande : Bob (avec compte) demande l'accès

Groupe après :
  - Alice (liée)
  - Bob (lié) ← MÊME membre, maintenant lié au compte
```

**Avantages :**
- Conserve l'historique des dépenses du membre "Bob"
- Bob récupère ses bilans existants
- Pas de doublon
- Les dépenses où "Bob" a participé restent valides

**Message :** "Demande approuvée avec succès. Le compte a été lié au membre existant 'Bob'."

---

### CAS 3 : Nom existe ET membre déjà lié ✗

**Situation :** Un membre avec ce nom existe ET est déjà lié à un autre compte

**Action :** REJETER la demande

**Résultat :**
```
Groupe : 
  - Alice (liée au compte alice@mail.com)
  - Bob (lié au compte bob@mail.com)
  
Demande : Bob (compte bob2@mail.com) demande l'accès

Résultat : REJET de la demande
```

**Raison :** On ne peut pas avoir deux comptes différents pour le même nom. L'utilisateur doit s'inscrire avec un autre nom.

**Message :** "Le nom 'Bob' est déjà utilisé par un membre lié à un compte. L'utilisateur doit s'inscrire avec un autre nom d'utilisateur."

**Action demandée :** L'utilisateur doit créer un nouveau compte avec un nom différent (ex: Bob2, BobDupont, etc.)

## Schéma de décision

```
Demande d'intégration
    |
    v
Nom existe dans le groupe ?
    |
    ├─ NON ──────────────────────────────────> CAS 1: Ajouter normalement
    |
    └─ OUI ─> Membre lié à un compte ?
                |
                ├─ NON ──────────────────────> CAS 2: Lier au compte existant
                |
                └─ OUI ──────────────────────> CAS 3: REJETER la demande
```

## Exemples concrets

### Exemple 1 : Location de vacances

**Situation initiale :**
Groupe "Vacances Bretagne 2024"
- Jean Dupont (compte lié)
- Marie Martin (compte lié)
- Pierre Durand (NON lié) ← ajouté manuellement par l'admin car pas de compte

Pierre a des dépenses :
- 150€ de courses
- 80€ d'essence

**Pierre crée un compte et demande l'accès :**
- Admin approuve
- Le membre "Pierre Durand" est automatiquement lié au nouveau compte
- Pierre peut maintenant se connecter et voir ses 230€ de dépenses

**Avantage :** Pierre récupère son historique

---

### Exemple 2 : Conflit de noms

**Situation :**
Groupe "Colocation Paris"
- Sophie Dubois (liée à sophie.dubois@mail.com)
- Thomas Petit (lié à thomas.p@mail.com)

**Sophie Bernard s'inscrit avec le nom "Sophie" :**
- Elle demande l'accès au groupe
- ✗ REJET : Le nom "Sophie" est déjà utilisé par Sophie Dubois

**Solution :** Sophie Bernard doit :
1. Créer un nouveau compte avec un nom différent : "SophieBernard" ou "Sophie2"
2. Redemander l'accès avec ce nouveau nom

---

### Exemple 3 : Membre provisoire devient membre réel

**Situation :**
Un admin crée un groupe et ajoute des membres manuellement :
- Admin (lié)
- PartantVoyage1 (non-lié) ← nom provisoire
- PartantVoyage2 (non-lié) ← nom provisoire

Plus tard, "Marc Leroy" s'inscrit :
- L'admin lui dit d'utiliser le nom "PartantVoyage1"
- Marc s'inscrit avec username "PartantVoyage1"
- Marc demande l'accès
- Admin approuve
- "PartantVoyage1" devient lié au compte de Marc

**Alternative plus propre :**
- Marc s'inscrit avec "Marc Leroy"
- Admin ajoute manuellement "Marc Leroy" au groupe
- Pas de membre provisoire nécessaire

## Avantages de cette logique

### 1. Conservation des données
Quand un membre non-lié est lié à un compte :
- ✓ Toutes ses dépenses sont conservées
- ✓ Ses bilans restent valides
- ✓ Son historique de paiements est préservé
- ✓ Ses périodes de séjour sont maintenues

### 2. Pas de doublons
- Un nom = Une personne dans le groupe
- Évite "Bob" et "Bob (utilisateur)"
- Évite la confusion dans les bilans

### 3. Protection des comptes
- Un membre lié ne peut pas être "volé" par quelqu'un d'autre
- Obligation de s'inscrire avec un nom unique

## Impact sur les dépenses

### Mode classique
Les dépenses en mode classique sont liées à une liste de participants spécifique.
- Si "Bob" non-lié participe à une dépense
- Puis "Bob" devient lié
- La dépense reste valide et "Bob" y participe toujours

### Mode séjour
Les dépenses en mode séjour utilisent les périodes de séjour.
- Si "Bob" non-lié a une période de séjour
- Puis "Bob" devient lié
- Sa période reste active
- Les bilans se recalculent automatiquement avec "Bob" lié

## Messages pour les utilisateurs

### Pour l'administrateur

**CAS 1 (nom disponible) :**
```
Demande approuvée avec succès
```

**CAS 2 (liaison) :**
```
Demande approuvée avec succès. 
Le compte a été lié au membre existant "Bob".
```

**CAS 3 (rejet) :**
```
Le nom "Bob" est déjà utilisé par un membre lié à un compte. 
L'utilisateur doit s'inscrire avec un autre nom d'utilisateur.
```

### Pour l'utilisateur dont la demande est rejetée

```
Votre demande d'intégration au groupe "Vacances 2024" a été rejetée.

Raison : Le nom d'utilisateur "Bob" est déjà utilisé par un autre membre du groupe.

Action requise : 
1. Créez un nouveau compte avec un nom d'utilisateur différent
   (exemple : Bob2, BobMartin, etc.)
2. Faites une nouvelle demande d'intégration avec ce nouveau nom

Si vous pensez qu'il s'agit d'une erreur, contactez l'administrateur du groupe.
```

## Tests à effectuer

### Test 1 : Nom disponible
1. Créer groupe avec Alice, Bob
2. Charlie demande l'accès
3. Admin approuve
4. ✓ Charlie ajouté au groupe

### Test 2 : Liaison membre non-lié
1. Créer groupe avec Alice, Bob (non-lié)
2. Bob crée un compte
3. Bob demande l'accès
4. Admin approuve
5. ✓ Bob lié au compte (pas de nouveau membre créé)
6. ✓ Dépenses de Bob conservées

### Test 3 : Rejet membre déjà lié
1. Créer groupe avec Alice, Bob (lié à bob@mail.com)
2. Bob2 s'inscrit avec username "Bob"
3. Bob2 demande l'accès
4. Admin approuve
5. ✓ Demande rejetée automatiquement
6. ✓ Message expliquant qu'il doit changer de nom

### Test 4 : Vérification données conservées
1. Créer groupe avec Alice, Bob (non-lié)
2. Créer dépense de 100€ payée par Bob
3. Bob crée un compte et demande l'accès
4. Admin approuve
5. ✓ Bob se connecte et voit la dépense de 100€
6. ✓ Bilan de Bob correct

## Requêtes SQL utiles

### Trouver les membres non-liés
```sql
SELECT 
    g.name as groupe,
    gm.member_name,
    gm.user_id
FROM group_members gm
JOIN groups_table g ON gm.group_id = g.id
WHERE gm.user_id IS NULL
ORDER BY g.name, gm.member_name;
```

### Trouver les conflits potentiels
```sql
-- Utilisateurs avec même nom qu'un membre lié
SELECT 
    u.username,
    u.email,
    g.name as groupe_avec_conflit
FROM users u
CROSS JOIN groups_table g
WHERE EXISTS (
    SELECT 1 FROM group_members gm
    WHERE gm.group_id = g.id
    AND gm.member_name = u.username
    AND gm.user_id IS NOT NULL
    AND gm.user_id != u.id
);
```

### Historique des liaisons (via demandes)
```sql
SELECT 
    jr.id,
    u.username,
    g.name as groupe,
    jr.status,
    jr.created_at,
    jr.processed_at
FROM group_join_requests jr
JOIN users u ON jr.user_id = u.id
JOIN groups_table g ON jr.group_id = g.id
WHERE jr.status IN ('approved', 'rejected')
ORDER BY jr.processed_at DESC;
```

## Modification de l'interface

### Dans admin.php - Indicateur de type de liaison

Ajouter une colonne dans la liste des demandes :

```php
<td>
    <?php 
    // Vérifier le type de conflit
    $checkQuery = "SELECT user_id FROM group_members 
                   WHERE group_id = ? AND member_name = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$request['group_id'], $request['username']]);
    $existing = $checkStmt->fetch();
    
    if(!$existing) {
        echo '<span style="color: green;">✓ Nom disponible</span>';
    } elseif($existing['user_id'] === null) {
        echo '<span style="color: blue;">🔗 Liaison possible</span>';
    } else {
        echo '<span style="color: red;">✗ Nom déjà lié</span>';
    }
    ?>
</td>
```

### Dans request_join.php - Avertissement utilisateur

Si le nom existe déjà comme membre lié :

```php
<?php
$nameCheck = $groupManager->isMemberNameInGroup($groupId, $_SESSION['username']);
if($nameCheck) {
    // Vérifier si lié ou non
    $query = "SELECT user_id FROM group_members 
              WHERE group_id = ? AND member_name = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$groupId, $_SESSION['username']]);
    $existing = $stmt->fetch();
    
    if($existing && $existing['user_id'] !== null) {
        ?>
        <div class="alert alert-warning">
            ⚠️ Attention : Le nom "<?= htmlspecialchars($_SESSION['username']) ?>" 
            est déjà utilisé dans ce groupe par un compte lié.
            Votre demande sera probablement rejetée.
            
            Vous devriez créer un nouveau compte avec un nom différent.
        </div>
        <?php
    }
}
?>
```

## Gestion des notifications (futur)

### Email de rejet avec explication

```
Objet : Votre demande d'intégration au groupe "Vacances 2024"

Bonjour Bob,

Votre demande d'intégration au groupe "Vacances 2024" n'a pas pu être acceptée.

RAISON : Le nom d'utilisateur "Bob" est déjà utilisé par un membre du groupe 
qui possède un compte lié.

QUE FAIRE ?
1. Créez un nouveau compte avec un nom différent
   Exemples : Bob2, BobDupont, BobMartin, etc.
   
2. Faites une nouvelle demande avec ce nouveau compte

3. L'administrateur pourra alors approuver votre demande

Si vous avez des questions, contactez l'administrateur du groupe.

Cordialement,
L'équipe Shareman
```

## Flux détaillé avec base de données

### CAS 2 : Liaison membre non-lié

**État initial :**
```sql
-- Table group_members
| id | group_id | user_id | member_name |
|----|----------|---------|-------------|
| 1  | 10       | 5       | Alice       |
| 2  | 10       | NULL    | Bob         |  ← Membre non-lié

-- Table users
| id | username | email          |
|----|----------|----------------|
| 5  | Alice    | alice@mail.com |
| 7  | Bob      | bob@mail.com   |
```

**Action : Bob (user_id=7) demande l'accès**

**Requête exécutée :**
```sql
UPDATE group_members 
SET user_id = 7 
WHERE id = 2;
```

**État final :**
```sql
-- Table group_members
| id | group_id | user_id | member_name |
|----|----------|---------|-------------|
| 1  | 10       | 5       | Alice       |
| 2  | 10       | 7       | Bob         |  ← Maintenant lié
```

**Résultat :** Même ligne, juste mise à jour du user_id

---

### CAS 3 : Rejet membre déjà lié

**État :**
```sql
-- Table group_members
| id | group_id | user_id | member_name |
|----|----------|---------|-------------|
| 1  | 10       | 5       | Alice       |
| 2  | 10       | 7       | Bob         |  ← Déjà lié à user_id=7

-- Table users
| id | username | email           |
|----|----------|-----------------|
| 5  | Alice    | alice@mail.com  |
| 7  | Bob      | bob@mail.com    |
| 9  | Bob      | bob2@mail.com   |  ← Autre compte, même nom
```

**Action : Bob (user_id=9) demande l'accès**

**Résultat :** REJET automatique

**Requête exécutée :**
```sql
UPDATE group_join_requests 
SET status = 'rejected', processed_at = NOW() 
WHERE id = ?;
```

**Message :** Nom déjà utilisé par un membre lié

## Points d'attention

### 1. Dépenses existantes
Quand un membre non-lié devient lié, ses dépenses restent valides car elles sont liées au `member_name`, pas au `user_id`.

### 2. Périodes de séjour
Les périodes de séjour sont liées au `member_name`. Elles restent donc actives après liaison.

### 3. Pas de perte de données
La liaison préserve tout l'historique du membre non-lié.

### 4. Sécurité
Un membre lié ne peut pas être "pris" par quelqu'un d'autre. Protection contre l'usurpation d'identité.

## Récapitulatif

| Cas | Nom dans groupe ? | Membre lié ? | Action | Résultat |
|-----|------------------|--------------|--------|----------|
| 1   | NON              | -            | INSERT | Nouveau membre |
| 2   | OUI              | NON          | UPDATE | Liaison du membre existant |
| 3   | OUI              | OUI          | REJECT | Demande rejetée |

---

**Version** : V4.3.5  
**Date** : Logique de liaison intelligente  
**Impact** : Améliore la gestion des membres et évite les doublons