-- Ajout de la colonne logo à la table composante pour l'icône/logo de la composante (plaquette et fiche)
ALTER TABLE composante ADD logo VARCHAR(255) DEFAULT NULL;
