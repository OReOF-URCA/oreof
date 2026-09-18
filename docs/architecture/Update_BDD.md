
```mysql
ALTER TABLE dpe_demande ADD created DATETIME NOT NULL, ADD updated DATETIME NOT NULL;
ALTER TABLE dpe_demande ADD date_cloture DATETIME DEFAULT NULL;

ALTER TABLE dpe_demande ADD auteur_id INT DEFAULT NULL;
ALTER TABLE dpe_demande ADD CONSTRAINT FK_5CC9FC9460BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id);
CREATE INDEX IDX_5CC9FC9460BB6FE6 ON dpe_demande (auteur_id);
ALTER TABLE fiche_matiere ADD quitus TINYINT(1) DEFAULT NULL;
ALTER TABLE plateforme_admission ADD mode_export VARCHAR(30) DEFAULT 'global' NOT NULL;
ALTER TABLE plateforme_admission_parametre ADD remarques LONGTEXT DEFAULT NULL;
ALTER TABLE etablissement ADD email_oreof VARCHAR(255) DEFAULT NULL;
ALTER TABLE etablissement CHANGE email_central email_central VARCHAR(255) DEFAULT NULL;
```


