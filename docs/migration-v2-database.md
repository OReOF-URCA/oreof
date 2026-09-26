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
