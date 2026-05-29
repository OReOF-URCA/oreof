<?php

namespace App\MessageHandler;

use App\Entity\GenerationJob;
use App\Message\ProcessGenerationJobMessage;
use App\Message\RequestGenerationJobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

//#[AsMessageHandler]
class RequestGenerationJobHandler
{
    public function __construct(
        private MessageBusInterface    $bus,
        private EntityManagerInterface $em)
    {
    }

    public function __invoke(RequestGenerationJobMessage $message): void
    {
        $job = new GenerationJob();
        $job->setType($message->getType());
        $job->setStatus('pending');
        $job->setParameters($message->getParameters());
        $job->setCreatedAt(new \DateTime());
        $job->setUpdatedAt(new \DateTime());

        // Associe l'utilisateur
        $user = $this->em->getReference('App\Entity\User', $message->getUserId());
        $job->setUser($user);

        $this->em->persist($job);
        $this->em->flush();

        $this->bus->dispatch(new ProcessGenerationJobMessage(
            $job->getId()
        ));
    }
}
