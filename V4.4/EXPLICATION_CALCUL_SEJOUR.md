# Explication : Calcul des bilans en mode séjour

## 🎯 Principe du mode séjour

Le mode séjour permet de répartir les dépenses proportionnellement au temps de présence et à un coefficient de chaque membre.

### Exemple simplifié

**Séjour de 10 jours** - Dépenses totales : **100 €**

| Membre | Durée | Coefficient | Jours pondérés | Part |
|--------|-------|-------------|----------------|------|
| Alice  | 10j   | 1.0         | 10 × 1.0 = 10  | 33,33€ |
| Bob    | 10j   | 1.0         | 10 × 1.0 = 10  | 33,33€ |
| Charlie| 10j   | 1.0         | 10 × 1.0 = 10  | 33,33€ |
| **TOTAL** |    |             | **30**         | **100€** |

## 🐛 Le problème identifié

### Ancien algorithme (INCORRECT)

```php
$costPerDayPerCoefficient = $totalStayExpenses / $totalGroupDays / $totalCoefficients;
$memberShare = $costPerDayPerCoefficient * $memberDays * $coefficient;
```

**Problème** : Divise par `$totalGroupDays` (durée du séjour) ET par `$totalCoefficients` (somme des coefficients)

### Exemple montrant l'erreur

**Séjour de 10 jours** - Dépenses : **100 €** - 3 membres

**Avec l'ancien algorithme :**
```
costPerDay = 100 / 10 / 3 = 3,33€
Part Alice = 3,33 × 10 × 1.0 = 33,33€
Part Bob   = 3,33 × 10 × 1.0 = 33,33€
Part Charlie = 3,33 × 10 × 1.0 = 33,33€
TOTAL = 100€ ✓ (correct par hasard)
```

**Mais si on ajoute un 4ème membre :**
```
costPerDay = 100 / 10 / 4 = 2,50€
Part Alice = 2,50 × 10 × 1.0 = 25€
Part Bob   = 2,50 × 10 × 1.0 = 25€
Part Charlie = 2,50 × 10 × 1.0 = 25€
Part David = 2,50 × 10 × 1.0 = 25€
TOTAL = 100€ ✓
```

**Mais si David n'est là que 5 jours :**
```
costPerDay = 100 / 10 / 4 = 2,50€
Part Alice = 2,50 × 10 × 1.0 = 25€
Part Bob   = 2,50 × 10 × 1.0 = 25€
Part Charlie = 2,50 × 10 × 1.0 = 25€
Part David = 2,50 × 5 × 1.0 = 12,50€
TOTAL = 87,50€ ✗ (ERREUR!)
```

## ✅ Nouvel algorithme (CORRECT)

### Principe

1. Calculer les **jours pondérés** pour chaque membre : `jours × coefficient`
2. Calculer le **total des jours pondérés**
3. Part du membre = `(jours pondérés membre / total jours pondérés) × total dépenses`

### Code corrigé

```php
// Calculer les jours pondérés pour chaque membre
$memberWeightedDays = [];
$totalWeightedDays = 0;

foreach ($stayPeriods as $period) {
    $memberDays = calculer_nb_jours($period);
    $weightedDays = $memberDays * $period['coefficient'];
    $memberWeightedDays[$member] = $weightedDays;
    $totalWeightedDays += $weightedDays;
}

// Calculer la part de chaque membre
foreach ($memberWeightedDays as $member => $weightedDays) {
    $share = ($weightedDays / $totalWeightedDays) * $totalExpenses;
}
```

### Même exemple avec nouvel algorithme

**Séjour de 10 jours** - 4 membres - David 5 jours

```
Jours pondérés Alice   = 10 × 1.0 = 10
Jours pondérés Bob     = 10 × 1.0 = 10
Jours pondérés Charlie = 10 × 1.0 = 10
Jours pondérés David   = 5 × 1.0 = 5
Total jours pondérés = 35

Part Alice   = (10/35) × 100 = 28,57€
Part Bob     = (10/35) × 100 = 28,57€
Part Charlie = (10/35) × 100 = 28,57€
Part David   = (5/35) × 100 = 14,29€
TOTAL = 100€ ✓ (CORRECT!)
```

## 🧪 Cas de test

### Test 1 : Tous présents toute la durée

```
Séjour : 10 jours
Dépenses : 150€
Membres : A (10j, coef 1.0), B (10j, coef 1.0), C (10j, coef 1.0)

Jours pondérés : 10 + 10 + 10 = 30
Part A = (10/30) × 150 = 50€
Part B = (10/30) × 150 = 50€
Part C = (10/30) × 150 = 50€
Total = 150€ ✓
```

### Test 2 : Durées différentes

```
Séjour : 10 jours
Dépenses : 200€
Membres : A (10j, coef 1.0), B (5j, coef 1.0), C (3j, coef 1.0)

Jours pondérés : 10 + 5 + 3 = 18
Part A = (10/18) × 200 = 111,11€
Part B = (5/18) × 200 = 55