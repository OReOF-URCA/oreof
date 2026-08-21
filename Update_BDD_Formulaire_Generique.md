```mysql

-- Table formation :
-- niveauEntree et niveauSortie passent de NOT NULL à nullable

ALTER TABLE formation MODIFY COLUMN niveau_entree INT DEFAULT NULL;
ALTER TABLE formation MODIFY COLUMN niveau_sortie INT DEFAULT NULL;

-- Table parcours :
-- Nouveaux champs dureeParcours et dureeParcoursUnite

ALTER TABLE parcours ADD COLUMN duree_parcours DOUBLE PRECISION DEFAULT NULL;
ALTER TABLE parcours ADD COLUMN duree_parcours_unite VARCHAR(20) DEFAULT NULL;

-- Table type_diplome :
-- semestreDebut, semestreFin, debutSemestreFlexible passent de NOT NULL à nullable

ALTER TABLE type_diplome MODIFY COLUMN semestre_debut INT DEFAULT NULL;
ALTER TABLE type_diplome MODIFY COLUMN semestre_fin INT DEFAULT NULL;
ALTER TABLE type_diplome MODIFY COLUMN debut_semestre_flexible TINYINT(1) DEFAULT NULL;

-- Nouveaux champs, hasEcts, nbEctsParSemestre

ALTER TABLE type_diplome ADD COLUMN classique TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE type_diplome ADD COLUMN has_ects TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE type_diplome ADD COLUMN nb_ects_par_semestre INT DEFAULT 30;

```