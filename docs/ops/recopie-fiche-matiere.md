# Recopie des données EC → fiches matières (`app:parcours-copy-data`)

Quand lire : exécuter ou vérifier la recopie des heures/ECTS/MCCC des EC vers les fiches matières.
Code : `src/Command/ParcoursCopyDataCommand.php` ; lecture prioritaire côté fiche via
`GetElementConstitutif::getFicheMatiereHeures()`, `getFicheMatiereEcts()`, `getMcccsFromFicheMatiere()`.
À mettre à jour si : `src/Command/ParcoursCopyDataCommand.php` ou méthodes `GetElementConstitutif::getFicheMatiere*`.

## Préparation

1. Créer une base de destination (copie ou dump restauré de la source) et la déclarer dans `.env.local` :
   `PARCOURS_COPY_DATABASE_URL=<URL Doctrine>`.
2. Sur **les deux bases** (source et destination) :
   `ALTER TABLE element_constitutif ADD heures_specifiques TINYINT(1) DEFAULT NULL, ADD mccc_specifiques TINYINT(1) DEFAULT NULL, ADD ects_specifiques TINYINT(1) DEFAULT NULL;`

## Commandes

| But | Commande |
|---|---|
| Lancer la copie | `app:parcours-copy-data --test-copy-database` |
| Comparer les bases entières | `app:parcours-copy-data --compare-two-databases hours\|ects\|mccc` (parcours en erreur listés en fin) |
| Comparer un parcours — heures | `app:parcours-copy-data --compare-two-dto <ID> --from-copy` |
| Comparer un parcours — ECTS / MCCC | `app:parcours-copy-data --compare-two-dto <ID> --ects` / `--mccc` (sans `--from-copy`) |
| Export PDF de maquette | `app:parcours-copy-data --dto-pdf-export <ID> [--from-copy]` → dossier `export/` à la racine |

Autre option : `--after-copy`. Incohérences peu nombreuses : correction manuelle en base.

## Statistiques post-copie

```sql
SELECT
 (SELECT COUNT(ec.id) FROM element_constitutif ec JOIN parcours p ON p.id = ec.parcours_id
   JOIN formation f ON p.formation_id = f.id JOIN type_diplome td ON f.type_diplome_id = td.id
   WHERE ec.parcours_id IS NOT NULL AND td.libelle_court != 'BUT') AS ec_total_hors_but,
 (SELECT COUNT(ec.id) FROM element_constitutif ec JOIN parcours p ON p.id = ec.parcours_id
   JOIN formation f ON p.formation_id = f.id JOIN type_diplome td ON f.type_diplome_id = td.id
   WHERE ec.parcours_id IS NOT NULL AND td.libelle_court != 'BUT' AND ec.ec_parent_id IS NOT NULL) AS ec_avec_parent_hors_but,
 (SELECT COUNT(e.id) FROM element_constitutif e JOIN element_constitutif p ON e.ec_parent_id = p.id
   WHERE p.heures_enfants_identiques = 1) AS ec_heures_sur_parent,
 (SELECT COUNT(id) FROM element_constitutif WHERE heures_specifiques IS NOT NULL) AS heures_specifiques,
 (SELECT COUNT(id) FROM element_constitutif WHERE mccc_specifiques IS NOT NULL) AS mccc_specifiques,
 (SELECT COUNT(id) FROM element_constitutif WHERE ects_specifiques IS NOT NULL) AS ects_specifiques;
```
