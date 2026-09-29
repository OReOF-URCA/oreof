# Guide utilisateur — Mode Assistant JSON

Public : utilisateurs métier / administrateurs fonctionnels (contenu réutilisable pour l'aide en ligne). Doc technique :
`README.md`.
À mettre à jour si : UI du mode assistant (`json_config_widget`, `json-config_controller.js`) : libellés, boutons, types.

Le Mode Assistant permet de saisir une configuration JSON sans connaître la syntaxe, sous forme de tableau
**Clé | Type | Valeur | Supprimer**. Accès : onglet « Mode Assistant » du champ de configuration.

## Types

| Type | Exemple de valeur | Remarque |
|---|---|---|
| Texte | `https://api.example.com` | texte, URL, email |
| Nombre | `30`, `3.14` | entier ou décimal |
| Booléen | Vrai / Faux | active/désactive |
| Tableau | `["GET", "POST"]` | crochets, virgules, guillemets pour le texte |
| Objet | `{"host": "localhost", "port": 3306}` | accolades, clés entre guillemets |

## Utilisation

| Action | Comment |
|---|---|
| Ajouter une ligne | « Ajouter une configuration » |
| Supprimer une ligne | icône corbeille |
| Changer le type | liste « Type » |
| Voir/valider le JSON | « Appliquer et voir le JSON » (bascule en mode JSON, synchronisé) |
| Revenir à l'assistant | onglet « Mode Assistant » |

Exemple : `api_url` Texte `https://parcoursup.fr/api`, `timeout` Nombre `60`, `enabled` Booléen Vrai,
`allowed_methods` Tableau `["GET", "POST"]` → `{"api_url": "https://parcoursup.fr/api", "timeout": 60, "enabled": true,
"allowed_methods": ["GET", "POST"]}`.

## Bonnes pratiques et erreurs courantes

- Clés explicites, sans espace : `api_url` (pas `url1` ni `api url`). Un seul type par tableau.
- Texte dans un tableau entre guillemets : `["a", "b"]` et non `[a, b]`.
- Clés d'objet entre guillemets : `{"host": "localhost"}` et non `{host: localhost}`.
- Pas de virgule finale : `["a", "b"]` et non `["a", "b",]`.
- Structures très imbriquées : utiliser le mode JSON ou demander à un administrateur technique.
