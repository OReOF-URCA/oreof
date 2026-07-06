```mysql
CREATE TABLE parcours_ramification 
    (
        id INT AUTO_INCREMENT NOT NULL, 
        type_ramification_id INT NOT NULL, 
        parcours_origine_id INT NOT NULL, 
        parcours_cible_id INT NOT NULL, 
        INDEX IDX_60B5422FE1B0C6E9 (type_ramification_id), 
        INDEX IDX_60B5422FF31368AE (parcours_origine_id), 
        INDEX IDX_60B5422F2BE9C1F6 (parcours_cible_id), 
        PRIMARY KEY(id)
    ) 
DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;

CREATE TABLE type_ramification_parcours 
    (
        id INT AUTO_INCREMENT NOT NULL, 
        code VARCHAR(255) NOT NULL, 
        libelle LONGTEXT NOT NULL, 
        PRIMARY KEY(id)
    ) 
DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;

ALTER TABLE parcours_ramification ADD CONSTRAINT FK_60B5422FE1B0C6E9 FOREIGN KEY (type_ramification_id) REFERENCES type_ramification_parcours (id);
ALTER TABLE parcours_ramification ADD CONSTRAINT FK_60B5422FF31368AE FOREIGN KEY (parcours_origine_id) REFERENCES parcours (id);
ALTER TABLE parcours_ramification ADD CONSTRAINT FK_60B5422F2BE9C1F6 FOREIGN KEY (parcours_cible_id) REFERENCES parcours (id);
```