<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursSesController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 06/02/2026 09:18
 */

namespace App\Controller\Parcours;

use App\Classes\ValidationProcess;
use App\Controller\BaseController;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\TypeModificationDpeEnum;
use App\Events\HistoriqueParcoursEvent;
use App\Form\Type\YesNoType;
use App\Repository\FormationRepository;
use App\Service\Parcours\ParcoursMoveService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/parcours/v2/ses', name: 'parcours_ses_')]
class ParcoursSesController extends BaseController
{
    public function __construct(
        private TurboStreamResponseFactory $turboStream,
        private ParcoursMoveService $parcoursMoveService,
        private FormationRepository $formationRepository,
        private EntityManagerInterface   $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    )
    {
    }

    #[Route('/changer-etat/{dpeParcours}', name: 'changer_etat', methods: ['GET', 'POST'])]
    public function changerEtat(
        ValidationProcess $validationProcess,
        Request           $request,
        DpeParcours       $dpeParcours
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $currentPlace = array_key_first($dpeParcours->getEtatValidation()) ?? '';
        $process = $validationProcess->getProcessAllForListe();

        $form = $this->createFormBuilder([
            'etat' => $currentPlace,
            'etatReconduction' => $dpeParcours->getEtatReconduction(),
            'inHistorique' => false,
        ], [
            'action' => $this->generateUrl('parcours_ses_changer_etat', ['dpeParcours' => $dpeParcours->getId()]),
            'method' => 'POST',
            'attr' => ['id' => 'modal_form'],
            'translation_domain' => 'form'
        ])
            ->add('etat', ChoiceType::class, [
                'label' => 'Nouvel état du workflow (DPE)',
                'choices' => $process,
                'choice_translation_domain' => 'process',
                'autocomplete' => true,
            ])
            ->add('etatReconduction', EnumType::class, [
                'class' => TypeModificationDpeEnum::class,
                'choice_label' => fn(TypeModificationDpeEnum $c) => $c->getLibelle(),
                'label' => 'État d\'ouverture',
                'placeholder' => 'Choisir un état d\'ouverture...',
                'required' => false,
                'autocomplete' => true,
            ])
            ->add('inHistorique', YesNoType::class, [
                'label' => 'Ajouter une entrée dans l\'historique',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $newEtat = $data['etat'];
            $newEtatReconduction = $data['etatReconduction'] ?? null;
            $inHistorique = (bool)($data['inHistorique'] ?? false);

            $dpeParcours->setEtatValidation([$newEtat => 1]);
            $dpeParcours->setEtatReconduction($newEtatReconduction);

            if ($inHistorique) {
                $user = $this->getUser();
                if ($user instanceof User) {
                    $event = new HistoriqueParcoursEvent(
                        $dpeParcours->getParcours(),
                        $user,
                        $newEtat,
                        'force_admin',
                        $request
                    );
                    $this->eventDispatcher->dispatch($event, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                }
            }

            $this->entityManager->flush();

            $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
                || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
                || $request->isXmlHttpRequest();

            if ($isTurbo) {
                return $this->turboStream->stream('parcours_v2/ses/turbo/changer_etat_success.stream.html.twig', [
                    'dpeParcours' => $dpeParcours,
                    'parcours' => $dpeParcours->getParcours(),
                    'toastMessage' => 'État du parcours modifié avec succès.',
                ]);
            }

            $this->addFlash('success', 'État du parcours modifié avec succès.');
            return $this->redirectToRoute('parcours_v2_voir', ['parcours' => $dpeParcours->getParcours()?->getId()]);
        }

        return $this->turboStream->streamOpenModalFromTemplates(
            'Modifier l\'état du parcours',
            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
            'parcours_v2/ses/_changer_etat.html.twig',
            [
                'parcours' => $dpeParcours->getParcours(),
                'dpeParcours' => $dpeParcours,
                'form' => $form->createView()
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Changer l\'état du parcours',
            ]
        );
    }

    #[Route('/changer-ouverture/{dpeParcours}', name: 'changer_ouverture', methods: ['GET', 'POST'])]
    public function changerOuverture(
        Request     $request,
        DpeParcours $dpeParcours
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createFormBuilder([
            'etat' => $dpeParcours->getEtatReconduction(),
        ], [
            'action' => $this->generateUrl('parcours_ses_changer_ouverture', ['dpeParcours' => $dpeParcours->getId()]),
            'method' => 'POST',
            'attr' => ['id' => 'modal_form'],
            'translation_domain' => 'form'
        ])
            ->add('etat', EnumType::class, [
                'class' => TypeModificationDpeEnum::class,
                'choice_label' => fn(TypeModificationDpeEnum $c) => $c->getLibelle(),
                'label' => 'État d\'ouverture',
                'placeholder' => 'Choisir un état d\'ouverture...',
                'required' => false,
                'autocomplete' => true,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $dpeParcours->setEtatReconduction($data['etat']);
            $this->entityManager->flush();

            $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
                || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
                || $request->isXmlHttpRequest();

            if ($isTurbo) {
                return $this->turboStream->stream('parcours_v2/ses/turbo/changer_etat_success.stream.html.twig', [
                    'dpeParcours' => $dpeParcours,
                    'parcours' => $dpeParcours->getParcours(),
                    'toastMessage' => 'État d\'ouverture modifié avec succès.',
                ]);
            }

            $this->addFlash('success', 'État d\'ouverture modifié avec succès.');
            return $this->redirectToRoute('parcours_v2_voir', ['parcours' => $dpeParcours->getParcours()?->getId()]);
        }

        return $this->turboStream->streamOpenModalFromTemplates(
            'Modifier l\'état d\'ouverture du parcours',
            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
            'parcours_v2/ses/_changer_ouverture.html.twig',
            [
                'parcours' => $dpeParcours->getParcours(),
                'dpeParcours' => $dpeParcours,
                'form' => $form->createView()
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Changer l\'état du parcours',
            ]
        );
    }

    #[Route('/commentaires/{dpeParcours}', name: 'commentaires')]
    public function commentaires(
        Request     $request,
        DpeParcours $dpeParcours
    ): Response
    {
        return $this->turboStream->streamOpenModalFromTemplates(
            'Commentaires sur le parcours',
            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
            'parcours_v2/ses/_commentaires.html.twig',
            [
                'parcours' => $dpeParcours->getParcours(),
                'dpeParcours' => $dpeParcours,
            ],
            '_ui/_footer_cancel.html.twig',
            [
                'cancelLabel' => 'Fermer',
            ]
        );
    }

    #[Route('/deplacer/{parcours}', name: 'deplacer', methods: ['GET'])]
    public function deplacer(
        Parcours $parcours,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $analysis = $this->parcoursMoveService->analyze($parcours);
        $eligibleFormations = $this->parcoursMoveService->getEligibleFormations($parcours);

        $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
            || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
            || $request->isXmlHttpRequest();

        if ($isTurbo) {
            return $this->turboStream->streamOpenModalFromTemplates(
                'Déplacer le parcours de formation',
                'Parcours : ' . $parcours->getDisplay(),
                'parcours_v2/ses/_deplacer.html.twig',
                [
                    'parcours' => $parcours,
                    'formation' => $parcours->getFormation(),
                    'analysis' => $analysis,
                    'eligibleFormations' => $eligibleFormations,
                ],
                'parcours_v2/ses/_modal_deplacer_footer.html.twig',
                [
                    'parcours' => $parcours,
                ]
            );
        }

        return $this->render('parcours_v2/ses/deplacer.html.twig', [
            'parcours' => $parcours,
            'formation' => $parcours->getFormation(),
            'analysis' => $analysis,
            'eligibleFormations' => $eligibleFormations,
        ]);
    }

    #[Route('/deplacer/{parcours}', name: 'deplacer_post', methods: ['POST'])]
    public function deplacerPost(
        Parcours $parcours,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
            || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
            || $request->isXmlHttpRequest();

        $csrfToken = (string)$request->request->get('_token', $request->request->get('csrf_token', ''));
        if (!$this->isCsrfTokenValid('parcours_move_' . $parcours->getId(), $csrfToken)) {
            if ($isTurbo) {
                return $this->turboStream->streamToastError('Token CSRF invalide ou expiré.');
            }
            $this->addFlash('danger', 'Token CSRF invalide ou expiré.');
            return $this->redirectToRoute('parcours_ses_deplacer', ['parcours' => $parcours->getId()]);
        }

        $confirmationText = trim((string)$request->request->get('confirmation_text', ''));
        $expectedText = trim($parcours->getLibelle() ?? '');

        if (mb_strtoupper($confirmationText) !== mb_strtoupper($expectedText) && mb_strtoupper($confirmationText) !== 'DEPLACER') {
            $msg = sprintf('Le texte saisi (« %s ») ne correspond pas au libellé du parcours (« %s ») ou à « DEPLACER ». Le déplacement a été annulé.', $confirmationText, $expectedText);
            if ($isTurbo) {
                return $this->turboStream->streamToastError($msg);
            }
            $this->addFlash('danger', $msg);
            return $this->redirectToRoute('parcours_ses_deplacer', ['parcours' => $parcours->getId()]);
        }

        $targetFormationId = (int)$request->request->get('target_formation_id', 0);
        $targetFormation = $targetFormationId > 0 ? $this->formationRepository->find($targetFormationId) : null;

        if ($targetFormation === null) {
            $msg = 'Veuillez sélectionner une formation de destination valide.';
            if ($isTurbo) {
                return $this->turboStream->streamToastError($msg);
            }
            $this->addFlash('danger', $msg);
            return $this->redirectToRoute('parcours_ses_deplacer', ['parcours' => $parcours->getId()]);
        }

        try {
            $this->parcoursMoveService->move($parcours, $targetFormation);
        } catch (\LogicException|\InvalidArgumentException $e) {
            if ($isTurbo) {
                return $this->turboStream->streamToastError($e->getMessage());
            }
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('parcours_ses_deplacer', ['parcours' => $parcours->getId()]);
        }

        $flashMsg = sprintf('Le parcours « %s » a été déplacé avec succès vers la formation « %s ».', $parcours->getLibelle(), $targetFormation->getDisplay());
        $this->addFlash('success', $flashMsg);

        $redirectUrl = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);

        if ($isTurbo) {
            return $this->turboStream->stream('parcours_v2/ses/turbo/move_success.stream.html.twig', [
                'redirectUrl' => $redirectUrl,
                'toastMessage' => $flashMsg,
            ]);
        }

        return $this->redirect($redirectUrl);
    }
}

