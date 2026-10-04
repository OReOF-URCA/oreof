<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DataFixtures/StructureEtablissementFixtures.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\DataFixtures;

use App\Entity\Composante;
use App\Entity\Domaine;
use App\Entity\Etablissement;
use App\Entity\Ville;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class StructureEtablissementFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $etablissement = new Etablissement();
        $etablissement->setLibelle('Université de Reims Champagne-Ardenne');
        $manager->persist($etablissement);

        $site = new Ville();
        $site->setLibelle('Reims');
        $site->setEtablissement($etablissement);
        $manager->persist($site);

        $site = new Ville();
        $site->setLibelle('Châlons en Champagne');
        $site->setEtablissement($etablissement);
        $manager->persist($site);

        $site = new Ville();
        $site->setLibelle('Troyes');
        $site->setEtablissement($etablissement);
        $manager->persist($site);

        $site = new Ville();
        $site->setLibelle('Charleville-Mézières');
        $site->setEtablissement($etablissement);
        $manager->persist($site);

        $site = new Ville();
        $site->setLibelle('Chaumont');
        $site->setEtablissement($etablissement);
        $manager->persist($site);

        $site = new Ville();
        $site->setLibelle('Colmar (UHA)');
        $site->setEtablissement($etablissement);
        $manager->persist($site);

        $domaine = new Domaine();
        $domaine->setLibelle('Arts, lettres, langues');
        $domaine->setSigle('All');
        $manager->persist($domaine);

        $domaine = new Domaine();
        $domaine->setLibelle('Droit, économie, gestion');
        $domaine->setSigle('DEG');
        $manager->persist($domaine);

        $domaine = new Domaine();
        $domaine->setLibelle('Sciences humaines et sociales');
        $domaine->setSigle('SHS');
        $this->addReference('domaine_shs', $domaine);
        $manager->persist($domaine);

        $domaine = new Domaine();
        $domaine->setLibelle('Sciences, technologies, santé');
        $domaine->setSigle('STS');
        $this->addReference('domaine_st', $domaine);
        $manager->persist($domaine);

        $domaine = new Domaine();
        $domaine->setLibelle('Enseignement');
        $domaine->setSigle('Ens.');
        $manager->persist($domaine);

        $domaine = new Domaine();
        $domaine->setLibelle('Santé');
        $domaine->setSigle('Santé');
        $manager->persist($domaine);

        $domaine = new Domaine();
        $domaine->setLibelle('Autre');
        $domaine->setSigle('Autre');
        $manager->persist($domaine);

        $composante = new Composante();
        $composante->setLibelle('Faculté des Sciences');
        $composante->setDirecteur($this->getReference(UsersFixtures::RESPONSABLE_DPE_COMPOSANTE, \App\Entity\User::class));

        $manager->persist($composante);

        $composante = new Composante();
        $composante->setLibelle('IUT de Troyes');
        $composante->setDirecteur(null);
        $manager->persist($composante);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UsersFixtures::class,
        ];
    }
}
