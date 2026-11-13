# Synthèse IA des modifications (`FicheMatiere`)

Quand lire : modification de l'analyseur d'écarts de fiche matière, du synthétiseur IA ou de la modale de synthèse admin.
À mettre à jour si : `src/Service/FicheMatiere/FicheMatiereDiffAnalyzer.php`, `src/Service/Ai/FicheMatiereAiSynthesizer.php`, `src/DTO/Comparison/FicheMatiereChange*.php`, `src/DTO/Comparison/AiDiffSummaryResult.php`, `src/Controller/FicheMatiere/FicheMatiereController.php` (`syntheseModifications`), `templates/fiche_matiere_v2/_modal_synthese_modifications*.html.twig`, variables `AI_*` dans `.env`.

## Architecture

| Composant | Chemin | Rôle |
|---|---|---|
| Analyseur déterministe | `src/Service/FicheMatiere/FicheMatiereDiffAnalyzer.php` | Résout la référence (`snapshot_valide`, `snapshot_json` ou `origine_n1`) et produit un `FicheMatiereChangeReport` classé par catégorie (`identite`, `descriptifs`, `volumes_horaires`, `mccc`, `competences`). Aucun appel réseau. |
| Synthétiseur IA + fallback | `src/Service/Ai/FicheMatiereAiSynthesizer.php` | Produit un `AiDiffSummaryResult` (résumé + points clés + indicateur `hasRegulatoryImpact`). Appelle le LLM via `HttpClientInterface` (mis en cache 24 h par empreinte SHA-256 du diff) ou bascule sur la synthèse déterministe locale si `AI_API_KEY` est vide ou si l'API échoue. |
| Route modale (`ROLE_ADMIN`) | `FicheMatiereController::syntheseModifications` (`/fiche-matiere/v2/{slug}/synthese-modifications`) | Paramètres GET optionnels : `source=auto|snapshot|origine_n1` et `refresh=1`. Retourne un flux Turbo ouvrant la modale. |
| UI consultation | `templates/fiche_matiere_v2/voir.html.twig`, `templates/components/FicheMatiereHeader.html.twig` | Bandeau d'alerte en haut de la fiche si `changeReport.hasChanges` + boutons d'accès dans `FicheMatiereHeader` (`ROLE_ADMIN` uniquement). |

## Configuration (Établissement ou `.env`)

La configuration est double et hiérarchisée :

1. **Priorité 1 : Configuration sur l'Établissement (Base de données / UI)** :
   Dans l'interface administrateur (`Configuration > Établissement > APIs tierces` ou route `app_etablissement_api_keys`), ajouter une entrée :
   - **Plateforme** : `Oratio (LLM)` (ou `ROMEO`, `LLM`, `IA`, `OpenAI`, `Mistral`)
   - **URL** : ex. `https://romeogate.univ-reims.fr/llm` (auto-complète avec `/chat/completions`)
   - **Clé API** : clé `sk-...`
   - **Login (optionnel)** : nom du modèle cible (ex. `mistral-small-3.2-24b` ou `gpt-oss-120b`).

2. **Priorité 2 : Variables d'environnement (`.env` / `.env.local`)** :
   Si aucune configuration n'est définie sur l'établissement, le service lit les variables d'environnement :

| Variable | Défaut | Description |
|---|---|---|
| `AI_API_URL` | `https://romeogate.univ-reims.fr/llm` | Base URL ou endpoint complet de l'API LLM (auto-complète avec `/chat/completions` si omis). |
| `AI_API_KEY` | *(vide)* | Clé individuelle `sk-...` obtenue auprès de ROMEO ou du fournisseur. Si vide (hors endpoint `localhost`), le moteur local déterministe est utilisé sans erreur. |
| `AI_MODEL` | `mistral-small-3.2-24b` | Identifiant du modèle utilisé sur ROMEO / Oratio (ex: `mistral-small-3.2-24b`, `gpt-oss-120b`, `ilaas/llama-3.3-70b`). |

## Règles

- Le calcul des écarts (volumes horaires, ECTS, MCCC, textes) reste **100 % déterministe** dans `FicheMatiereDiffAnalyzer` ; le LLM ne reçoit que les champs ayant réellement changé (`FicheMatiereChangeReport`).
- Aucun appel HTTP vers le LLM n'est déclenché au chargement de `fiche_matiere_v2_voir` : l'appel n'a lieu qu'à l'ouverture de la modale `fiche_matiere_v2_synthese_modifications`.

