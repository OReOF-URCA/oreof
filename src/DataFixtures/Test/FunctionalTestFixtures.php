<?php

declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Profil;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Entity\TypeDiplome;
use App\Enums\CentreGestionEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Minimal, deterministic functional dataset.
 *
 * Keep this fixture deliberately small: its purpose is to provide stable
 * entities for route/controller tests, not to reproduce production data.
 */
final class FunctionalTestFixtures extends Fixture implements FixtureGroupInterface
{
    public const USER_ADMIN = 'test.user.admin';
    public const COMPOSANTE = 'test.composante';
    public const FORMATION = 'test.formation';
    public const PARCOURS = 'test.parcours';
    public const FICHE_MATIERE = 'test.fiche_matiere';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = (new User())
            ->setEmail('admin-test@oreof.invalid')
            ->setUsername('admin-test')
            ->setRoles(['ROLE_ADMIN']);

        $admin->setNom('Admin');
        $admin->setPrenom('Test');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'test'));

        $profil = (new Profil())
            ->setLibelle('Administrateur de test')
            ->setCode('TEST_ADMIN')
            ->setCentre(CentreGestionEnum::CENTRE_GESTION_COMPOSANTE);

        $composante = (new Composante())
            ->setLibelle('Composante de test')
            ->setSigle('TEST')
            ->setCodeComposante('TST')
            ->setDirecteur($admin)
            ->setResponsableDpe($admin);

        $campagne = (new CampagneCollecte())
            ->setLibelle('Campagne de test')
            ->setAnnee(2026)
            ->setDefaut(true)
            ->setCodeApogee('T');

        $typeDiplome = (new TypeDiplome())
            ->setLibelle('Diplôme de test')
            ->setLibelleCourt('TEST')
            ->setNbUeMin(1)
            ->setNbUeMax(10)
            ->setNbEctsMaxUe(30)
            ->setNbEcParUe(10)
            ->setModeleMcc('test')
            ->setCodeApogee('T');

        $formation = (new Formation($campagne))
            ->setSigle('TEST-FORM')
            ->setTypeDiplome($typeDiplome)
            ->setMentionTexte('Formation de test')
            ->setComposantePorteuse($composante);

        $parcours = (new Parcours($formation))
            ->setLibelle('Parcours de test')
            ->setSigle('TEST-P');

        $fiche = (new FicheMatiere())
            ->setLibelle('Fiche matière de test')
            ->setSigle('TEST-FM')
            ->setParcours($parcours);

        $userProfil = (new UserProfil())
            ->setUser($admin)
            ->setProfil($profil)
            ->setComposante($composante)
            ->setFormation($formation)
            ->setParcours($parcours)
            ->setCampagneCollecte($campagne);

        foreach ([$admin, $profil, $composante, $campagne, $typeDiplome, $formation, $parcours, $fiche, $userProfil] as $entity) {
            $manager->persist($entity);
        }

        $manager->flush();

        $this->addReference(self::USER_ADMIN, $admin);
        $this->addReference(self::COMPOSANTE, $composante);
        $this->addReference(self::FORMATION, $formation);
        $this->addReference(self::PARCOURS, $parcours);
        $this->addReference(self::FICHE_MATIERE, $fiche);
    }
}
