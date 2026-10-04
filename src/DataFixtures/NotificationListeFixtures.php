<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DataFixtures/NotificationListeFixtures.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/01/2023 21:04
 */

namespace App\DataFixtures;

use App\Entity\NotificationListe;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NotificationListeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $steps = [
            ['workflow' => 'dpe_parcours', 'step' => 'ouverture_campagne'],
            ['workflow' => 'dpe_parcours', 'step' => 'responsables_composantes'],
            ['workflow' => 'dpe_parcours', 'step' => 'responsables_mentions'],
            ['workflow' => 'dpe_parcours', 'step' => 'associer_redacteurs'],
            ['workflow' => 'dpe_parcours', 'step' => 'soumission_fiche_ec'],
            ['workflow' => 'dpe_parcours', 'step' => 'validation_fiche_ec'],
            ['workflow' => 'dpe_parcours', 'step' => 'reserve_fiche_ec'],
            ['workflow' => 'dpe_parcours', 'step' => 'soumission_dpe'],
            ['workflow' => 'dpe_parcours', 'step' => 'reception_reserves_dpe'],
            ['workflow' => 'dpe_parcours', 'step' => 'visa_projet_dpe'],
            ['workflow' => 'dpe_parcours', 'step' => 'soumission_conseil'],
            ['workflow' => 'dpe_parcours', 'step' => 'avis_conseil'],
            ['workflow' => 'dpe_parcours', 'step' => 'validation_dpe'],
            ['workflow' => 'dpe_parcours', 'step' => 'soumission_central'],
            ['workflow' => 'dpe_parcours', 'step' => 'reserves_central'],
            ['workflow' => 'dpe_parcours', 'step' => 'visa_direct_dpe'],
            ['workflow' => 'dpe_parcours', 'step' => 'transmission_vp'],
            ['workflow' => 'dpe_parcours', 'step' => 'avis_central'],
            ['workflow' => 'dpe_parcours', 'step' => 'visa_central'],
            ['workflow' => 'dpe_parcours', 'step' => 'soumission_cfvu'],
            ['workflow' => 'dpe_parcours', 'step' => 'avis_cfvu'],
            ['workflow' => 'dpe_parcours', 'step' => 'visa_publication'],
        ];

        foreach ($steps as $stepData) {
            $n = new NotificationListe();
            $n->setWorkflow($stepData['workflow']);
            $n->setStep($stepData['step']);
            $manager->persist($n);
        }

        $manager->flush();
    }
}
