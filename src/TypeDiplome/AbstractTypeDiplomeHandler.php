<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/TypeDiplome/AbstractTypeDiplomeHandler.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 26/08/2026 13:00
 */

namespace App\TypeDiplome;

use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\UserProfil;
use App\Enums\TypeModificationDpeEnum;
use App\Form\FormationSesType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractTypeDiplomeHandler implements TypeDiplomeHandlerInterface
{
    public function getFormationFormType(): string
    {
        return FormationSesType::class;
    }

    public function getFormationFormTemplate(): string
    {
        return 'formation/_new_form.html.twig';
    }

    public function getFormationFormOptions(array $context): array
    {
        $formation = new Formation($context['campagne']);
        $formation->setTypeDiplome($context['typeDiplome']);

        return [
            'data' => $formation,
            'action' => $context['action'],
        ];
    }

    public function handleFormationSubmission(
        FormInterface $form,
        Request $request,
        array $context
    ): ?Response {
        /** @var Formation $formation */
        $formation = $form->getData();
        $em = $context['entityManager'];
        $mentionRepository = $context['mentionRepository'];
        $profilRepository = $context['profilRepository'];
        $dpeParcoursWorkflow = $context['workflow'];
        $router = $context['router'];

        if (array_key_exists(
                'mention',
                $request->request->all()['formation_ses'] ?? []
            ) && $request->request->all()['formation_ses']['mention'] !== null && $request->request->all()['formation_ses']['mention'] !== 'autre') {
            $mention = $mentionRepository->find($request->request->all()['formation_ses']['mention']);
            $formation->setMentionTexte(null);
            $formation->setMention($mention);
        }

        $formation->addComposantesInscription($formation->getComposantePorteuse());
        $formation->setHasParcours(true);
        $formation->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_PARCOURS);
        $em->persist($formation);

        $parcours = new Parcours($formation);
        $parcours->setLibelle('[A renommer] Parcours par défaut');
        $parcours->setRespParcours($formation->getResponsableMention());
        $em->persist($parcours);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setFormation($formation);
        $dpeParcours->setCampagneCollecte($context['campagne']);
        $dpeParcours->setVersion('0.1');
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);

        $parcours->addDpeParcour($dpeParcours);
        $em->persist($dpeParcours);

        $histo = new HistoriqueParcours();
        $histo->setParcours($parcours);
        $histo->setCreated(new \DateTime());
        $histo->setEtat('valide');
        $histo->setEtape('en_cours_redaction');
        $histo->setUser($context['user']);
        $em->persist($histo);

        $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
        $uc = new UserProfil();
        $uc->setUser($formation->getResponsableMention());
        $uc->setCampagneCollecte($context['campagne']);
        $uc->setFormation($formation);
        $uc->setProfil($profil);
        $em->persist($uc);

        $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
        $uc = new UserProfil();
        $uc->setUser($formation->getResponsableMention());
        $uc->setCampagneCollecte($context['campagne']);
        $uc->setParcours($parcours);
        $uc->setProfil($profil);
        $em->persist($uc);

        if ($formation->getCoResponsable() !== null) {
            $profil = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_FORMATION']);
            $ucCo = new UserProfil();
            $ucCo->setUser($formation->getCoResponsable());
            $ucCo->setCampagneCollecte($context['campagne']);
            $ucCo->setFormation($formation);
            $ucCo->setProfil($profil);
            $em->persist($ucCo);
        }

        $em->flush();
        $dpeParcoursWorkflow->apply($dpeParcours, 'initialiser');
        $dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');

        $redirect = $request->query->get('redirect') ?? $request->request->get('redirect');
        if ($redirect === 'app_offre_index') {
            return new RedirectResponse($router->generate('app_offre_index'));
        }

        return new RedirectResponse($router->generate('app_formation_index'));
    }
}
