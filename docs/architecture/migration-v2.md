# Migration de la base main → ORéOF V2

Quand lire : toute modification de schéma/entité Doctrine, ou préparation d'une bascule main → V2.
Code : `src/Command/MigrateV2Command.php` (+ `src/Command/Migration/V2HelpSeedData`, `V2DoctrineAlignment`). Les anciens
scripts SQL `Update_BDD*.md` sont archivés dans `docs/archives/sql-v2/` et **repris par l'étape 010** : ne plus les
appliquer à la main.
À mettre à jour si : `src/Command/MigrateV2Command.php`, `src/Command/Migration/`, `app:migrate:v2:cleanup` ou décision de reprise.

## Principes

- Deux phases : **additive** (`app:migrate:v2`, jamais de DROP) puis **cleanup** destructif
  (`app:migrate:v2:cleanup`) uniquement après validation fonctionnelle de V2.
- Dry-run par défaut. Étapes appliquées tracées dans `app_v2_migration` ; `--force` rejoue une étape.
- Données ambiguës jamais inventées : `--check` les signale (`dpe_demande` sans auteur, associations
  diplôme/plateforme sans années, lignes `validation_issue` incomplètes).
- Plan de DROP du cleanup explicite, jamais généré depuis le diff Doctrine.
- Nouvelle modification de schéma V2 : migration Doctrine dans `migrations/` **et**, si elle doit s'appliquer à une base
  main, étape idempotente dans `MigrateV2Command` (dépendances déclarées, contrôle ajouté à `--check`).

## Procédure

```bash
# 0. Sauvegarde restaurable ; tester sur une copie récente de la prod
php bin/console doctrine:migrations:status          # comparer avec la structure réelle de la base
php bin/console doctrine:migrations:migrate --no-interaction
# 1. Migration additive
php bin/console app:migrate:v2                       # prévisualiser
php bin/console app:migrate:v2 --apply
php bin/console app:migrate:v2 --check
php bin/console doctrine:schema:validate
# Étape isolée (dépendances vérifiées avant écriture)
php bin/console app:migrate:v2 --step=100_safe_defaults [--apply]
# 2. Cleanup, après sauvegarde et validation V2
php bin/console app:migrate:v2:cleanup               # prévisualiser
php bin/console app:migrate:v2:cleanup --apply --confirm-backup
```

## Étapes

| Étape | Contenu |
|---|---|
| `010_documented_schema` | modifications des anciens `Update_BDD*.md` |
| `020_validation_schema` | socle de validation, états d'onglets, `volume_horaire_parcours` |
| `030_admission_years` | `annees`, `annees_capacite_requise` sur les associations diplôme/plateforme |
| `040_new_v2_tables` | FAQ, aide, documents de conseil |
| `045_help_seed_data` | aides contextuelles et images livrées avec V2 (`V2HelpSeedData`), sans écraser l'existant (`route_slug`/`fichier`) ; répare les aides semées avec des `\n` littéraux et jamais modifiées depuis (rejouer avec `--step=045_help_seed_data --force`). Contenus à jour : import d'archive, voir `docs/composants/aides-faq.md` |
| `050_history_documents` | relations historique → documents de conseil |
| `060_doctrine_alignment` | alignement non destructif du schéma restant sur le mapping Doctrine V2 (`V2DoctrineAlignment`) |
| `090_reconcile_schema` | répare les structures V2 partielles (FK, index, UNIQUE, `validation_issue`) ; UNIQUE ajouté seulement sans doublon, aucun doublon fusionné automatiquement |
| `100_safe_defaults` | valeurs déterministes : états de validation, `semestre.last_modification = NOW()` si NULL |
| `110_finalize_constraints` | `NOT NULL` finaux après reprise |

`--check` contrôle tables/colonnes, FK, UNIQUE (doublons = erreur), `NOT NULL` finaux, défaut `validation_dirty = 1`, et
des marqueurs du socle Doctrine d'avril/mai 2026 (non rejoué par la commande ; il contient déjà des suppressions
historiques : `role`, `user_centre`, `fiche_matiere_parcours`, `formation.version_parent_id`, `mention.domaine_id`).

## Décisions de reprise

- Validation : `ElementConstitutif`, `Semestre`, `Ue` utilisent `ValidatableTrait` (`validationStatus=incomplete`,
  `validationDirty=true`). Les lignes existantes sont marquées `validation_dirty = 1` et le défaut SQL aligné : une base
  main n'est jamais considérée comme recalculée.
- `Semestre.lastModification` : NULL → `NOW()` (nouveau point de départ du suivi V2, pas de reconstitution historique),
  puis `NOT NULL`.
- Workflow : aucune transformation. Les états `ChangeRf` historiques restent reconnus (dont `demande_initialisee`) ;
  `DpeParcours.etatValidation` et `FicheMatiere.etatFiche` gardent leurs colonnes.

## À valider avant d'ajouter au cleanup

Colonnes absentes du mapping V2 mais **conservées** en phase additive :

- `campagne_collecte.date_ouverture_dpe`, `date_cloture_dpe` ;
- `etablissement_information.mention_handicap`, `savoir_plus_orientation_insertion`, `relations_internationales`,
  `associations_etudiantes` (le générateur LHEO V2 utilise désormais des contenus génériques) : confirmer que les
  personnalisations de production peuvent être abandonnées ou archivées.
