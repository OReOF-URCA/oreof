# Migration de la base de données vers ORéOF V2

Cette procédure couvre la migration d'une base issue de \`main\` vers \`v2\`.

## Principes

La migration est volontairement séparée en deux phases :

1. **migration additive** : ajout/modification du schéma et transformation des données sans DROP ;
2. **cleanup** : suppressions destructives, uniquement après validation fonctionnelle de V2.

La commande est en **dry-run par défaut**.

## Avant migration

Faire une sauvegarde restaurable de la base puis tester la procédure sur une copie récente de la production.

Vérifier les migrations Doctrine habituelles avant la migration applicative :

\`\`\`bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction
\`\`\`

## Migration V2

Prévisualiser :

\`\`\`bash
php bin/console app:migrate:v2
\`\`\`

Appliquer :

\`\`\`bash
php bin/console app:migrate:v2 --apply
\`\`\`

Contrôler :

\`\`\`bash
php bin/console app:migrate:v2 --check
php bin/console doctrine:schema:validate
\`\`\`

Une étape peut être isolée :

\`\`\`bash
php bin/console app:migrate:v2 --step=100_safe_defaults
php bin/console app:migrate:v2 --step=100_safe_defaults --apply
\`\`\`

Les étapes appliquées sont enregistrées dans \`app_v2_migration\`. \`--force\` permet de rejouer explicitement une étape.

## Étapes actuelles

- \`010_documented_schema\` : reprend \`docs/architecture/Update_BDD.md\` et \`Update_BDD_Formulaire_Generique.md\`.
- \`020_validation_schema\` : reprend les structures de \`migrations/app/v2/20260325_153000__create_validation_and_tab_state_structures.sql\`.
- \`030_admission_years\` : ajoute \`type_diplome_plateforme_admission.annees\`.
- \`100_safe_defaults\` : initialise uniquement les valeurs dont la règle est déterministe.

Les données ambiguës ne sont pas inventées. Le contrôle signale notamment :
- les \`dpe_demande\` sans \`auteur_id\` ;
- les associations type diplôme / plateforme dont \`annees\` est NULL.

Ces cas doivent recevoir une règle métier explicite avant la migration de production.

## Cleanup destructif

Prévisualiser :

\`\`\`bash
php bin/console app:migrate:v2:cleanup
\`\`\`

Appliquer, uniquement après sauvegarde et validation de V2 :

\`\`\`bash
php bin/console app:migrate:v2:cleanup --apply --confirm-backup
\`\`\`

Le plan de DROP est intentionnellement explicite et n'est jamais généré automatiquement depuis le diff Doctrine.


## Éléments legacy identifiés pour la phase 2

L'audit des mappings `main` / `v2` a identifié des colonnes qui n'existent plus dans le mapping V2, notamment :
- `campagne_collecte.date_ouverture_dpe` et `date_cloture_dpe` ;
- plusieurs champs historiques de `etablissement_information` (handicap, orientation/insertion, relations internationales, associations étudiantes).

Ils ne sont **pas supprimés pendant la phase additive**. Leur usage applicatif et la nécessité éventuelle d'une reprise doivent être validés avant ajout au plan `app:migrate:v2:cleanup`.


## Workflow

L'audit des états `ChangeRf` entre `main` et `v2` ne nécessite pas de transformation de données : les états historiques restent reconnus par le workflow V2. En particulier, `demande_initialisee` est conservé explicitement pour permettre la reprise des demandes existantes.

Les marquages `DpeParcours.etatValidation` et `FicheMatiere.etatFiche` restent stockés dans les mêmes colonnes ; aucune réécriture globale n'est donc appliquée sans nécessité démontrée.


## Décisions métier à confirmer avant cleanup

### Informations établissement / LHEO

V2 retire du mapping `EtablissementInformation` les colonnes `mention_handicap`, `savoir_plus_orientation_insertion`, `relations_internationales` et `associations_etudiantes`. Le générateur LHEO V2 utilise désormais des contenus génériques à leur place.

Ces colonnes ne doivent pas être supprimées automatiquement tant que l'on n'a pas confirmé que les éventuelles personnalisations présentes en production peuvent être abandonnées ou archivées.

### DpeFormation

`DpeFormation` est destiné à disparaître, mais reste actuellement référencé dans la branche V2 par l'entité, son repository, le workflow `dpeFormation`, `Formation.dpeFormations` et `HistoriqueFormation.dpeFormation`.

La migration additive ne crée donc pas de table `dpe_formation`. Le cleanup ne devra la supprimer qu'après retrait de ces références applicatives et vérification de la reprise éventuelle des historiques.


## Niveau des migrations Doctrine existantes

Les migrations Doctrine racine d'avril/mai 2026 sont présentes dans `main` comme dans `v2`. Elles contiennent déjà plusieurs modifications structurelles, y compris des suppressions historiques (`role`, `user_centre`, `fiche_matiere_parcours`, `formation.version_parent_id`, `mention.domaine_id`).

La commande V2 ne les rejoue pas. Le mode `--check` vérifie désormais quelques marqueurs de ce socle et signale une base qui semble ne pas être au même niveau. Avant une migration de test ou de production, comparer impérativement le résultat de `doctrine:migrations:status` avec la structure réelle de la base.


## Validation des données existantes

Les entités `ElementConstitutif`, `Semestre` et `Ue` utilisent en V2 `ValidatableTrait`. Son état initial est `validationStatus=incomplete` et `validationDirty=true`.

La migration marque donc explicitement les lignes préexistantes comme `validation_dirty = 1` et aligne le défaut SQL à `1`. Une base issue de `main` ne doit pas être considérée comme déjà recalculée par le nouveau moteur de validation.


## Décision de reprise — Semestre.lastModification

Pour la bascule V2, les semestres historiques qui n'ont pas de `last_modification` sont initialisés à `NOW()`. Cette date représente le nouveau point de départ du suivi de modification/validation V2 ; il n'est pas nécessaire de reconstruire une date historique antérieure.

Après cette reprise, `semestre.last_modification` est finalisé en `NOT NULL`.


## Réconciliation des contraintes uniques

Les premiers scripts SQL V2 pouvaient créer `fiche_matiere_tab_state`, `formation_tab_state`, `parcours_tab_state` et `volume_horaire_parcours` sans toutes les contraintes uniques attendues par le modèle courant.

L'étape `090_reconcile_schema` ajoute les index UNIQUE uniquement lorsqu'aucun doublon de clé métier n'existe. Aucun doublon n'est supprimé ou fusionné automatiquement. `--check` signale comme erreur toute duplication et toute contrainte UNIQUE encore absente.
