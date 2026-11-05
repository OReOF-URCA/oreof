<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Parcours;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParcoursContactController extends BaseController
{
    #[Route('/parcours/contacts/{parcours}', name: 'app_parcours_contacts')]
    public function liste(Parcours $parcours): Response
    {
        return $this->render('parcours_contact/_liste.html.twig', [
            'parcours' => $parcours,
            'contacts' => $parcours->getContacts(),
        ]);
    }

    #[Route('/parcours/contacts/{parcours}/new', name: 'app_parcours_contacts_add', methods: ['GET', 'POST'])]
    public function new(
        EntityManagerInterface $entityManager,
        Request $request,
        Parcours $parcours,
        TurboStreamResponseFactory $turboStreamResponseFactory
    ): Response
    {
        $contact = new Contact();
        $contact->setParcours($parcours);
        $contact->setDenomination('Secrétariat pédagogique');
        $parcours->addContact($contact);
        if ($parcours->getComposanteInscription() !== null && $parcours->getComposanteInscription()->getAdresse() !== null) {
            $adresseCompo = $parcours->getComposanteInscription()->getAdresse();
            $adresse = clone $adresseCompo;
            $contact->setAdresse($adresse);
        } else {
            $adresseCompo = $parcours->getFormation()?->getComposantePorteuse()?->getAdresse();
            if ($adresseCompo !== null) {
                $adresse = clone $adresseCompo;
                $contact->setAdresse($adresse);
            }
        }
        $form = $this->createForm(ContactType::class, $contact, [
            'action' => $this->generateUrl('app_parcours_contacts_add', ['parcours' => $parcours->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($contact);
            $entityManager->flush();

            if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                return $turboStreamResponseFactory->appendStreams(
                    $turboStreamResponseFactory->streamToastSuccess('Contact ajouté avec succès', true)->getContent(),
                    $this->renderView('parcours_contact/turbo/update_contacts_list.stream.html.twig', [
                        'parcours' => $parcours,
                        'contacts' => $parcours->getContacts(),
                    ])
                );
            }

            return $this->json(true);
        }

        if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html') || $request->isXmlHttpRequest()) {
            return $turboStreamResponseFactory->streamOpenModalFromTemplates(
                'Ajouter un contact',
                'Coordonnées du secrétariat ou contact pour la formation',
                '_ui/_modal_new_generic.html.twig',
                [
                    'contact' => $contact,
                    'form' => $form->createView(),
                ],
                '_ui/_footer_submit_cancel.html.twig'
            );
        }

        return $this->render('parcours_contact/new.html.twig', [
            'contact' => $contact,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_parcours_contact_edit', methods: ['GET', 'POST'])]
    public function edit(
        EntityManagerInterface $entityManager,
        Request $request,
        Contact $contact,
        ContactRepository $contactRepository,
        TurboStreamResponseFactory $turboStreamResponseFactory
    ): Response
    {
        $parcours = $contact->getParcours();
        $form = $this->createForm(ContactType::class, $contact, [
            'action' => $this->generateUrl('app_parcours_contact_edit', ['id' => $contact->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                return $turboStreamResponseFactory->appendStreams(
                    $turboStreamResponseFactory->streamToastSuccess('Contact modifié avec succès', true)->getContent(),
                    $this->renderView('parcours_contact/turbo/update_contacts_list.stream.html.twig', [
                        'parcours' => $parcours,
                        'contacts' => $parcours?->getContacts() ?? [],
                    ])
                );
            }

            return $this->json(true);
        }

        if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html') || $request->isXmlHttpRequest()) {
            return $turboStreamResponseFactory->streamOpenModalFromTemplates(
                'Modifier le contact',
                $contact->getDenomination(),
                '_ui/_modal_new_generic.html.twig',
                [
                    'contact' => $contact,
                    'form' => $form->createView(),
                ],
                '_ui/_footer_submit_cancel.html.twig'
            );
        }

        return $this->render('parcours_contact/new.html.twig', [
            'contact' => $contact,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_parcours_contact_duplicate', methods: ['GET', 'POST'])]
    public function duplicate(
        EntityManagerInterface $entityManager,
        Request $request,
        Contact $contact,
        TurboStreamResponseFactory $turboStreamResponseFactory
    ): Response {
        $parcours = $contact->getParcours();
        $contactNew = clone $contact;
        $adresse = $contact->getAdresse();
        $adresseNew = $adresse !== null ? clone $adresse : null;

        $contactNew->setDenomination($contact->getDenomination() . ' - Copie');
        if ($adresseNew !== null) {
            $contactNew->setAdresse($adresseNew);
        }
        $entityManager->persist($contactNew);
        $entityManager->flush();

        if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            return $turboStreamResponseFactory->appendStreams(
                $turboStreamResponseFactory->streamToastSuccess('Contact dupliqué avec succès')->getContent(),
                $this->renderView('parcours_contact/turbo/update_contacts_list.stream.html.twig', [
                    'parcours' => $parcours,
                    'contacts' => $parcours?->getContacts() ?? [],
                ])
            );
        }

        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_parcours_contact_delete', methods: ['POST', 'DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Request $request,
        Contact $contact,
        TurboStreamResponseFactory $turboStreamResponseFactory
    ): Response {
        $parcours = $contact->getParcours();
        $token = $request->request->get('_token')
            ?? $request->request->get('csrf')
            ?? JsonRequest::getValueFromRequest($request, 'csrf');

        if ($this->isCsrfTokenValid(
            'delete' . $contact->getId(),
            (string)$token
        )) {
            $adresse = $contact->getAdresse();
            $adresse?->setAdresseOrigineCopie(null);
            $contact->setParcours(null);
            $entityManager->remove($contact);
            $entityManager->flush();

            if ($adresse !== null) {
                $entityManager->remove($adresse);
                $entityManager->flush();
            }

            if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
                return $turboStreamResponseFactory->appendStreams(
                    $turboStreamResponseFactory->streamToastSuccess('Contact supprimé avec succès')->getContent(),
                    $this->renderView('parcours_contact/turbo/update_contacts_list.stream.html.twig', [
                        'parcours' => $parcours,
                        'contacts' => $parcours?->getContacts() ?? [],
                    ])
                );
            }

            return $this->json(true);
        }

        if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            return $turboStreamResponseFactory->streamToastError('Jeton CSRF invalide');
        }

        return $this->json(false);
    }
}
