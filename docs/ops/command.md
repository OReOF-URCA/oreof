# Commandes disponibles dans ORéOF

Ce document décrit l'ensemble des commandes disponibles dans l'application ORéOF, leurs paramètres et des exemples
d'utilisation.

## ApiJsonVersioningCommand

**Nom de la commande :** `app:api-json-versioning`

**Description :** Commande pour gérer l'API JSON du versioning.

**Options :**

- `--generate-index-api` : Génère le fichier d'index pour l'API JSON du versioning.

**Exemple d'utilisation :**

```bash
php bin/console app:api-json-versioning --generate-index-api
```

## CorrigeSemestreCommand

**Nom de la commande :** `app:corrige-semestre`

**Description :** Corrige les données des semestres en copiant les codes Apogee des semestres impairs vers les semestres
pairs.

**Options :** Aucune option spécifique.

**Exemple d'utilisation :**

```bash
php bin/console app:corrige-semestre
```

## ExportElpApogeeCommand

**Nom de la commande :** `app:export-elp-apogee`

**Description :** Exporte les éléments pédagogiques (ELP) vers Apogee.

**Options :**

- `--mode` : Mode d'exécution ('test' ou 'production', défaut: 'test')
- `--full-excel-export` : Génère un export Excel des ELP pour toutes les formations - Type : Semestre, UE, EC
- `--dummy-insertion` : Insère un ELP dans la base de données APOTEST
- `--dummy-lse-insertion` : Insère une LSE dans la base de données APOTEST
- `--parcours-insertion` : Insère tous les ELP d'un parcours dans la base de données, via le Web Service
- `--full-parcours-insertion` : Insère tous les ELP de tous les parcours disponibles en base de données
- `--full-lse-insertion` : Insère toutes les listes LSE de tous les parcours disponibles
- `--parcours-excel-export` : Génère un export de tous les ELP pour un parcours donné
- `--with-filter` : Ajoute un filtre aux données traitées (parcours-excel-export)
- `--with-json-export` : Option si l'on souhaite un export JSON supplémentaire dans certains cas
- `--check-lse-test-json` : Vérifie un fichier d'export JSON pour les LSE de test (doublons)
- `--check-duplicates` : Vérifie s'il y a des doublons sur les codes Apogee depuis la base de données
- `--full-verify-data` : Instancie tous les ELP des parcours disponibles, et génère un compte-rendu selon les erreurs
  détectées
- `--parcours-lse-excel-export` : Génère un export Excel pour les LSE d'un parcours
- `--full-lse-excel-export` : Génère l'export Excel pour les LSE de tous les parcours disponibles
- `--check-duplicates-with-apogee` : Vérifie si les codes apogee depuis OREOF ne sont pas déjà présents dans APOGEE
- `--report-invalid-data` : Génère un rapport listant les parcours dont certaines données sont manquantes
- `--report-invalid-apogee-code` : Génère un rapport listant les parcours dont les codes APOGEE sont invalides
- `--check-duplicates-from-json-export` : Vérifie si des doublons existent dans un export JSON des ELP
- `--check-nested-children` : Vérifie s'il n'y a pas trop d'éléments enfants imbriqués dans les parcours disponibles
- `--format-formation-to-exclude` : Formate un fichier contenant les formations à exclure vers du JSON
- `--dump-parcours-to-insert` : Dump les parcours disponibles pour l'insertion
- `--with-exclusion` : Exclut certaines données
- `--check-diff` : Vérifie les différences entre deux fichier JSON

**Exemples d'utilisation :**

```bash
php bin/console app:export-elp-apogee --mode=test --parcours-excel-export=123
php bin/console app:export-elp-apogee --full-excel-export=EC
php bin/console app:export-elp-apogee --full-parcours-insertion
```

## FicheSansEcCommand

**Nom de la commande :** `app:fiche:sans-ec`

**Description :** Liste toutes les fiches matières qui n'ont pas d'éléments constitutifs associés.

**Options :** Aucune option spécifique.

**Exemple d'utilisation :**

```bash
php bin/console app:fiche:sans-ec
```

## GenereSyntheseCommand

**Nom de la commande :** `app:genere-synthese`

**Description :** Génère des fichiers PDF de synthèse pour les parcours soumis au central.

**Options :** Aucune option spécifique.

**Exemple d'utilisation :**

```bash
php bin/console app:genere-synthese
```

## McccPdfCommand

**Nom de la commande :** `app:mccc-pdf`

**Description :** Génère les PDF contenant les descriptifs d'une formation ORéOF (MCCC - Modalités de Contrôle des
Connaissances et des Compétences).

**Options :**

- `--generate-parcours` : Identifiant (PK) du parcours pour lequel on souhaite générer l'export des MCCC au format PDF
- `--generate-all-parcours` : Génère tous les PDF des MCCC pour tous les parcours validés ('publie')
- `--generate-today-cfvu-valid` : Génère les PDF des MCCC pour les parcours qui ont été validés le jour même ('
  valide_a_publier')

**Exemples d'utilisation :**

```bash
php bin/console app:mccc-pdf --generate-parcours=123
php bin/console app:mccc-pdf --generate-all-parcours
php bin/console app:mccc-pdf --generate-today-cfvu-valid
```

## NewAnneeUniversitaireCommand

**Nom de la commande :** `app:new-annee-universitaire`

**Description :** Duplique tous les parcours et formations, pour créer une nouvelle année universitaire.

**Options :**

- `--generate-full-database` : Copie tous les parcours disponibles

**Exemple d'utilisation :**

```bash
php bin/console app:new-annee-universitaire --generate-full-database
```

## ParcoursCopyDataCommand

**Nom de la commande :** `app:parcours:copy-data`

**Description :** Copie les données d'un parcours vers un autre.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:parcours:copy-data
```

## PublishValidParcoursCommand

**Nom de la commande :** `app:publish-valid-parcours`

**Description :** Publie les parcours validés.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:publish-valid-parcours
```

## RecopieCentreCommand

**Nom de la commande :** `app:recopie-centre`

**Description :** Recopie les centres de formation.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:recopie-centre
```

## UpdateAcCommand

**Nom de la commande :** `app:update-ac`

**Description :** Met à jour les apprentissages critiques.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-ac
```

## UpdateBccCommand

**Nom de la commande :** `app:update-bcc`

**Description :** Met à jour les blocs de compétences.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-bcc
```

## UpdateButCommand

**Nom de la commande :** `app:update-but`

**Description :** Met à jour les données des BUT (Bachelor Universitaire de Technologie).

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-but
```

## UpdateCodeBccCommand

**Nom de la commande :** `app:update-code-bcc`

**Description :** Met à jour les codes des blocs de compétences.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-code-bcc
```

## UpdateCodificationCommand

**Nom de la commande :** `app:update-codification`

**Description :** Met à jour la codification des éléments.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-codification
```

## UpdateDpeCommand

**Nom de la commande :** `app:update-dpe`

**Description :** Met à jour les DPE (Dossier Pédagogique Electronique).

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-dpe
```

## UpdateHistoriqueCommand

**Nom de la commande :** `app:update-historique`

**Description :** Met à jour l'historique des parcours.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-historique
```

## UpdatePourcentageCommand

**Nom de la commande :** `app:update-pourcentage`

**Description :** Met à jour les pourcentages dans les éléments.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-pourcentage
```

## UpdateRemplissageCommand

**Nom de la commande :** `app:update-remplissage`

**Description :** Met à jour le taux de remplissage des parcours.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-remplissage
```

## UpdateSlugCommand

**Nom de la commande :** `app:update-slug`

**Description :** Met à jour les slugs des entités.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:update-slug
```

## VersioningFicheMatiereCommand

**Nom de la commande :** `app:versioning-fiche-matiere`

**Description :** Gère le versioning des fiches matières.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:versioning-fiche-matiere
```

## VersioningParcoursCommand

**Nom de la commande :** `app:versioning-parcours`

**Description :** Gère le versioning des parcours.

**Options :** Non documentées dans le code examiné.

**Exemple d'utilisation :**

```bash
php bin/console app:versioning-parcours
```
