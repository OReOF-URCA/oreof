<?php

namespace App\Command;

use App\Entity\UserNotificationPreference;
use App\Repository\UserProfilRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-notif',
    description: 'Cette commande active par défaut l\'ensemble des notifications par mails et in app, au niveau global',
)]
class UpdateNotifCommand extends Command
{
    private UserProfilRepository $userProfilRepository;

    public function __construct(
        protected UserRepository         $userRepository,
        protected EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();

        foreach ($users as $user) {
            $notif = new UserNotificationPreference();
            $notif->setUser($user);
            $this->entityManager->persist($notif);
        }

        $this->entityManager->flush();

        $io->success('Préférences générales mises à jour.');

        return Command::SUCCESS;
    }
}
