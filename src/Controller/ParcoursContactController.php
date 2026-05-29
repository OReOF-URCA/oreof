<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Parcours;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use App\Utils\JsonRequest;
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
        Parcours $parcours
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

            return $this->json(true);
        }

        //todo: modal à adapter à V2
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
        ContactRepository $contactRepository
    ): Response
    {
        $form = $this->createForm(ContactType::class, $contact, [
            'action' => $this->generateUrl('app_parcours_contact_edit', ['id' => $contact->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->json(true);
        }

        return $this->render('parcours_contact/new.html.twig', [
            'contact' => $contact,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_parcours_contact_duplicate', methods: ['GET'])]
    public function duplicate(
        EntityManagerInterface $entityManager,
        Contact $contact
    ): Response {
        //cloner un objet contact qui dispose d'une relation (adresse), duplique aussi la relation
        //il faut donc cloner l'adresse et la setter sur le nouveau contact
        $contactNew = clone $contact;
        $adresseNew = clone $contact->getAdresse();

        $contactNew->setDenomination($contact->getDenomination() . ' - Copie');
        $contactNew->setAdresse($adresseNew);
        $entityManager->persist($contactNew);
        $entityManager->flush();

        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_parcours_contact_delete', methods: ['DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Request $request,
        Contact $contact,
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $contact->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
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

            return $this->json(true);
        }

        return $this->json(false);
    }
}
