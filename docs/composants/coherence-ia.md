# Indicateur de cohérence des contenus (règles + IA)

Quand lire : modification de la détection de contenus fictifs/remplissage/incohérences, de la modale de diagnostic
cohérence ou de l'audit Pilotage.
À mettre à jour si : `src/Entity/CoherenceAnalyse.php`, `src/Repository/CoherenceAnalyseRepository.php`,
`src/Service/Ai/LlmClient.php`, `src/Service/Coherence/`, `src/DTO/Coherence/`,
`src/Message/AnalyzeParcoursCoherence.php`, `src/MessageHandler/AnalyzeParcoursCoherenceHandler.php`,
`src/Command/AnalyzeCoherenceCommand.php`, `src/Twig/Components/CoherenceBadge.php`,
`src/Controller/CoherenceController.php`, `src/Controller/Admin/PilotageCoherenceController.php`,
`src/DataTable/PilotageCoherenceDataTable.php`.

## Architecture hybride (2 niveaux)

L'indicateur est **informatif et non bloquant** dans les workflows DPE/Fiche matière. Dans un premier temps, son
affichage et ses points d'entrée (badges, modales, colonnes de validation et page Pilotage) sont **restreints au rôle
`ROLE_ADMIN`** (`CoherenceController`, `PilotageCoherenceController`, `CoherenceBadge`, `PilotageMenuProvider` et
templates associés).

| Composant                | Chemin                                                      | Rôle                                                                                                                                                                                                                                                                                            |
|--------------------------|-------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Pré-filtre déterministe  | `src/Service/Coherence/TextQualityDetector.php`             | Détecte sans coût ni latence réseau : placeholders (`lorem ipsum`, `à compléter`, `todo`, `test`, `tbd`…), caractères répétés, faible ratio alphabétique, copie d'intitulé, textes trop courts, faible diversité lexicale, champs dupliqués.                                                    |
| Constructeur d'empreinte | `src/Service/Coherence/ParcoursCoherencePayloadBuilder.php` | Extrait textes, modalités (`stage`, `projet`, `mémoire`, `alternance`), compétences et maquette (`Semestre`/`UE`/`EC` y compris raccrochages), et calcule le hash SHA-256 (`computeHash()`).                                                                                                    |
| Orchestrateur            | `src/Service/Coherence/CoherenceAnalyzerService.php`        | `getIndicatorForParcours()` / `getIndicatorForFicheMatiere()` (0 appel LLM, lit `coherence_analyse` + signale `isStale` si hash modifié + remonte toute nouvelle alerte déterministe immédiate) ; `analyzeParcours()` / `analyzeFicheMatiere()` (appelle `LlmClient` si configuré et persiste). |
| Client LLM mutualisé     | `src/Service/Ai/LlmClient.php`                              | Réutilise la même résolution hiérarchique que `FicheMatiereAiSynthesizer` (`Etablissement::getAiApiConfiguration()` > `.env` `AI_API_URL`/`AI_API_KEY`/`AI_MODEL`).                                                                                                                             |
| Persistance BDD          | `src/Entity/CoherenceAnalyse.php`                           | Table `coherence_analyse` (`parcours` / `fiche_matiere`), hash SHA-256, statut (`ok\|warning\|alert\|pending\|error`), score 0–100, score déterministe, synthèse et liste JSON d'`issues`.                                                                                                      |
| Async & CLI              | `AnalyzeParcoursCoherence`, `AnalyzeCoherenceCommand`       | Transport `async_export` dans `config/packages/messenger.yaml` ; commande `app:coherence:analyze [--campagne=] [--parcours=] [--limit=] [--no-ai] [--force]`.                                                                                                                                   |

## Points d'affichage UI

- **Composant badge** : `<twig:CoherenceBadge :parcours="parcours" />` ou `:ficheMatiere="ficheMatiere"` (props
  `compact`, `clickable`). Ouvre la modale via `click->modalturbo#open`.
- **En-têtes & vérifications** : `templates/components/ParcoursHeader.html.twig`,
  `templates/components/FicheMatiereHeader.html.twig`, `templates/parcours_state/_index.html.twig`.
- **Validation** : colonne « Cohérence » dans `ValidationAdminController::dpeListe`
  (`templates/validation/datatable/_coherence.html.twig`) et `templates/validation/_listeFiches.html.twig`, plus encart
  indicatif dans `templates/parcours_v2/process/_validation_apply.html.twig`.
- **Pilotage admin** : `/administration/pilotage-coherence` (`PilotageCoherenceController`,
  `PilotageCoherenceDataTable`, menu `pilotage.coherence_ia`), avec KPIs, filtres par statut, pré-filtre batch
  déterministe et dispatch Messenger IA par lots.
- **Modale** : `CoherenceController` (`app_coherence_parcours_modal`, `app_coherence_parcours_analyze`,
  `app_coherence_fiche_modal`, `app_coherence_fiche_analyze`) met à jour en direct la modale et les badges de la page
  via Turbo Stream (`templates/coherence/_badge_stream.html.twig`).
