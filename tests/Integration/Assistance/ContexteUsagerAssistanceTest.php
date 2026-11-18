<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\Entity\CampagneCollecte;
use App\Entity\DemandeAssistance;
use App\Entity\Profil;
use App\Entity\TimelineDate;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CampagneModuleEnum;
use App\Service\Assistance\ContexteUsagerAssistance;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use DateTime;

class ContexteUsagerAssistanceTest extends TestCase
{
    use DatabaseTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    private function demande(string $email): DemandeAssistance
    {
        return (new DemandeAssistance())->setSujet('Accès')->setSujetNormalise('accès')->setDemandeurEmail($email);
    }

    private function usagerAvecProfilSur(CampagneCollecte $campagne): User
    {
        $user = new User();
        $user->setUsername('usager-contexte');
        $user->setEmail('usager-contexte@univ-test.fr');
        $user->setNom('Lignon');
        $user->setPrenom('Jean');
        $profil = (new Profil())->setLibelle('Responsable DPE test')->setCode('test_dpe');
        $userProfil = (new UserProfil())->setUser($user)->setProfil($profil)->setCampagneCollecte($campagne);
        $user->getUserProfils()->add($userProfil);
        $this->persistAll([$user, $profil, $userProfil]);

        return $user;
    }

    private function campagneSansEtape(): CampagneCollecte
    {
        $campagne = (new CampagneCollecte())->setLibelle('Campagne vide test')->setAnnee(2099)->setCodeApogee('T');
        $this->persist($campagne);

        return $campagne;
    }

    public function testSansUtilisateurConnuRenvoieNull(): void
    {
        self::assertNull($this->getService(ContexteUsagerAssistance::class)->pour($this->demande('inconnu@univ-test.fr')));
    }

    private function campagneOuverte(): CampagneCollecte
    {
        $campagne = (new CampagneCollecte())->setLibelle('Campagne ouverte test')->setAnnee(2098)->setCodeApogee('U');
        $etape = (new TimelineDate())
            ->setLibelle('Saisie')
            ->setIcone('mdi:pencil')
            ->setInTimeline(true)
            ->setDateDebut(new DateTime('-1 day'))
            ->setDate(new DateTime('+10 days'))
            ->setModulesActifs(array_map(static fn (CampagneModuleEnum $m) => $m->value, CampagneModuleEnum::cases()));
        $campagne->addTimelineDate($etape);
        $this->persistAll([$campagne, $etape]);

        return $campagne;
    }

    public function testCampagnePasseeDUnProfilEstMasquee(): void
    {
        $campagne = $this->campagneSansEtape();
        $this->usagerAvecProfilSur($campagne);

        $contexte = $this->getService(ContexteUsagerAssistance::class)->pour($this->demande('usager-contexte@univ-test.fr'));

        self::assertNotContains($campagne, array_map(static fn (array $c) => $c['campagne'], $contexte['campagnes']));
        self::assertCount(1, $contexte['profils']);
    }

    public function testCampagneOuverteDUnProfilEstResumeeEnUneLigne(): void
    {
        $defaut = $this->entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        self::assertNotNull($defaut, 'Les fixtures de test doivent fournir une campagne par défaut.');
        $campagne = $this->campagneOuverte();
        $user = $this->usagerAvecProfilSur($campagne);

        $contexte = $this->getService(ContexteUsagerAssistance::class)->pour($this->demande('usager-contexte@univ-test.fr'));

        self::assertSame($user, $contexte['user']);
        self::assertSame([$defaut, $campagne], array_map(static fn (array $c) => $c['campagne'], $contexte['campagnes']));
        $bloc = $contexte['campagnes'][1];
        self::assertSame('ouvertes', $bloc['etat']);
        self::assertSame(sprintf('Toutes les saisies sont ouvertes jusqu\'au %s.', (new DateTime('+10 days'))->format('d/m/Y')), $bloc['resume']);
        self::assertSame([], $bloc['details']);
    }
}
