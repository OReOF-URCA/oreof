<?php

namespace App\Service\Assistance\Source;

use Closure;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * Lit la boîte d'assistance en IMAP, sans jamais la modifier.
 *
 * - En-têtes d'abord : le corps et les pièces jointes ne sont téléchargés que pour les mails encore inconnus.
 * - Lecture « peek » avec récupération des flags : l'état lu/non lu des mails n'est pas modifié.
 * - Un mail illisible (date invalide, contenu introuvable…) est journalisé et ignoré, la relève continue.
 *
 * Configuration : variables d'environnement ASSISTANCE_IMAP_*.
 */
class ImapSource implements SourceMailInterface
{
    public function __construct(
        private readonly MailEntrantFactory $factory,
        private readonly LoggerInterface $logger,
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly string $folder,
    ) {
    }

    public function estConfiguree(): bool
    {
        return $this->host !== '' && $this->username !== '';
    }

    public function lire(DateTimeImmutable $depuis, ?Closure $estConnu = null): iterable
    {
        if (!$this->estConfiguree()) {
            throw new RuntimeException('Boîte IMAP non configurée (variables ASSISTANCE_IMAP_*).');
        }

        $client = $this->connecter();

        try {
            $dossier = $client->getFolderByPath($this->folder);
            if ($dossier === null) {
                throw new RuntimeException(sprintf('Dossier IMAP introuvable : %s', $this->folder));
            }
            $messages = $dossier->query()
                ->whereSince($depuis->format('d.m.Y'))
                ->leaveUnread()
                ->setFetchFlags(true)
                ->setFetchBody(false)
                ->softFail()
                ->get();

            foreach ($messages as $message) {
                try {
                    $messageId = $this->factory->messageId($message);
                    if ($messageId !== null && $estConnu !== null && $estConnu($messageId)) {
                        continue;
                    }
                    $message->parseBody();
                    $mail = $this->factory->depuisMessage($message);
                } catch (Throwable $e) {
                    $this->logger->warning('Assistance : mail IMAP illisible, ignoré', ['uid' => $message->getUid(), 'exception' => $e]);
                    continue;
                }
                yield $mail;
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Lecture IMAP impossible : ' . $e->getMessage(), 0, $e);
        } finally {
            $client->disconnect();
        }
    }

    private function connecter(): Client
    {
        try {
            $client = (new ClientManager([
                'options' => [
                    'soft_fail' => true,
                    // date d'en-tête illisible : on garde le mail plutôt que d'interrompre la relève
                    'fallback_date' => 'now',
                ],
            ]))->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption === '' ? false : $this->encryption,
                'validate_cert' => true,
                'username' => $this->username,
                'password' => $this->password,
                'protocol' => 'imap',
            ]);
            $client->connect();
        } catch (Throwable $e) {
            throw new RuntimeException('Connexion IMAP impossible : ' . $e->getMessage(), 0, $e);
        }

        return $client;
    }
}
