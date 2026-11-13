<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\Entity\CampagneCollecte;
use App\Entity\DemandeAssistance;
use App\Entity\Profil;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Service\Assistance\ContexteUsagerAssistance;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;

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

    public function testCampagneSansEtapeAfficheAucunePeriode(): void
    {
        $campagne = $this->campagneSansEtape();
        $this->usagerAvecProfilSur($campagne);

        $contexte = $this->getService(ContexteUsagerAssistance::class)->pour($this->demande('usager-contexte@univ-test.fr'));

        $bloc = array_values(array_filter($contexte['campagnes'], static fn (array $c) => $c['campagne'] === $campagne))[0];
        self::assertCount(5, $bloc['modules']);
        self::assertSame('Aucune période de saisie configurée pour ce module.', $bloc['modules'][0]['message']);
        self::assertCount(1, $contexte['profils']);
    }

    public function testCampagnesDesProfilsSontIncluses(): void
    {
        $defaut = $this->entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        self::assertNotNull($defaut, 'Les fixtures de test doivent fournir une campagne par défaut.');
        $campagne = $this->campagneSansEtape();
        $user = $this->usagerAvecProfilSur($campagne);

        $contexte = $this->getService(ContexteUsagerAssistance::class)->pour($this->demande('usager-contexte@univ-test.fr'));

        self::assertSame($user, $contexte['user']);
        self::assertSame([$defaut, $campagne], array_map(static fn (array $c) => $c['campagne'], $contexte['campagnes']));
    }
}
