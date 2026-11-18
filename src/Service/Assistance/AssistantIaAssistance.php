<?php

namespace App\Service\Assistance;

use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Enums\SensMessageAssistanceEnum;
use App\Service\Ai\FicheMatiereAiSynthesizer;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Résumé d'une demande d'assistance et brouillon de réponse par le LLM de l'établissement (Oratio / ROMEO),
 * avec la configuration IA commune (Établissement > APIs tierces, sinon variables AI_*).
 *
 * Envoyé : contexte ORéOF de l'usager (profils, périodes de saisie…) et contenu propre des messages (sans historique
 * cité, sans adresse mail ni pièce jointe). Résultat en cache selon ce contenu : un nouveau message ou un changement de
 * contexte produit un nouveau résultat.
 */
class AssistantIaAssistance
{
    private const MESSAGES_MAX = 20;

    private const CARACTERES_PAR_MESSAGE = 3000;

    private const DUREE_CACHE = 7 * 24 * 3600;

    private const CONSIGNE_COMMUNE = 'Tu assistes l\'équipe support d\'ORéOF, application de gestion de l\'offre de formation '
        . '(maquettes, parcours, fiches matières, MCCC, campagnes de saisie, workflows de validation) de l\'Université de Reims '
        . 'Champagne-Ardenne. Tu reçois l\'échange complet d\'une demande d\'assistance : messages des usagers, réponses et notes '
        . 'internes de l\'équipe, précédé du contexte ORéOF de l\'usager (profils, périodes de saisie ouvertes ou fermées, '
        . 'demandes de modification) : appuie-toi dessus. N\'invente jamais de fonctionnalité, de procédure, de date ni de lien. Réponds en français, '
        . 'en texte brut sans Markdown.';

    private const CONSIGNE_RESUME = self::CONSIGNE_COMMUNE . ' Résume la demande pour un membre de l\'équipe qui la découvre, '
        . 'en trois lignes au plus : « Problème : … », « Déjà fait : … » (omettre si rien), « Reste à faire : … ».';

    private const CONSIGNE_BROUILLON = self::CONSIGNE_COMMUNE . ' Rédige un brouillon de réponse de l\'équipe au dernier message '
        . 'de l\'usager, en tenant compte des notes internes. Vouvoiement, ton cordial, concis. Si une information manque pour '
        . 'répondre, écris [à compléter] à sa place. Donne uniquement le corps du mail, de « Bonjour, » à « Cordialement, » '
        . 'suivi de « L\'équipe ORéOF ».';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly FicheMatiereAiSynthesizer $configurationIa,
        private readonly SeparateurCitation $separateur,
        private readonly ContexteUsagerAssistance $contexteUsager,
    ) {
    }

    public function estConfigure(): bool
    {
        return $this->configurationIa->isAiConfigured();
    }

    public function resumer(DemandeAssistance $demande, bool $forcer = false): string
    {
        return $this->generer('resume', self::CONSIGNE_RESUME, 400, $demande, $forcer);
    }

    public function suggererReponse(DemandeAssistance $demande, bool $forcer = false): string
    {
        return $this->generer('brouillon', self::CONSIGNE_BROUILLON, 800, $demande, $forcer);
    }

    /**
     * Échange tel qu'envoyé au modèle : en-tête de la demande puis messages par date (les derniers si trop nombreux).
     */
    public function conversation(DemandeAssistance $demande): string
    {
        $lignes = [
            'Sujet : ' . $demande->getSujet(),
            'Statut : ' . $demande->getStatut()->getLibelle(),
            'Responsable dans l\'équipe : ' . ($demande->getResponsable()?->getDisplay() ?? 'aucun'),
            'Usager : ' . ($demande->getDemandeurNom() ?? 'non renseigné'),
        ];
        if ($demande->getFormation() !== null) {
            $lignes[] = 'Formation : ' . $demande->getFormation()->getDisplay()
                . ($demande->getParcours() !== null ? ' / ' . $demande->getParcours()->getDisplay() : '');
        }
        $lignes[] = '';
        $lignes[] = $this->contexteUsager->enTexte($demande);

        $messages = $demande->getMessages()->toArray();
        usort($messages, static fn (MessageAssistance $a, MessageAssistance $b) => $a->getDateEnvoi() <=> $b->getDateEnvoi());
        foreach (array_slice($messages, -self::MESSAGES_MAX) as $message) {
            $lignes[] = '';
            $lignes[] = sprintf('[%s] %s :', $message->getDateEnvoi()->format('d/m/Y H:i'), $this->auteur($message));
            $lignes[] = $this->contenu($message);
        }

        return implode("\n", $lignes);
    }

    /**
     * Texte brut → HTML pour l'éditeur Trix (un bloc par ligne, ligne vide entre paragraphes).
     */
    public static function enHtml(string $texte): string
    {
        $paragraphes = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $texte))) ?: [];
        $blocs = array_map(
            static fn (string $p) => '<div>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES), false) . '</div>',
            $paragraphes,
        );

        return str_replace("<br>\n", '<br>', implode('<div><br></div>', $blocs));
    }

    private function generer(string $type, string $consigne, int $jetonsMax, DemandeAssistance $demande, bool $forcer): string
    {
        if (!$this->estConfigure()) {
            throw new RuntimeException('Aucune IA configurée (Établissement > APIs tierces ou variables AI_*).');
        }

        $config = $this->configurationIa->resolveConfig();
        $conversation = $this->conversation($demande);
        $cle = sprintf('assistance_ia_%s_%s', $type, hash('sha256', $config['model'] . "\n" . $conversation));
        if ($forcer) {
            $this->cache->delete($cle);
        }

        return $this->cache->get($cle, function (ItemInterface $item) use ($config, $consigne, $jetonsMax, $conversation): string {
            $item->expiresAfter(self::DUREE_CACHE);

            return $this->appeler($config, $consigne, $jetonsMax, $conversation);
        });
    }

    /**
     * @param array{url: ?string, apiKey: ?string, model: ?string, source: string} $config
     */
    private function appeler(array $config, string $consigne, int $jetonsMax, string $conversation): string
    {
        $entetes = ['Accept' => 'application/json'];
        if ($config['apiKey'] !== null) {
            $entetes['Authorization'] = 'Bearer ' . $config['apiKey'];
        }

        $reponse = $this->httpClient->request('POST', (string) $config['url'], [
            'headers' => $entetes,
            'timeout' => 60.0,
            'json' => [
                'model' => $config['model'] ?: 'default',
                'temperature' => 0.2,
                'max_tokens' => $jetonsMax,
                'messages' => [
                    ['role' => 'system', 'content' => $consigne],
                    ['role' => 'user', 'content' => $conversation],
                ],
            ],
        ]);

        $texte = trim((string) ($reponse->toArray()['choices'][0]['message']['content'] ?? ''));
        if ($texte === '') {
            throw new RuntimeException('Réponse vide du modèle IA.');
        }

        return $texte;
    }

    private function auteur(MessageAssistance $message): string
    {
        $nom = $message->getExpediteurNom() ?: ($message->getAuteur()?->getDisplay() ?? 'inconnu');

        return match (true) {
            $message->getSens() === SensMessageAssistanceEnum::NOTE => sprintf('Note interne de l\'équipe (%s)', $nom),
            $message->isAutomatique() => 'Message automatique',
            $message->getSens() === SensMessageAssistanceEnum::SORTANT => sprintf('Équipe ORéOF (%s)', $nom),
            default => sprintf('Usager (%s)', $nom),
        };
    }

    private function contenu(MessageAssistance $message): string
    {
        $texte = $message->getCorpsTexte();
        if (trim($texte) === '' && $message->getCorpsHtml() !== null) {
            $html = (string) preg_replace('/<(br|\/p|\/div|\/li)\b[^>]*>/i', "\n", $message->getCorpsHtml());
            $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        [$visible] = $this->separateur->separerTexte(trim($texte));
        // Les adresses mail (signatures, citations) ne sont pas utiles au modèle.
        $visible = (string) preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[adresse]', $visible);
        $visible = trim((string) preg_replace("/\n{3,}/", "\n\n", $visible));

        return mb_strlen($visible) > self::CARACTERES_PAR_MESSAGE
            ? mb_substr($visible, 0, self::CARACTERES_PAR_MESSAGE) . '…'
            : $visible;
    }
}
