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
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Entity\ElementConstitutif;
use App\Entity\AnneeUniversitaire;
use App\Entity\DpeParcours;
use App\Entity\ChangeRf;
use DateTime;
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
    public const SEMESTRE = 'test.semestre';
    public const UE = 'test.ue';
    public const EC = 'test.ec';
    public const DPE_PARCOURS = 'test.dpe_parcours';
    public const CHANGE_RF = 'test.change_rf';

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

        $anneeUniversitaire = (new AnneeUniversitaire())
            ->setLibelle('2026-2027')
            ->setAnnee(2026);

        $campagne = (new CampagneCollecte())
            ->setLibelle('Campagne de test')
            ->setAnnee(2026)
            ->setDefaut(true)
            ->setCodeApogee('T')
            ->setAnneeUniversitaire($anneeUniversitaire);

        $typeDiplome = (new TypeDiplome())
            ->setLibelle('Diplôme de test')
            ->setLibelleCourt('DU')
            ->setNbUeMin(1)
            ->setNbUeMax(10)
            ->setNbEctsMaxUe(30)
            ->setNbEcParUe(10)
            ->setModeleMcc('App\\TypeDiplome\\Du\\DuHandler')
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

        $semestre = (new Semestre())
            ->setOrdre(1)
            ->setTroncCommun(false)
            ->setNonDispense(false);

        $semestreParcours = (new SemestreParcours($semestre, $parcours))
            ->setOrdre(1)
            ->setPorteur(true)
            ->setOuvert(true);

        $ue = (new Ue())
            ->setOrdre(1)
            ->setLibelle('UE de test')
            ->setEcts(6.0)
            ->setSemestre($semestre);

        $ec = (new ElementConstitutif())
            ->setCode('TEST-EC')
            ->setOrdre(1)
            ->setLibelle('EC de test')
            ->setEcts(3.0)
            ->setParcours($parcours)
            ->setUe($ue)
            ->setFicheMatiere($fiche);

        $dpeParcours = (new DpeParcours())
            ->setCampagneCollecte($campagne)
            ->setFormation($formation)
            ->setParcours($parcours)
            ->setEtatValidation([]);

        $changeRf = (new ChangeRf())
            ->setFormation($formation)
            ->setCampagneCollecte($campagne)
            ->setAncienResponsable($admin)
            ->setNouveauResponsable($admin)
            ->setDateDemande(new DateTime())
            ->setEtatDemande([]);

        $userProfil = (new UserProfil())
            ->setUser($admin)
            ->setProfil($profil)
            ->setComposante($composante)
            ->setFormation($formation)
            ->setParcours($parcours)
            ->setCampagneCollecte($campagne);

        foreach ([$admin, $profil, $composante, $anneeUniversitaire, $campagne, $typeDiplome, $formation, $parcours, $fiche, $semestre, $semestreParcours, $ue, $ec, $dpeParcours, $changeRf, $userProfil] as $entity) {
            $manager->persist($entity);
        }

        $manager->flush();

        $this->addReference(self::USER_ADMIN, $admin);
        $this->addReference(self::COMPOSANTE, $composante);
        $this->addReference(self::FORMATION, $formation);
        $this->addReference(self::PARCOURS, $parcours);
        $this->addReference(self::FICHE_MATIERE, $fiche);
        $this->addReference(self::SEMESTRE, $semestre);
        $this->addReference(self::UE, $ue);
        $this->addReference(self::EC, $ec);
        $this->addReference(self::DPE_PARCOURS, $dpeParcours);
        $this->addReference(self::CHANGE_RF, $changeRf);
    }
}
