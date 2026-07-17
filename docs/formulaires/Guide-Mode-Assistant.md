# Guide d'utilisation du Mode Assistant JSON

## Introduction

Le **Mode Assistant** vous permet de créer des configurations JSON sans connaître la syntaxe JSON. C'est comme remplir un tableau Excel !

## Accès au Mode Assistant

1. Dans le formulaire, cherchez le champ de configuration (ex: "Configuration de la plateforme")
2. Cliquez sur l'onglet **"Mode Assistant"** (avec l'icône baguette magique ✨)

## Interface du Mode Assistant

L'interface se compose de :

```
┌─────────────────────────────────────────────────────────────┐
│  📋 Clé          🎯 Type        💎 Valeur           ❌     │
├─────────────────────────────────────────────────────────────┤
│  [api_url  ] [Texte ▼] [https://example.com     ] [🗑️]    │
│  [timeout  ] [Nombre▼] [30                       ] [🗑️]    │
│  [enabled  ] [Bool. ▼] [Vrai ▼                   ] [🗑️]    │
├─────────────────────────────────────────────────────────────┤
│           ➕ Ajouter une configuration                     │
└─────────────────────────────────────────────────────────────┘
```

### Colonnes

1. **Clé** : Le nom de la configuration (ex: `api_url`, `timeout`, `max_users`)
2. **Type** : Le type de données (voir ci-dessous)
3. **Valeur** : La valeur de la configuration
4. **Actions** : Bouton pour supprimer la ligne

## Types de données

### 🔤 Texte
Pour du texte simple ou des URL.

**Exemples :**
- `https://api.example.com`
- `Mon texte de configuration`
- `admin@example.com`

### 🔢 Nombre
Pour des nombres entiers ou décimaux.

**Exemples :**
- `30` (timeout en secondes)
- `100` (nombre maximum d'utilisateurs)
- `3.14` (valeur décimale)

### ✅ Booléen
Pour des valeurs Vrai/Faux (activé/désactivé).

**Exemples :**
- `Vrai` → Active une fonctionnalité
- `Faux` → Désactive une fonctionnalité

### 📦 Tableau
Pour une liste de valeurs.

**Exemples :**
- `["GET", "POST", "PUT"]` → Liste de méthodes HTTP
- `["admin", "user", "guest"]` → Liste de rôles
- `[1, 2, 3, 4]` → Liste de nombres

**💡 Astuce :** Écrivez les valeurs entre crochets `[]` et séparez-les par des virgules.

### 🎁 Objet
Pour une structure JSON complexe.

**Exemples :**
- `{"host": "localhost", "port": 3306}` → Configuration de base de données
- `{"max": 100, "min": 0}` → Limites

**💡 Astuce :** Écrivez les valeurs entre accolades `{}` au format `"clé": "valeur"`.

## Exemple complet : Configuration d'une plateforme d'admission

### Étape 1 : Ajouter l'URL de l'API
```
Clé: api_url
Type: Texte
Valeur: https://parcoursup.fr/api
```

### Étape 2 : Ajouter le timeout
```
Clé: timeout
Type: Nombre
Valeur: 60
```

### Étape 3 : Activer la fonctionnalité
```
Clé: enabled
Type: Booléen
Valeur: Vrai
```

### Étape 4 : Définir les méthodes autorisées
```
Clé: allowed_methods
Type: Tableau
Valeur: ["GET", "POST"]
```

### Résultat
Quand vous cliquez sur **"Appliquer et voir le JSON"**, vous obtenez :

```json
{
  "api_url": "https://parcoursup.fr/api",
  "timeout": 60,
  "enabled": true,
  "allowed_methods": ["GET", "POST"]
}
```

## Astuces et conseils

### ✅ À faire
- Utilisez des noms de clés clairs et explicites (ex: `api_url` plutôt que `url1`)
- Vérifiez le type de données avant de saisir la valeur
- Utilisez le bouton "Appliquer et voir le JSON" pour vérifier le résultat

### ❌ À éviter
- N'utilisez pas d'espaces dans les noms de clés (préférez `api_url` à `api url`)
- N'oubliez pas les guillemets dans les tableaux de texte : `["item1", "item2"]`
- Ne mélangez pas les types dans un même tableau

## Erreurs courantes

### ❌ Tableau sans guillemets pour du texte
```
Mauvais: [item1, item2]
Bon:     ["item1", "item2"]
```

### ❌ Objet sans guillemets pour les clés
```
Mauvais: {host: localhost}
Bon:     {"host": "localhost"}
```

### ❌ Virgule en trop dans un tableau
```
Mauvais: ["a", "b", "c",]
Bon:     ["a", "b", "c"]
```

## Passage au Mode JSON

Une fois votre configuration créée dans le mode assistant :

1. Cliquez sur **"Appliquer et voir le JSON"**
2. Le JSON est généré automatiquement
3. Vous pouvez le vérifier et le formater si besoin
4. Cliquez sur **"Mode Assistant"** pour revenir à l'interface graphique

## Cas d'usage

### Configuration d'une API externe
```
api_url    → Texte   → https://api.service.fr
api_key    → Texte   → abc123xyz
timeout    → Nombre  → 30
retry      → Nombre  → 3
enabled    → Booléen → Vrai
```

### Limites et quotas
```
max_users        → Nombre  → 1000
max_requests     → Nombre  → 10000
rate_limit       → Nombre  → 100
unlimited_admins → Booléen → Vrai
```

### Configuration de notifications
```
email_enabled → Booléen → Vrai
sms_enabled   → Booléen → Faux
recipients    → Tableau → ["admin@example.com", "support@example.com"]
```

## Besoin d'aide ?

- 💡 Consultez la section "Aide sur le format JSON" en bas du formulaire
- 📚 Lisez la documentation technique dans `docs/formulaires/JsonConfigType.md`
- 🎓 Demandez à un administrateur technique en cas de configuration complexe

## Résumé rapide

| Action | Comment faire |
|--------|---------------|
| Ajouter une config | Cliquer sur "➕ Ajouter une configuration" |
| Supprimer une ligne | Cliquer sur l'icône 🗑️ à droite |
| Changer le type | Utiliser le menu déroulant "Type" |
| Voir le JSON | Cliquer sur "Appliquer et voir le JSON" |
| Revenir au wizard | Cliquer sur l'onglet "Mode Assistant" |

---

**Astuce finale** : Commencez simple avec quelques configurations de type Texte et Nombre, puis ajoutez progressivement des types plus complexes (Tableaux, Objets) au fur et à mesure que vous vous familiarisez avec l'outil.
