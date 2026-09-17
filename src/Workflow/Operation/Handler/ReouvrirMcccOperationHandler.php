<?php

declare(strict_types=1);

namespace App\Workflow\Operation\Handler;

use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\User;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use Dannebicque\WorkflowOperationsBundle\Contract\OperationHandlerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * First ORéOF handler migrated to the generic operation contract.
 *
 * It performs domain mutations only. Applying the Symfony transition is the
 * executor's responsibility and flushing is owned by the caller's transaction.
 */
final readonly class ReouvrirMcccOperationHandler implements OperationHandlerInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(object $subject, WorkflowOperation $operation): bool
    {
        return $subject instanceof DpeParcours
            && 'dpeParcours' === $operation->workflowName
            && 'reouvrir_mccc' === $operation->transitionName;
    }

    public function handle(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): void {
        if (!$subject instanceof DpeParcours) {
            throw new \InvalidArgumentException(sprintf('Expected %s, got %s.', DpeParcours::class, get_debug_type($subject)));
        }

        if (!$context->actor instanceof User) {
            throw new \DomainException('Un utilisateur ORéOF est requis pour réouvrir les MCCC.');
        }

        $argumentaire = trim((string) ($context->input['argumentaire'] ?? ''));
        if ('' === $argumentaire) {
            throw new \DomainException('Argumentaire obligatoire.');
        }

        $parcours = $subject->getParcours();
        if (null === $parcours) {
            throw new \DomainException('Le parcours associé au DPE est introuvable.');
        }

        $demande = new DpeDemande();
        $demande->setFormation($parcours->getFormation());
        $demande->setCampagneCollecte($subject->getCampagneCollecte());
        $demande->setParcours($parcours);
        $demande->setAuteur($context->actor);
        $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
        $demande->setNiveauDemande('P');
        $demande->setArgumentaireDemande($argumentaire);
        $demande->setNiveauModification(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);

        $subject->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
        $this->entityManager->persist($demande);
    }
}
