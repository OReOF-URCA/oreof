/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/migrations/app/v2/20260613_192700__add_annees_to_type_diplome_plateforme_admission.sql
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 13/06/2026 19:27
 */

-- v2 - Ajout du champ annees pour les plateformes d'admission
-- Cible: MySQL uniquement

ALTER TABLE type_diplome_plateforme_admission
ADD COLUMN annees JSON NULL COMMENT 'Années concernées par la plateforme (ex: [1, 2, 3])';
