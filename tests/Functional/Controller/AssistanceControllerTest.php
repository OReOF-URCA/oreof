<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Entity\PieceJointeAssistance;
use App\Entity\User;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

class AssistanceControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    /** @var list<int> */
    private array $demandes = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        foreach ($this->demandes as $id) {
            $demande = $this->em->find(DemandeAssistance::class, $id);
            if ($demande !== null) {
                $this->em->remove($demande);
            }
        }
        $this->em->flush();
        (new Filesystem())->remove((string) static::getContainer()->getParameter('assistance_pieces_jointes_dir'));
        parent::tearDown();
    }

    private function utilisateur(string $username, array $roles): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($user === null) {
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($username . '@univ-test.fr');
            $user->setNom('Test');
            $user->setPrenom($username);
            $user->setRoles($roles);
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }

    private function demande(string $sujet, StatutDemandeAssistanceEnum $statut = StatutDemandeAssistanceEnum::NOUVELLE): DemandeAssistance
    {
        $demande = (new DemandeAssistance())
            ->setSujet($sujet)
            ->setSujetNormalise(mb_strtolower($sujet))
            ->setDemandeurEmail('usager-assistance@univ-test.fr')
            ->setStatut($statut);
        $demande->addMessage(
            (new MessageAssistance())
                ->setSens(SensMessageAssistanceEnum::ENTRANT)
                ->setMessageId('<' . uniqid('test', true) . '@univ-test.fr>')
                ->setExpediteurEmail('usager-assistance@univ-test.fr')
                ->setSujet($sujet)
                ->setCorpsTexte('Bonjour, je n\'ai pas accès.')
        );
        $this->em->persist($demande);
        $this->em->flush();
        $this->demandes[] = $demande->getId();

        return $demande;
    }

    private function connecterAdmin(): User
    {
        $admin = $this->utilisateur('admin-test-assistance', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        return $admin;
    }

    private function jeton(string $url, string $action): string
    {
        $crawler = $this->client->request('GET', $url);

        return (string) $crawler->filter(sprintf('form[action$="%s"] input[name="_token"]', $action))->attr('value');
    }

    public function testUtilisateurNonAdminEstRefuse(): void
    {
        $this->client->loginUser($this->utilisateur('user-test-assistance', ['ROLE_USER']));
        $this->client->request('GET', '/administration/assistance');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListeAfficheLesDemandesOuvertes(): void
    {
        $this->demande('Sujet ouvert assistance');
        $this->demande('Sujet résolu assistance', StatutDemandeAssistanceEnum::RESOLUE);
        $this->connecterAdmin();

        $crawler = $this->client->request('GET', '/administration/assistance');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Sujet ouvert assistance', $crawler->text());
        self::assertStringNotContainsString('Sujet résolu assistance', $crawler->text());
    }

    public function testFicheAfficheLeFil(): void
    {
        $demande = $this->demande('Fiche assistance');
        $this->connecterAdmin();

        $crawler = $this->client->request('GET', '/administration/assistance/' . $demande->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('je n\'ai pas accès', $crawler->text());
    }

    public function testChangerLeStatut(): void
    {
        $demande = $this->demande('Statut assistance');
        $this->connecterAdmin();
        $url = '/administration/assistance/' . $demande->getId();

        $this->client->request('POST', $url . '/statut', ['statut' => 'resolue', '_token' => $this->jeton($url, '/statut')]);

        self::assertResponseRedirects($url);
        $this->em->clear();
        self::assertSame(StatutDemandeAssistanceEnum::RESOLUE, $this->em->find(DemandeAssistance::class, $demande->getId())->getStatut());
    }

    public function testStatutSansJetonEstRefuse(): void
    {
        $demande = $this->demande('Jeton assistance');
        $this->connecterAdmin();

        $this->client->request('POST', '/administration/assistance/' . $demande->getId() . '/statut', ['statut' => 'resolue']);

        $this->em->clear();
        self::assertSame(StatutDemandeAssistanceEnum::NOUVELLE, $this->em->find(DemandeAssistance::class, $demande->getId())->getStatut());
    }

    public function testMePrendreEnChargeAssigneEtPasseEnCours(): void
    {
        $demande = $this->demande('Responsable assistance');
        $admin = $this->connecterAdmin();
        $url = '/administration/assistance/' . $demande->getId();

        $this->client->request('POST', $url . '/responsable', ['responsable' => (string) $admin->getId(), '_token' => $this->jeton($url, '/responsable')]);

        $this->em->clear();
        $relue = $this->em->find(DemandeAssistance::class, $demande->getId());
        self::assertSame($admin->getId(), $relue->getResponsable()?->getId());
        self::assertSame(StatutDemandeAssistanceEnum::EN_COURS, $relue->getStatut());
    }

    public function testAjouterUneNoteCreeUnMessageNote(): void
    {
        $demande = $this->demande('Note assistance');
        $this->connecterAdmin();
        $url = '/administration/assistance/' . $demande->getId();

        $this->client->request('POST', $url . '/note', ['contenu' => 'Vérifier la campagne de son profil', '_token' => $this->jeton($url, '/note')]);

        self::assertResponseRedirects($url);
        $this->em->clear();
        $messages = $this->em->find(DemandeAssistance::class, $demande->getId())->getMessages();
        self::assertCount(2, $messages);
        self::assertSame(SensMessageAssistanceEnum::NOTE, $messages->last()->getSens());
        self::assertSame('Vérifier la campagne de son profil', $messages->last()->getCorpsTexte());
    }

    public function testPieceJointeTelechargeable(): void
    {
        $demande = $this->demande('PJ assistance');
        $dossier = (string) static::getContainer()->getParameter('assistance_pieces_jointes_dir');
        (new Filesystem())->dumpFile($dossier . '/2026/11/pj-test', '%PDF-test');
        $piece = (new PieceJointeAssistance())
            ->setNomOriginal('fiche.pdf')
            ->setMimeType('application/pdf')
            ->setTaille(9)
            ->setChemin('2026/11/pj-test');
        $demande->getMessages()->first()->addPieceJointe($piece);
        $this->em->flush();
        $this->connecterAdmin();

        $this->client->request('GET', '/administration/assistance/piece-jointe/' . $piece->getId());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('content-disposition'));
    }

    public function testAnnonceNeProposePasDeRepondre(): void
    {
        $demande = $this->demande('Annonce assistance')->setAnnonce(true);
        $this->em->flush();
        $this->connecterAdmin();
        $url = '/administration/assistance/' . $demande->getId();

        $crawler = $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action$="/repondre"]'));
        self::assertStringContainsString('Annonce', $crawler->filter('main')->text());

        $this->client->request('POST', $url . '/repondre', [
            'destinataires' => 'usager-assistance@univ-test.fr',
            'corps' => 'Réponse à tous',
            '_token' => $this->jeton($url, '/note'),
        ]);
        self::assertEmailCount(0);
    }

    public function testDestinatairesNombreuxSontRaccourcis(): void
    {
        $demande = $this->demande('Destinataires assistance');
        $demande->getMessages()->first()->setDestinataires(array_map(static fn (int $i) => sprintf('d%d@univ-test.fr', $i), range(1, 20)));
        $this->em->flush();
        $this->connecterAdmin();

        $crawler = $this->client->request('GET', '/administration/assistance/' . $demande->getId());

        self::assertStringContainsString('et 15 autres', $crawler->text());
        self::assertCount(1, $crawler->filter('details:contains("d20@univ-test.fr")'));
    }

    public function testHistoriqueCiteEstReplie(): void
    {
        $demande = $this->demande('Historique assistance');
        $demande->getMessages()->first()->setCorpsHtml('<p>Merci pour la correction.</p><p>Le 12/11/2026 à 17:58, Emeline a écrit :</p><blockquote><p>Ancien message cité</p></blockquote>');
        $this->em->flush();
        $this->connecterAdmin();

        $crawler = $this->client->request('GET', '/administration/assistance/' . $demande->getId());

        $historique = $crawler->filter('details.assistance-historique');
        self::assertCount(1, $historique);
        self::assertStringContainsString('Ancien message cité', $historique->text());
        self::assertStringNotContainsString('Merci pour la correction', $historique->text());
        self::assertStringContainsString('Merci pour la correction', $crawler->text());
    }

    public function testRepondreEnvoieLeMailEtEnregistreLaReponse(): void
    {
        $demande = $this->demande('Réponse assistance');
        $this->connecterAdmin();
        $url = '/administration/assistance/' . $demande->getId();

        $this->client->request('POST', $url . '/repondre', [
            'destinataires' => 'usager-assistance@univ-test.fr',
            'corps' => '<div>Bonjour, c\'est réglé.</div>',
            '_token' => $this->jeton($url, '/repondre'),
        ]);

        self::assertResponseRedirects($url);
        self::assertEmailCount(1);
        $this->em->clear();
        $relue = $this->em->find(DemandeAssistance::class, $demande->getId());
        self::assertSame(SensMessageAssistanceEnum::SORTANT, $relue->getMessages()->last()->getSens());
        self::assertSame(StatutDemandeAssistanceEnum::EN_ATTENTE_USAGER, $relue->getStatut());
    }

    public function testFicheAfficheLeContexteDuDemandeurConnu(): void
    {
        $admin = $this->connecterAdmin();
        $demande = $this->demande('Contexte assistance');
        $demande->setDemandeurEmail((string) $admin->getEmail());
        $this->em->flush();

        $crawler = $this->client->request('GET', '/administration/assistance/' . $demande->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Contexte ORéOF du demandeur', $crawler->text());
        self::assertStringContainsString('Périodes de saisie', $crawler->text());
        self::assertSelectorExists('a[href*="_switch_user=admin-test-assistance"]');
    }
}
