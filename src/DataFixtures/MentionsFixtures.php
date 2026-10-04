<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DataFixtures/MentionsFixtures.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/02/2023 22:12
 */

namespace App\DataFixtures;

use App\Entity\Mention;
use App\TypeDiplome\Source\ButTypeDiplome;
use App\TypeDiplome\Source\MasterTypeDiplome;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MentionsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i < 12; $i++) {
            $mention = new Mention();

            $mention->setLibelle('Mention ' . $i);
            $mention->setSigle('M' . $i);

            if ($i % 2 === 1) {
                $mention->addDomaine($this->getReference('domaine_st', \App\Entity\Domaine::class));
            } else {
                $mention->addDomaine($this->getReference('domaine_shs', \App\Entity\Domaine::class));
            }
            $manager->persist($mention);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            StructureEtablissementFixtures::class,
        ];
    }
}
