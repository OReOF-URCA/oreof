
-- Pour choisir si on initialise les parcours d'un diplôme avec l'état de modification sans CFVU.
ALTER TABLE type_diplome ADD passage_cfvu BOOLEAN DEFAULT true NOT NULL;


-- Pour importer un PDF de la maquette sur les parcours non classiques (qui n'ont pas d'onglet "structure")
ALTER TABLE parcours ADD maquette_pdf VARCHAR(255) DEFAULT NULL, ADD maquette_pdf_nom_original VARCHAR(255) DEFAULT NULL;