<?php

namespace App\Service\Assistance\Source;

use DateTimeImmutable;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

/**
 * Lit la boîte d'assistance en IMAP, sans jamais la modifier (messages laissés non lus).
 * Configuration : variables d'environnement ASSISTANCE_IMAP_*.
 */
class ImapSource implements SourceMailInterface
{
    public function __construct(
        private readonly MailEntrantFactory $factory,
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

    public function lire(DateTimeImmutable $depuis): iterable
    {
        if (!$this->estConfiguree()) {
            throw new RuntimeException('Boîte IMAP non configurée (variables ASSISTANCE_IMAP_*).');
        }

        try {
            $client = (new ClientManager())->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption === '' ? false : $this->encryption,
                'validate_cert' => true,
                'username' => $this->username,
                'password' => $this->password,
                'protocol' => 'imap',
            ]);
            $client->connect();
            $dossier = $client->getFolderByPath($this->folder);
            if ($dossier === null) {
                throw new RuntimeException(sprintf('Dossier IMAP introuvable : %s', $this->folder));
            }
            $messages = $dossier->query()
                ->whereSince($depuis->format('d.m.Y'))
                ->leaveUnread()
                ->setFetchBody(true)
                ->get();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Connexion IMAP impossible : ' . $e->getMessage(), 0, $e);
        }

        try {
            foreach ($messages as $message) {
                yield $this->factory->depuisMessage($message);
            }
        } finally {
            $client->disconnect();
        }
    }
}
