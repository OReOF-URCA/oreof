# Commandes console `app:*`

Quand lire : exécuter ou modifier une commande. Source de vérité : `src/Command/` (`php bin/console list app` dans le
conteneur, `--help` pour les options). Exécution : `make cli APP=v2` puis `php bin/console <commande>`.
À mettre à jour si : `src/Command/` (commande ajoutée/renommée/supprimée, option modifiée).

## Migration / structure de données

| Commande | Rôle | Options |
|---|---|---|
| `app:migrate:v2` | migration additive main → V2 (dry-run par défaut) | `--apply`, `--check`, `--step=`, `--force` — voir `docs/architecture/migration-v2.md` |
| `app:migrate:v2:cleanup` | suppressions destructives post-validation V2 | `--apply --confirm-backup` |
| `app:semestre:migrate-tronc-commun` | semestres tronc commun → semestres mutualisés (campagne courante) | `--apply`, `--campaign`, `--formation`, `--porteur`, `--mapping-file`, `--allow-cross-formation`, `--output` |
| `app:parcours-copy-data` | recopie heures/ECTS/MCCC vers les fiches matières | voir `docs/ops/recopie-fiche-matiere.md` |

## Campagnes / années

| Commande | Rôle | Options |
|---|---|---|
| `app:duplicate-for-new-annee` | duplique une campagne de collecte pour l'année suivante | `--annee-source=<PK campagne>` |
| `app:new-annee-universitaire` | duplique parcours et formations (ancien mécanisme) | `--generate-full-database` |
| `app:create-annee` | création d'année | — |
| `app:update-timeline` | recopie les données des campagnes dans `timeline` | — |

## Calculs / mises à jour en masse

| Commande | Rôle | Options |
|---|---|---|
| `app:calcul-volume-horaire` | calcule et stocke les volumes horaires de tous les parcours | argument `campagne`, `--all`, `--historique` |
| `app:formation:check-completion` | vérifie/met à jour la complétion | `--formation`, `--parcours`, `--fichematiere`, `--all`, `--dry-run`, `--save`, `--format`, `--output` |
| `app:update-remplissage` | taux de remplissage des parcours | `--campagne` |
| `app:update-pourcentage` | champ pourcentage des `FicheMatiere` | — |
| `app:update-codification` | génère la codification des formations | — |
| `app:update-code-bcc`, `app:update-ac` | codes BCC / apprentissages critiques | — |
| `app:update-dpe`, `app:update-slug` | DPE / slugs | — |
| `app:update-droits` | profils selon les responsabilités | — |
| `app:update-notif` | active les notifications par défaut | — |
| `app:recopie-profils`, `app:recopie-domaine` | recopies de profils utilisateurs / domaines | — |
| `app:translations:import-missing` | importe les traductions manquantes, reformate `translations/*.yaml` | `--translations-dir`, `--simulate`, `--overwrite`, `--format`, `--no-backup` |
| `app:workflow:check-metadata-completeness` | contrôle les metadata des workflows | — |

## Publication / exports / versioning

| Commande | Rôle | Options |
|---|---|---|
| `app:mccc-pdf` | PDF des MCCC | `--generate-parcours=<id>`, `--generate-all-parcours` (état `publie`), `--generate-today-cfvu-valid` (`valide_a_publier` du jour) |
| `app:genere-synthese` | PDF de synthèse des parcours soumis au central | — |
| `app:publish-valid-parcours` | publie les parcours validés | — |
| `app:versioning-parcours`, `app:versioning-fiche-matiere` | sauvegarde (versioning) parcours / fiches de la DPE courante | — |
| `app:api-json-versioning` | API JSON du versioning | `--generate-index-api`, `--version-two` |
| `app:export-elp-apogee` | ELP/LSE vers Apogée (Excel, JSON, WS) | ci-dessous |

### `app:export-elp-apogee`

`--mode=test|production` (défaut `test`). Exports : `--full-excel-export=Semestre|UE|EC`, `--parcours-excel-export=<id>`
(`--with-filter`), `--parcours-lse-excel-export`, `--full-lse-excel-export`, `--with-json-export`. Insertions (APOTEST
puis prod via WS) : `--dummy-insertion`, `--dummy-lse-insertion`, `--parcours-insertion`, `--full-parcours-insertion`,
`--full-lse-insertion`, `--dump-parcours-to-insert`, `--with-exclusion`, `--format-formation-to-exclude`. Contrôles :
`--check-duplicates`, `--check-duplicates-with-apogee`, `--check-duplicates-from-json-export`, `--check-lse-test-json`,
`--check-nested-children`, `--full-verify-data`, `--report-invalid-data`, `--report-invalid-apogee-code`,
`--check-diff`.
