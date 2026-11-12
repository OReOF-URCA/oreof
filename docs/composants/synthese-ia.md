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

## Configuration (`.env` / `.env.local`)

| Variable | Défaut | Description |
|---|---|---|
| `AI_API_URL` | `https://api.mistral.ai/v1/chat/completions` | Endpoint compatible OpenAI Chat Completions (Mistral, OpenAI, Ollama local, Albert/DINUM, vLLM). |
| `AI_API_KEY` | *(vide)* | Clé Bearer. Si vide (hors endpoint `localhost`), le moteur local déterministe est utilisé sans erreur. |
| `AI_MODEL` | `mistral-small-latest` | Nom du modèle cible envoyé dans le payload JSON. |

## Règles

- Le calcul des écarts (volumes horaires, ECTS, MCCC, textes) reste **100 % déterministe** dans `FicheMatiereDiffAnalyzer` ; le LLM ne reçoit que les champs ayant réellement changé (`FicheMatiereChangeReport`).
- Aucun appel HTTP vers le LLM n'est déclenché au chargement de `fiche_matiere_v2_voir` : l'appel n'a lieu qu'à l'ouverture de la modale `fiche_matiere_v2_synthese_modifications`.

