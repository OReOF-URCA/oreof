<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Form\Workflow\ArgumentaireDateType;
use App\Form\Workflow\ArgumentaireType;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class DpeWorkflowConfigurationTest extends KernelTestCase
{
    public function testEveryTransitionUsesTheDpeOperationVoter(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.dpeParcours');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        $factory = new WorkflowOperationFactory();
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $operation = $factory->create($workflow, $transition->getName());

            self::assertSame(
                'DPE_WORKFLOW_TRANSITION',
                $operation->metadata['authorization']['attribute'] ?? null,
                sprintf('La transition %s ne déclare pas le voter DPE.', $transition->getName()),
            );
            self::assertSame('operation', $operation->metadata['authorization']['subject'] ?? null);
        }
    }

    public function testEveryReserveTransitionCollectsAndAliasesAnArgumentaire(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.dpeParcours');

        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);
            if ('reserver' !== ($metadata['type'] ?? null)) {
                continue;
            }

            self::assertContains(
                $metadata['form']['type'] ?? null,
                [ArgumentaireType::class, ArgumentaireDateType::class],
                sprintf('La transition %s ne collecte pas son argumentaire.', $transition->getName()),
            );
            self::assertSame(
                'motif',
                $metadata['context']['aliases']['argumentaire'] ?? null,
                sprintf('La transition %s ne normalise pas son argumentaire en motif.', $transition->getName()),
            );
        }
    }

    public function testNonClassiqueParcoursWorkflowCycle(): void
    {
        self::bootKernel();
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('workflow.dpeParcours');

        $formationNonClassique = new \App\Entity\Formation();
        $formationNonClassique->setClassique(false);

        $parcoursNonClassique = new \App\Entity\Parcours();
        $parcoursNonClassique->setLibelle('Parcours DU');
        $parcoursNonClassique->setFormation($formationNonClassique);

        $dpeNonClassique = new \App\Entity\DpeParcours();
        $dpeNonClassique->setFormation($formationNonClassique);
        $dpeNonClassique->setParcours($parcoursNonClassique);

        // 1. État en_cours_redaction : seulement transmettre_ses_etablissement (pas valider_parcours)
        $dpeNonClassique->setEtatValidation(['en_cours_redaction' => 1]);
        self::assertTrue($workflow->can($dpeNonClassique, 'transmettre_ses_etablissement'));
        self::assertFalse($workflow->can($dpeNonClassique, 'valider_parcours'));

        // 2. État soumis_central : valider_ses_etablissement & reserver_ses_etablissement
        $dpeNonClassique->setEtatValidation(['soumis_central' => 1]);
        self::assertTrue($workflow->can($dpeNonClassique, 'valider_ses_etablissement'));
        self::assertTrue($workflow->can($dpeNonClassique, 'reserver_ses_etablissement'));
        self::assertFalse($workflow->can($dpeNonClassique, 'valider_central'));
        self::assertFalse($workflow->can($dpeNonClassique, 'valider_publication_directe'));
        self::assertFalse($workflow->can($dpeNonClassique, 'reserver_central'));

        // 3. État valide_cfvu (relecture avant publication) : valider_publication & reouvrir_relecture_etablissement
        $dpeNonClassique->setEtatValidation(['valide_cfvu' => 1]);
        self::assertTrue($workflow->can($dpeNonClassique, 'valider_publication'));
        self::assertTrue($workflow->can($dpeNonClassique, 'reouvrir_relecture_etablissement'));

        // 4. État valide_a_publier : publier & reouvrir_avant_publie
        $dpeNonClassique->setEtatValidation(['valide_a_publier' => 1]);
        self::assertTrue($workflow->can($dpeNonClassique, 'publier'));
        self::assertTrue($workflow->can($dpeNonClassique, 'reouvrir_avant_publie'));

        // 5. État publie : reouvrir_etablissement autorisé, mais pas les réouvertures classiques
        $dpeNonClassique->setEtatValidation(['publie' => 1]);
        self::assertTrue($workflow->can($dpeNonClassique, 'reouvrir_etablissement'));
        self::assertFalse($workflow->can($dpeNonClassique, 'reouvrir_sans_cfvu'));
        self::assertFalse($workflow->can($dpeNonClassique, 'reouvrir_mccc'));

        // Parcours classique inverse
        $formationClassique = new \App\Entity\Formation();
        $formationClassique->setClassique(true);

        $parcoursClassique = new \App\Entity\Parcours();
        $parcoursClassique->setLibelle('Parcours Master');
        $parcoursClassique->setFormation($formationClassique);

        $dpeClassique = new \App\Entity\DpeParcours();
        $dpeClassique->setFormation($formationClassique);
        $dpeClassique->setParcours($parcoursClassique);

        // en_cours_redaction classique
        $dpeClassique->setEtatValidation(['en_cours_redaction' => 1]);
        self::assertTrue($workflow->can($dpeClassique, 'valider_parcours'));
        self::assertFalse($workflow->can($dpeClassique, 'transmettre_ses_etablissement'));

        // soumis_central classique
        $dpeClassique->setEtatValidation(['soumis_central' => 1]);
        self::assertTrue($workflow->can($dpeClassique, 'valider_central'));
        self::assertTrue($workflow->can($dpeClassique, 'reserver_central'));
        self::assertFalse($workflow->can($dpeClassique, 'valider_ses_etablissement'));
        self::assertFalse($workflow->can($dpeClassique, 'reserver_ses_etablissement'));

        // publie classique
        $dpeClassique->setEtatValidation(['publie' => 1]);
        self::assertFalse($workflow->can($dpeClassique, 'reouvrir_etablissement'));
        self::assertTrue($workflow->can($dpeClassique, 'reouvrir_sans_cfvu'));
        self::assertTrue($workflow->can($dpeClassique, 'reouvrir_mccc'));
    }
}
