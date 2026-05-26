/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/migrations/app/v2/20260325_153000__create_validation_and_tab_state_structures.sql
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/03/2026 14:49
 */

/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/migrations/app/v2/schema/20260325_153000__create_validation_and_tab_state_structures.sql
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/03/2026 14:35
 */

-- v2 - socle validation + tab states
-- Cible: MySQL uniquement

CREATE TABLE IF NOT EXISTS fiche_matiere_tab_state
(
    id
    INT
    AUTO_INCREMENT
    NOT
    NULL,
    fiche_matiere_id
    INT
    NOT
    NULL,
    tab_key
    VARCHAR
(
    30
) NOT NULL,
    done TINYINT
(
    1
) DEFAULT 0 NOT NULL,
    status VARCHAR
(
    10
) DEFAULT 'red' NOT NULL,
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    issues JSON DEFAULT NULL COMMENT '(DC2Type:json)',
    INDEX IDX_36E6A5605A3AD6AF
(
    fiche_matiere_id
),
    PRIMARY KEY
(
    id
)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS formation_tab_state
(
    id
    INT
    AUTO_INCREMENT
    NOT
    NULL,
    formation_id
    INT
    NOT
    NULL,
    tab_key
    VARCHAR
(
    30
) NOT NULL,
    done TINYINT
(
    1
) DEFAULT 0 NOT NULL,
    status VARCHAR
(
    10
) DEFAULT 'red' NOT NULL,
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    issues JSON DEFAULT NULL COMMENT '(DC2Type:json)',
    INDEX IDX_193AB4875200282E
(
    formation_id
),
    PRIMARY KEY
(
    id
)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS parcours_tab_state
(
    id
    INT
    AUTO_INCREMENT
    NOT
    NULL,
    parcours_id
    INT
    NOT
    NULL,
    tab_key
    VARCHAR
(
    30
) NOT NULL,
    done TINYINT
(
    1
) DEFAULT 0 NOT NULL,
    status VARCHAR
(
    10
) DEFAULT 'red' NOT NULL,
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    issues JSON DEFAULT NULL COMMENT '(DC2Type:json)',
    INDEX IDX_FEC78C486E38C0DB
(
    parcours_id
),
    PRIMARY KEY
(
    id
)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS validation_issue
(
    id
    INT
    AUTO_INCREMENT
    NOT
    NULL,
    semestre_id
    INT
    DEFAULT
    NULL,
    scope_type
    VARCHAR
(
    255
) NOT NULL,
    scope_id INT NOT NULL,
    rule_code VARCHAR
(
    255
) NOT NULL,
    severity VARCHAR
(
    15
) NOT NULL,
    message VARCHAR
(
    255
) DEFAULT NULL,
    payload JSON DEFAULT NULL COMMENT '(DC2Type:json)',
    type_diplome VARCHAR
(
    255
) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_FABE262D5577AFDB
(
    semestre_id
),
    PRIMARY KEY
(
    id
)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

SET
@fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fiche_matiere_tab_state'
      AND CONSTRAINT_NAME = 'FK_36E6A5605A3AD6AF'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET
@fk_sql = IF(
    @fk_exists = 0,
    'ALTER TABLE fiche_matiere_tab_state ADD CONSTRAINT FK_36E6A5605A3AD6AF FOREIGN KEY (fiche_matiere_id) REFERENCES fiche_matiere (id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET
@fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'formation_tab_state'
      AND CONSTRAINT_NAME = 'FK_193AB4875200282E'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET
@fk_sql = IF(
    @fk_exists = 0,
    'ALTER TABLE formation_tab_state ADD CONSTRAINT FK_193AB4875200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET
@fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'parcours_tab_state'
      AND CONSTRAINT_NAME = 'FK_FEC78C486E38C0DB'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET
@fk_sql = IF(
    @fk_exists = 0,
    'ALTER TABLE parcours_tab_state ADD CONSTRAINT FK_FEC78C486E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET
@fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'validation_issue'
      AND CONSTRAINT_NAME = 'FK_FABE262D5577AFDB'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET
@fk_sql = IF(
    @fk_exists = 0,
    'ALTER TABLE validation_issue ADD CONSTRAINT FK_FABE262D5577AFDB FOREIGN KEY (semestre_id) REFERENCES semestre (id)',
    'SELECT 1'
);
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE element_constitutif
    ADD COLUMN IF NOT EXISTS validation_status VARCHAR (16) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS validation_dirty TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS validation_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';

ALTER TABLE nature_ue_ec
    ADD COLUMN IF NOT EXISTS description_courte VARCHAR (255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS icone VARCHAR (50) DEFAULT NULL;

ALTER TABLE parcours
    ADD COLUMN IF NOT EXISTS semestre_debut INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS semestre_fin INT DEFAULT NULL;

ALTER TABLE semestre
    ADD COLUMN IF NOT EXISTS last_modification DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    ADD COLUMN IF NOT EXISTS validation_status VARCHAR (16) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS validation_dirty TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS validation_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';

UPDATE semestre
SET last_modification = NOW()
WHERE last_modification IS NULL;

ALTER TABLE semestre
    MODIFY COLUMN last_modification DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)';

ALTER TABLE ue
    ADD COLUMN IF NOT EXISTS validation_status VARCHAR (16) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS validation_dirty TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS validation_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';

UPDATE semestre
SET validation_status = 'incomplete'
WHERE validation_status IS NULL;

UPDATE ue
SET validation_status = 'incomplete'
WHERE validation_status IS NULL;

UPDATE element_constitutif
SET validation_status = 'incomplete'
WHERE validation_status IS NULL;


CREATE TABLE volume_horaire_parcours
(
    id                   INT AUTO_INCREMENT NOT NULL,
    parcours_id          INT              NOT NULL,
    campagne_collecte_id INT              NOT NULL,
    heures_cm_pres       DOUBLE PRECISION NOT NULL,
    heures_td_pres       DOUBLE PRECISION NOT NULL,
    heures_tp_pres       DOUBLE PRECISION NOT NULL,
    heures_te_pres       DOUBLE PRECISION NOT NULL,
    heures_cm_dist       DOUBLE PRECISION NOT NULL,
    heures_td_dist       DOUBLE PRECISION NOT NULL,
    heures_tp_dist       DOUBLE PRECISION NOT NULL,
    volumes_annee        JSON DEFAULT NULL,
    volumes_semestre     JSON DEFAULT NULL,
    date_calcul          DATETIME         NOT NULL,
    INDEX                IDX_9596F3826E38C0DB (parcours_id),
    INDEX                IDX_9596F382D2CFEAA (campagne_collecte_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
ALTER TABLE volume_horaire_parcours
    ADD CONSTRAINT FK_9596F3826E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id);
ALTER TABLE volume_horaire_parcours
    ADD CONSTRAINT FK_9596F382D2CFEAA FOREIGN KEY (campagne_collecte_id) REFERENCES campagne_collecte (id);


ALTER TABLE change_rf
    ADD date_prise_fonction DATETIME DEFAULT NULL;
