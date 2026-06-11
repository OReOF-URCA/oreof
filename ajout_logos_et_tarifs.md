```mysql
ALTER TABLE type_diplome ADD logo JSON DEFAULT NULL COMMENT '(DC2Type:json)';

ALTER TABLE parcours ADD logo JSON DEFAULT NULL COMMENT '(DC2Type:json)';

ALTER TABLE etablissement_information ADD tarif_inscription LONGTEXT DEFAULT NULL;
```