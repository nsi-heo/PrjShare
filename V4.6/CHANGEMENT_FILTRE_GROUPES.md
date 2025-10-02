# Changement : Comportement par défaut du filtre de groupes

## Modification apportée

Inversion du comportement par défaut de la checkbox de filtrage des groupes.

### Avant
- Par défaut : Tous les groupes visibles
- Checkbox cochée : Uniquement mes groupes
- Label : "Afficher uniquement mes groupes"

### Après
- Par défaut : **Uniquement mes groupes** ✓
- Checkbox cochée : Tous les groupes
- Label : "Afficher tous les groupes (y compris ceux où je ne suis pas membre)"

## Raison du changement

L'utilisateur moyen veut d'abord voir **ses groupes**, puis éventuellement explorer les autres.

Ce comportement est plus intuitif et correspond aux attentes standards :
- Gmail : mes emails par défaut
- Trello : mes boards par défaut
- Slack : mes channels par défaut

## Impact par rôle

### Visiteur
**Par défaut :** Aucun groupe (il n'est membre de rien)
**Si checkbox cochée :** Tous les groupes (en lecture seule)

### Utilisateur
**Par défaut :** Uniquement ses groupes
**Si checkbox cochée :** Tous les groupes (peut demander l'accès aux autres)

### Administrateur
**Toujours :** Tous les groupes visibles (pas de filtre)
**Raison :** L'admin doit tout voir pour gérer

## Modification technique

### Paramètre URL
- Avant : `?only_my_groups=1`
- Après : `?show_all=1`

### Logique
```php
// AVANT
$onlyMyGroups = isset($_GET['only_my_groups']) && $_GET['only_my_groups'] == '1';
$groups = getGroupsForUser($userId, $onlyMyGroups);

// APRÈS
$showAllGroups = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$onlyMyGroups = !$showAllGroups; // Inversé
$groups = getGroupsForUser($userId, $onlyMyGroups);
```

## Interface utilisateur

### Label de la checkbox
```
☐ Afficher tous les groupes (y compris ceux où je ne suis pas membre)
```

**État décoché (défaut) :** Mes groupes uniquement
**État coché :** Tous les groupes

## Cas d'usage

### Cas 1 : Nouvel utilisateur arrive

1. Se connecte pour la première fois
2. **Voit une liste vide** → Normal, il n'est dans aucun groupe
3. Coche la checkbox
4. Voit tous les groupes disponibles
5. Demande l'accès à un groupe

### Cas 2 : Utilisateur actif

1. Se connecte
2. **Voit immédiatement ses 3 groupes** (Vacances, Colocation, Sport)
3. Peut travailler directement sans être distrait par les autres groupes
4. Si besoin, coche la checkbox pour explorer d'autres groupes

### Cas 3 : Administrateur

1. Se connecte
2. **Voit tous les groupes** (pas de checkbox affichée)
3. Badge "Membre" ou "Créateur" sur chaque carte
4. Peut gérer tous les groupes

## Tests à effectuer

- [ ] Utilisateur déconnecté → redirigé vers login
- [ ] Utilisateur membre de 0 groupe → liste vide par défaut
- [ ] Utilisateur membre de 2 groupes → voir les 2 par défaut
- [ ] Cocher checkbox → voir tous les groupes
- [ ] Décocher checkbox → retour à mes groupes
- [ ] Rafraîchir page → état de la checkbox conservé via URL
- [ ] Admin → toujours tous les groupes, pas de checkbox
- [ ] Badges "Membre" / "Non membre" corrects

## URLs de test

```bash
# Par défaut (mes groupes)
http://votre-site/dashboard.php

# Tous les groupes
http://votre-site/dashboard.php?show_all=1

# Retour aux miens (supprimer paramètre)
http://votre-site/dashboard.php
```

## Rétrocompatibilité

### Ancien paramètre `only_my_groups`
Si quelqu'un a bookmarké l'ancienne URL :
```
http://site.com/dashboard.php?only_my_groups=1
```

**Comportement :** Le paramètre est ignoré, comportement par défaut appliqué

**Solution si nécessaire :**
```php
// Gérer l'ancien paramètre pour rétrocompatibilité
if(isset($_GET['only_my_groups']) && $_GET['only_my_groups'] == '1') {
    // Rediriger vers le nouveau comportement par défaut
    header('Location: dashboard.php');
    exit;
}
```

## Messages d'aide

Ajouter un texte d'aide sous la checkbox pour les nouveaux utilisateurs :

```html
<div class="filter-section">
    <label>
        <input type="checkbox" id="filter-toggle">
        Afficher tous les groupes (y compris ceux où je ne suis pas membre)
    </label>
    <p style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem;">
        💡 Par défaut, seuls vos groupes sont affichés. 
        Cochez cette case pour voir tous les groupes disponibles.
    </p>
</div>
```

## Statistiques affichées

Mettre à jour le texte d'en-tête :

### Avant
```
Mes groupes
3 groupe(s) dont vous êtes membre
```

### Après (checkbox décochée)
```
Mes groupes
Vous êtes membre de 3 groupe(s)
```

### Après (checkbox cochée)
```
Tous les groupes
Vous êtes membre de 3 sur 8 groupe(s)
```

## Fichier modifié

- `dashboard.php` - Logique inversée et nouveau label

## Déploiement

1. Sauvegarder `dashboard.php` actuel
2. Remplacer par la nouvelle version
3. Tester les 3 rôles (visiteur, utilisateur, admin)
4. Vérifier que la checkbox fonctionne correctement

**Temps estimé :** 5 minutes
**Risque :** Très faible
**Impact utilisateur :** Positif (plus intuitif)

---

**Version** : 4.3.6
**Date** : Inversion filtre par défaut
**Type** : Amélioration UX