<?php

declare(strict_types=1);

namespace App\Tests\Integration\Workflow;

use App\Tests\Support\TestCase;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Fixtures\EntityFixturesTrait;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * TEST D'INTÉGRATION - Transitions de Workflow Symfony
 *
 * Cas d'usage : Tester les états, transitions, et callbacks du workflow
 * Configuration : config/packages/workflow.yaml
 */
class WorkflowTransitionExampleTest extends TestCase
{
    use DatabaseTrait;
    use EntityFixturesTrait;

    private WorkflowInterface $workflow;

    /**
     * ✅ Scénario : Transition valide du workflow fonctionne
     */
    public function testValidWorkflowTransition(): void
    {
        $this->markTestIncomplete('À impléter avec le vrai workflow');

        // Arrange
        // $parcours = $this->createMinimalParcours();
        // $parcours->setState('draft');
        // $this->persist($parcours);

        // Act & Assert
        // $this->assertTrue($this->workflow->can($parcours, 'submit_for_review'));
        // $this->workflow->apply($parcours, 'submit_for_review');
        // $this->assertEquals('review', $parcours->getState());
    }

    /**
     * ❌ Scénario : Transition invalide échoue
     */
    public function testInvalidTransitionThrows(): void
    {
        $this->markTestIncomplete('À impléter');

        // $parcours = $this->createMinimalParcours();
        // $parcours->setState('archived');

        // $this->assertFalse($this->workflow->can($parcours, 'submit_for_review'));
    }

    /**
     * ✅ Scénario : Tous les états possibles sont accessibles
     */
    public function testAllStateTransitions(): void
    {
        $this->markTestIncomplete('À impléter');

        // $parcours = $this->createMinimalParcours();
        // $places = $this->workflow->getDefinition()->getPlaces();

        // foreach ($places as $place) {
        //     $this->assertNotNull($place, "Place {$place} should exist");
        // }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();

        // Récupère le workflow depuis le conteneur
        // $this->workflow = $this->getService('state_machine.parcours'); // À adapter au vrai nom
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }
}
