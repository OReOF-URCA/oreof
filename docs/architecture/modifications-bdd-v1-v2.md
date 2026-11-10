# Modifications du schéma de base de données (main → V2)

Quand lire : comparaison d'architecture de données V1 (main) vs V2, préparation d'une migration de production, ou ajout d'entités/colonnes.
Code : `src/Command/MigrateV2Command.php`, `src/Command/Migration/`, `migrations/`, `src/Entity/`.
À mettre à jour si : modification d'entité Doctrine, ajout de migration Doctrine, ou évolution de `app:migrate:v2`.

---

## 1. Principes généraux d'évolution

La bascule de schéma entre la version historique (`main` / V1) et la V2 s'articule autour de principes structurants :

- **Migration additive en deux temps** : application des créations et modifications sans perte de données via `app:migrate:v2` (idempotente et tracée dans `app_v2_migration`), puis nettoyage destructif différé via `app:migrate:v2:cleanup` uniquement après validation de l'instance V2.
- **Découplage de l'offre de formation** :
  - La `Formation` est directement rattachée à son `TypeDiplome` (`formation.type_diplome_id`), et non plus via `Mention`.
  - La `Mention` est rattachée à une période d'accréditation (`accreditation`) et peut porter plusieurs domaines (`mention_domaine`).
  - Distinction explicite des maquettes avec l'indicateur `formation.classique` (formations classiques avec semestres/UE/EC vs formations non classiques avec maquette PDF).
- **Remplacement des tables de jointure par des colonnes JSON** :
  - Les langues d'enseignement de `fiche_matiere` (historiquement dans l'entité `Langue` et deux tables de jointure) sont stockées dans des colonnes JSON (`langue_dispense`, `langue_support`).
  - Stockage des états d'onglets (`formation_tab_state`, `parcours_tab_state`, `fiche_matiere_tab_state`) avec détails JSON des anomalies.
- **Refonte de la validation de maquette** :
  - Trait partagé `ValidatableTrait` sur `Semestre`, `Ue`, `ElementConstitutif` (`validation_status`, `validation_dirty`, `validation_updated_at`).
  - Table `validation_issue` pour journaliser les règles métier enfreintes.
  - Table `volume_horaire_parcours` pour matérialiser les calculs de volumes par campagne.
- **Centralisation des capacités d'accueil** :
  - Déplacement de `capacite_accueil` depuis `formation` et `parcours` vers `annee.capacite_accueil`.
- **Référentiels administrables** :
  - Introduction des tables `referentiel` et `referentiel_valeur` pour remplacer progressivement les enums statiques hardcodés en PHP.

---

## 2. Nouvelles tables V2

| Table | Rôle métier | Colonnes clés / Relations |
|---|---|---|
| `accreditation` | Périodes d'accréditation quinquennales (ex. 2024-2028). | `id`, `libelle`, `annee_debut`, `annee_fin`, `actif` |
| `app_v2_migration` | Table technique de suivi des étapes de `app:migrate:v2`. | `id`, `migration_key`, `applied_at`, `status`, `details` |
| `document_conseil` | Documents officiels rattachés aux conseils (PV, notes de cadrage). | `id`, `uploaded_by_id` (FK `user`), `composante_id` (FK `composante`), `type`, `filename`, `original_filename`, `date_conseil`, `uploaded_at`, `commentaire` |
| `document_conseil_formation` | Table de liaison n:n entre documents de conseil et formations. | `document_conseil_id` (FK ON DELETE CASCADE), `formation_id` (FK ON DELETE CASCADE) |
| `dpe_formation` | Suivi et validation DPE globalisé à la formation (en plus du parcours). | `id`, `campagne_collecte_id` (FK), `formation_id` (FK), `etat_validation` (JSON), `version`, `created`, `updated`, `laissez_passer` |
| `faq` | Foire aux questions contextuelle. | `id`, `question`, `reponse`, `is_active`, `centres_show` (JSON), `ordre`, `created_at`, `updated_at` |
| `fiche_matiere_tab_state` | État de complétion et validation des onglets d'une fiche matière. | `id`, `fiche_matiere_id` (FK ON DELETE CASCADE), `tab_key`, `done`, `status`, `issues` (JSON), `updated_at` ; index UNIQUE (`fiche_matiere_id`, `tab_key`) |
| `formation_tab_state` | État de complétion et validation des onglets d'une formation. | `id`, `formation_id` (FK ON DELETE CASCADE), `tab_key`, `done`, `status`, `issues` (JSON), `updated_at` ; index UNIQUE (`formation_id`, `tab_key`) |
| `help` | Aides contextuelles affichées sur les pages via le slug de route. | `id`, `title`, `content`, `route_slug`, `is_active`, `centres_show` (JSON) |
| `help_image` | Images téléversées pour enrichir les fiches d'aide. | `id`, `nom`, `fichier`, `date_creation` |
| `parcours_ramification` | Relations de ramification entre parcours (troncs communs, sorties). | `id`, `type_ramification_id` (FK), `parcours_origine_id` (FK), `parcours_cible_id` (FK) |
| `parcours_tab_state` | État de complétion et validation des onglets d'un parcours. | `id`, `parcours_id` (FK ON DELETE CASCADE), `tab_key`, `done`, `status`, `issues` (JSON), `updated_at` ; index UNIQUE (`parcours_id`, `tab_key`) |
| `plateforme_admission` | Référentiel des plateformes de candidature (Parcoursup, MonMaster...). | `id`, `libelle`, `code`, `active`, `configuration` (JSON), `definition_champs` (JSON), `color`, `mode_export` ('global'/'par_diplome') |
| `plateforme_admission_parametre` | Paramètres et capacités d'une plateforme pour une année et campagne. | `id`, `annee_id` (FK), `plateforme_id` (FK), `campagne_id` (FK), `active`, `capacite_globale`, `capacite_fi`, `capacite_alternance`, `capacite_specifique`, `donnees_specifiques` (JSON), `remarques` |
| `portail` | Portails de formation mutualisant plusieurs mentions / L1. | `id`, `campagne_id` (FK ON DELETE CASCADE), `libelle`, `description`, `capacite_accueil`, `created`, `updated` |
| `referentiel` | Référentiels configurables (remplaçant les enums rigides). | `id`, `code` (UNIQUE), `libelle`, `administrable`, `extensible` |
| `referentiel_valeur` | Valeurs associées à chaque référentiel avec mapping legacy. | `id`, `referentiel_id` (FK ON DELETE RESTRICT), `code`, `libelle`, `legacy_value`, `ordre`, `actif`, `systeme`, `config` (JSON) |
| `type_diplome_plateforme_admission` | Configuration des années et capacités attendues par type de diplôme. | `id`, `type_diplome_id` (FK), `plateforme_id` (FK), `campagne_id` (FK), `annees` (JSON), `annees_capacite_requise` (JSON), `annees_capacite_specifique` (JSON) |
| `type_ramification_parcours` | Typologie des ramifications de parcours. | `id`, `code`, `libelle` |
| `validation_issue` | Journal des anomalies de validation des maquettes pédagogiques. | `id`, `semestre_id` (FK `semestre`), `scope_type`, `scope_id`, `rule_code`, `severity`, `message`, `payload` (JSON), `type_diplome`, `created_at` |
| `volume_horaire_parcours` | Agrégat des volumes horaires calculés par parcours et campagne. | `id`, `parcours_id` (FK), `campagne_collecte_id` (FK), `heures_*_pres`, `heures_*_dist`, `volumes_annee` (JSON), `volumes_semestre` (JSON), `date_calcul` ; index UNIQUE (`parcours_id`, `campagne_collecte_id`) |

---

## 3. Tables et colonnes supprimées ou dépréciées

### 3.1 Tables supprimées
- `role` : rôles applicatifs historiques, remplacés par les rôles natifs Symfony (`user.roles` en JSON).
- `user_centre` : associations utilisateur-centre supprimées au profit des profils (`Profil`, `UserProfil`).
- `fiche_matiere_parcours` : liaison historique directe remplacée par l'arborescence EC → UE → Semestre → Parcours.
- `element_constitutif_but_apprentissage_critique` : ancienne table de jointure B.U.T. nettoyée dans le socle Doctrine.
- `langue` : entité supprimée dans la migration `Version20261027224000`. Les données ont été migrées vers les colonnes JSON `fiche_matiere.langue_dispense` et `fiche_matiere.langue_support`.
- `fiche_matiere_langue_dispense` et `fiche_matiere_langue_support` : tables de jointure supprimées suite à la bascule JSON.

### 3.2 Colonnes supprimées du mapping ou transférées
- `formation.capacite_accueil` et `parcours.capacite_accueil` : supprimées (`Version20261106114500`), reportées sur `annee.capacite_accueil`.
- `mention.type_diplome_id` : supprimée (`Version20261108201500`), la liaison est désormais directe sur `formation.type_diplome_id`.
- `mention.domaine_id` : remplacée par la relation n:n `mention_domaine`.
- `formation.version_parent_id` : colonne obsolète de duplication, retirée du mapping V2.
- `timeline_date.is_cfvu` : remplacée par `timeline_date.flag` (valeurs `'cfvu'`, `'none'`).

---

## 4. Modifications par table existante

### `formation`
- **Ajout de colonnes** :
  - `classique` (`TINYINT(1) DEFAULT NULL`) : indique si la formation suit le cycle semestriel classique ou non classique.
  - `type_diplome_id` (`INT`, FK vers `type_diplome`) : rattachement direct.
  - `niveau_entree_referentiel` (`VARCHAR(50) DEFAULT NULL`), `niveau_sortie_referentiel` (`VARCHAR(50) DEFAULT NULL`) : clés canoniques vers `referentiel_valeur`.
  - `structure_semestres` (`JSON DEFAULT NULL`), `remplissage` (`JSON DEFAULT NULL`), `etat_steps` (`JSON NOT NULL`), `etat_dpe` (`JSON DEFAULT NULL`), `etat_reconduction` (`JSON DEFAULT NULL`).
- **Modifications de types / contraintes** :
  - `niveau_entree`, `niveau_sortie` : devenus nullables (`INT DEFAULT NULL`).
  - `mention_id` : rendu optionnel / nullable (permet des formations avec `mention_texte` libre).
- **Suppression** : `capacite_accueil`, `version_parent_id`.

### `parcours`
- **Ajout de colonnes** :
  - `duree_parcours` (`DOUBLE PRECISION DEFAULT NULL`), `duree_parcours_unite` (`VARCHAR(20) DEFAULT NULL`).
  - `duree_parcours_unite_referentiel` (`VARCHAR(50) DEFAULT NULL`), `modalitesEnseignement_referentiel` (`VARCHAR(100) DEFAULT NULL`), `niveau_francais_referentiel` (`VARCHAR(20) DEFAULT NULL`).
  - `semestre_debut`, `semestre_fin` (`INT DEFAULT NULL`) : bornes semestrielles du parcours.
  - `maquette_pdf`, `maquette_pdf_nom_original` (`VARCHAR(255) DEFAULT NULL`) : fichier maquette pour les formations non classiques.
  - `logo` (`JSON DEFAULT NULL`).
  - `etat_steps` (`JSON NOT NULL`), `etat_parcours` (`JSON DEFAULT NULL`), `remplissage` (`JSON DEFAULT NULL`), `etats_fiches_matieres` (`JSON DEFAULT NULL`).
  - `codes_rome` (`JSON DEFAULT NULL`), `regime_inscription` (`JSON DEFAULT NULL`).
- **Suppression** : `capacite_accueil`.

### `annee`
- **Ajout de colonnes** :
  - `capacite_accueil` (`INT DEFAULT NULL`) : capacité d'accueil portée par chaque année d'études.
  - `regime_inscription` (`JSON DEFAULT NULL`).
  - `portail_id` (`INT DEFAULT NULL`, FK vers `portail` ON DELETE SET NULL).
- **Modifications de types** :
  - `code_apogee_etape_annee` (`VARCHAR(10) DEFAULT NULL`), `code_apogee_etape_version` (`VARCHAR(3) DEFAULT NULL`).

### `mention`
- **Ajout de colonnes** :
  - `accreditation_id` (`INT DEFAULT NULL`, FK vers `accreditation`).
  - `sigle` (`VARCHAR(20) DEFAULT NULL`).
- **Tables de jointure** :
  - `mention_domaine` (`mention_id`, `domaine_id`) : rattachement à plusieurs domaines.
  - `mention_type_diplome` (`mention_id`, `type_diplome_id`) : types de diplômes possibles par mention (`Version20261109220000`).
- **Suppression / dissociation** :
  - `type_diplome_id` : supprimé (`Version20261108201500`) au profit de `mention_type_diplome`.
  - `domaine_id` : supprimé au profit de `mention_domaine`.

### `type_diplome`
- **Ajout de colonnes** :
  - `classique` (`TINYINT(1) NOT NULL DEFAULT 1`) : valeur modèle par défaut pour les formations créées.
  - `has_ects` (`TINYINT(1) NOT NULL DEFAULT 1`), `nb_ects_par_semestre` (`INT DEFAULT 30`).
  - `passage_cfvu` (`BOOLEAN DEFAULT TRUE NOT NULL`).
  - `logo` (`JSON DEFAULT NULL`).
- **Modifications de types / contraintes** :
  - `semestre_debut`, `semestre_fin`, `debut_semestre_flexible` : devenus nullables.
  - `libelle_court` (`VARCHAR(50) DEFAULT NULL`).

### `semestre`, `ue`, `element_constitutif` (Socle de validation)
- **Colonnes ajoutées via `ValidatableTrait`** :
  - `validation_status` (`VARCHAR(16) DEFAULT NULL`) : statut calculé (`complete`, `incomplete`, `warning`).
  - `validation_dirty` (`TINYINT(1) NOT NULL DEFAULT 1`) : drapeau d'invalidation pour forcer le recalcul en différé.
  - `validation_updated_at` (`DATETIME DEFAULT NULL`) : horodatage du dernier calcul.
- **Spécificités `semestre`** :
  - `last_modification` (`DATETIME NOT NULL`) : date de dernière modification de la structure pédagogique.
- **Spécificités `ue`** :
  - `ects` (`DOUBLE PRECISION DEFAULT NULL`).
- **Spécificités `element_constitutif`** :
  - `modaliteEnseignement_referentiel` (`VARCHAR(100) DEFAULT NULL`).
  - `ects`, `volume_*_presentiel`, `volume_*_distanciel`, `volume_te` harmonisés en `DOUBLE PRECISION DEFAULT NULL`.
  - `quitus_text` (`VARCHAR(1000) DEFAULT NULL`).

### `fiche_matiere`
- **Ajout de colonnes** :
  - `langue_dispense` (`JSON NOT NULL DEFAULT '["fr"]'`), `langue_support` (`JSON NOT NULL DEFAULT '["fr"]'`).
  - `modaliteEnseignement_referentiel` (`VARCHAR(100) DEFAULT NULL`).
  - `quitus` (`TINYINT(1) DEFAULT NULL`), `quitus_text` (`VARCHAR(1000) DEFAULT NULL`).
  - `etat_steps` (`JSON NOT NULL`), `remplissage` (`JSON DEFAULT NULL`), `etat_fiche` (`JSON DEFAULT NULL`).
- **Modifications de types** :
  - Volumes horaires harmonisés en `DOUBLE PRECISION DEFAULT NULL`.

### `dpe_demande` & `dpe_parcours`
- **`dpe_demande`** :
  - `created` (`DATETIME NOT NULL`), `updated` (`DATETIME NOT NULL`).
  - `date_cloture` (`DATETIME DEFAULT NULL`).
  - `auteur_id` (`INT DEFAULT NULL`, FK vers `user`).
- **`dpe_parcours`** :
  - `etat_validation` (`JSON NOT NULL`), `etat_reconduction` (`VARCHAR(255) DEFAULT NULL`).

### `composante`
- **Ajout de colonnes** :
  - `logo` (`JSON DEFAULT NULL`).
  - `plaquette_rubriques` (`JSON DEFAULT NULL`), `header_plaquette` (`VARCHAR(255) DEFAULT NULL`), `footer_plaquette` (`VARCHAR(255) DEFAULT NULL`).
  - `etat_composante` (`JSON DEFAULT NULL`).

### `timeline_date`
- **Ajout / modification de colonnes** :
  - `ordre` (`INT NOT NULL DEFAULT 0`).
  - `modules_actifs` (`JSON DEFAULT NULL`).
  - `flag` (`VARCHAR(30) NOT NULL`) : remplace le booléen `is_cfvu`.
  - `icone` : migration des valeurs FontAwesome legacy (`fa-*`) vers Symfony UX Icons (`mdi:*`).

### `historique`
- **Ajout de colonnes** :
  - `document_pv_id` (`INT DEFAULT NULL`, FK vers `document_conseil`).
  - `document_note_id` (`INT DEFAULT NULL`, FK vers `document_conseil`).
  - `dpe_formation_id` (`INT DEFAULT NULL`, FK vers `dpe_formation`).
  - `complements` (`JSON DEFAULT NULL`).

### `etablissement` & `etablissement_information`
- **`etablissement`** :
  - `email_oreof` (`VARCHAR(255) DEFAULT NULL`).
  - `api_keys` (`JSON DEFAULT NULL`).
  - `options` (`JSON NOT NULL`).
- **`etablissement_information`** :
  - `tarif_inscription` (`LONGTEXT DEFAULT NULL`).

---

## 5. Données et règles de reprise (Data migrations)

Lors du passage d'une base `main` vers `v2`, plusieurs étapes de reprise des données sont exécutées automatiquement :

1. **Validation de maquette** :
   - `validation_dirty = 1` initialisé sur tous les éléments (`semestre`, `ue`, `element_constitutif`) pour provoquer un recalcul automatique à la première lecture.
   - `validation_status = 'incomplete'` appliqué par défaut.
   - `semestre.last_modification = NOW()` pour les semestres dont la date était `NULL`.
2. **Dates DPE** :
   - `dpe_demande.created` initialisé depuis `COALESCE(date_demande, NOW())`, puis passé en `NOT NULL`.
   - `dpe_demande.updated` initialisé depuis `COALESCE(created, date_demande, NOW())`, puis passé en `NOT NULL`.
3. **Langues des fiches matières** :
   - Migration des liaisons n:n de `fiche_matiere_langue_dispense` et `fiche_matiere_langue_support` vers les colonnes JSON `langue_dispense` et `langue_support` (avec normalisation ISO ex. `ch` → `zh`, `fs` → `fr`).
4. **Mentions et Accréditations** :
   - Création de la période active initiale `2024-2028`.
   - Rattachement de toutes les mentions existantes à cette période.
   - Report de la valeur `type_diplome.classique` sur `formation.classique`.
   - Commande dédiée : `php bin/console app:transfer-mentions --apply`.
5. **Référentiels canoniques** :
   - Recopie textuelle des colonnes enum historiques (`modalites_enseignement`, `niveau_francais`, etc.) vers les nouvelles colonnes `*_referentiel`.

---

## 6. Procédure de déploiement d'une base main vers V2

Pour appliquer l'ensemble de ces évolutions sur une base de données V1 :

```bash
# 0. Sauvegarde préalable obligatoire
# 1. Jouer les migrations Doctrine standard
php bin/console doctrine:migrations:migrate -n

# 2. Migration d'alignement V2 (phase additive)
php bin/console app:migrate:v2          # Prévisualisation
php bin/console app:migrate:v2 --apply  # Application
php bin/console app:migrate:v2 --check  # Vérification de l'intégrité

# 3. Reprise des mentions et accréditations
php bin/console app:transfer-mentions --apply

# 4. Validation finale du schéma Doctrine
php bin/console doctrine:schema:validate
```

