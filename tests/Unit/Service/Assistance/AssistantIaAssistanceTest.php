<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Enums\SensMessageAssistanceEnum;
use App\Service\Ai\FicheMatiereAiSynthesizer;
use App\Service\Assistance\AssistantIaAssistance;
use App\Service\Assistance\SeparateurCitation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AssistantIaAssistanceTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $requetes = [];

    private function assistant(?string $cle = 'sk-test', ?string $reponse = 'Texte généré'): AssistantIaAssistance
    {
        $this->requetes = [];
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use ($reponse): MockResponse {
            $this->requetes[] = ['url' => $url, 'corps' => json_decode((string) $options['body'], true)];

            return new MockResponse((string) json_encode(['choices' => [['message' => ['content' => $reponse]]]]));
        });
        $config = new FicheMatiereAiSynthesizer(
            httpClient: new MockHttpClient(),
            cache: new ArrayAdapter(),
            envAiUrl: 'https://romeogate.example/llm',
            envAiKey: $cle,
            envAiModel: 'mistral-small-3.2-24b',
        );

        return new AssistantIaAssistance($http, new ArrayAdapter(), $config, new SeparateurCitation());
    }

    private function message(SensMessageAssistanceEnum $sens, string $nom, string $email, string $texte, string $date): MessageAssistance
    {
        return (new MessageAssistance())
            ->setSens($sens)
            ->setExpediteurNom($nom)
            ->setExpediteurEmail($email)
            ->setSujet('Accès MCCC')
            ->setCorpsTexte($texte)
            ->setDateEnvoi(new DateTimeImmutable($date));
    }

    private function demande(): DemandeAssistance
    {
        $demande = (new DemandeAssistance())
            ->setSujet('Accès MCCC')
            ->setDemandeurEmail('jean.dupont@univ-reims.fr')
            ->setDemandeurNom('Jean Dupont');
        $demande->addMessage($this->message(SensMessageAssistanceEnum::ENTRANT, 'Jean Dupont', 'jean.dupont@univ-reims.fr',
            "Je n'arrive pas à saisir les MCCC du parcours Chimie.\n\nLe 3 nov. 2026, X a écrit :\n> vieux message cité", '2026-11-10 09:00'));
        $demande->addMessage($this->message(SensMessageAssistanceEnum::NOTE, 'Admin ORéOF', 'admin@univ-reims.fr',
            'Vérifier ses droits sur le parcours.', '2026-11-10 10:00'));

        return $demande;
    }

    public function testConversationEcarteHistoriqueCiteEtAdressesMail(): void
    {
        $conversation = $this->assistant()->conversation($this->demande());

        self::assertStringContainsString('Sujet : Accès MCCC', $conversation);
        self::assertStringContainsString("[10/11/2026 09:00] Usager (Jean Dupont) :\nJe n'arrive pas à saisir les MCCC du parcours Chimie.", $conversation);
        self::assertStringContainsString("Note interne de l'équipe (Admin ORéOF) :\nVérifier ses droits sur le parcours.", $conversation);
        self::assertStringNotContainsString('vieux message cité', $conversation);
        self::assertStringNotContainsString('@', $conversation, 'Les adresses mail ne sont pas envoyées au modèle');
    }

    public function testResumeAppelleOratioAvecLaConversation(): void
    {
        $assistant = $this->assistant(reponse: "Problème : saisie MCCC impossible.\nReste à faire : vérifier ses droits.");

        $resume = $assistant->resumer($this->demande());

        self::assertSame("Problème : saisie MCCC impossible.\nReste à faire : vérifier ses droits.", $resume);
        self::assertCount(1, $this->requetes);
        self::assertSame('https://romeogate.example/llm/chat/completions', $this->requetes[0]['url']);
        self::assertSame('mistral-small-3.2-24b', $this->requetes[0]['corps']['model']);
        self::assertStringContainsString('Problème', $this->requetes[0]['corps']['messages'][0]['content']);
        self::assertStringContainsString('parcours Chimie', $this->requetes[0]['corps']['messages'][1]['content']);
    }

    public function testResultatMisEnCacheSaufSiForce(): void
    {
        $assistant = $this->assistant();
        $demande = $this->demande();

        $assistant->resumer($demande);
        $assistant->resumer($demande);
        self::assertCount(1, $this->requetes, 'Deuxième appel servi par le cache');

        $assistant->resumer($demande, true);
        self::assertCount(2, $this->requetes);
    }

    public function testNouveauMessageInvalideLeCache(): void
    {
        $assistant = $this->assistant();
        $demande = $this->demande();
        $assistant->resumer($demande);

        $demande->addMessage($this->message(SensMessageAssistanceEnum::ENTRANT, 'Jean Dupont', 'jean.dupont@univ-reims.fr', 'Toujours bloqué.', '2026-11-11 08:00'));
        $assistant->resumer($demande);

        self::assertCount(2, $this->requetes);
    }

    public function testBrouillonUtiliseUneConsigneDeReponse(): void
    {
        $assistant = $this->assistant(reponse: "Bonjour,\n\nVos droits sont corrigés.\n\nCordialement,\nL'équipe ORéOF");

        $brouillon = $assistant->suggererReponse($this->demande());

        self::assertStringStartsWith('Bonjour', $brouillon);
        self::assertStringContainsString('[à compléter]', $this->requetes[0]['corps']['messages'][0]['content']);
    }

    public function testSansCleIaNonConfigureeEtAucunAppel(): void
    {
        $assistant = $this->assistant(cle: '');

        self::assertFalse($assistant->estConfigure());
        $this->expectException(RuntimeException::class);
        $assistant->resumer($this->demande());
    }

    public function testReponseVideEstUneErreur(): void
    {
        $this->expectException(RuntimeException::class);
        $this->assistant(reponse: '  ')->suggererReponse($this->demande());
    }

    public function testEnHtmlEchappeEtDecoupeLesParagraphes(): void
    {
        self::assertSame(
            '<div>Bonjour &lt;b&gt;,</div><div><br></div><div>Ligne 1<br>Ligne 2</div>',
            AssistantIaAssistance::enHtml("Bonjour <b>,\n\nLigne 1\nLigne 2"),
        );
    }
}
