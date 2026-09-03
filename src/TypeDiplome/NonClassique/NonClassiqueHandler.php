<?php

namespace App\TypeDiplome\NonClassique;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\AbstractTypeDiplomeHandler;
use App\TypeDiplome\ValideParcoursInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Entity\Formation;
use App\Entity\DpeParcours;
use App\Entity\UserProfil;
use App\Enums\TypeModificationDpeEnum;


final class NonClassiqueHandler extends AbstractTypeDiplomeHandler
{
    public const TEMPLATE_FOLDER = 'non_classique';
    public const SOURCE = 'non_classique';
    public const TEMPLATE_FORM_MCCC = 'non_classique.html.twig';

    public function supports(string $type): bool
    {
        return false;
    }

    public function showStructure(
        Parcours               $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): array
    {
        return [
            'parcours' => $parcours,
            'dureeParcours' => $parcours->getDureeParcours(),
            'dureeParcoursUnite' => $parcours->getDureeParcoursUnite(),
        ];
    }

    public function getStructureCompetences(Parcours $parcours): array
    {
        return [];
    }

    public function getTypeEpreuves(): array
    {
        return [];
    }

    public function getLibelleCourt(): string
    {
        return 'NON_CLASSIQUE';
    }

    public function getMcccs(ElementConstitutif|FicheMatiere $elementConstitutif): array|Collection
    {
        return [];
    }

    public function saveMcccs(ElementConstitutif|FicheMatiere $elementConstitutif, InputBag $request): void
    {
        // No MCCC structure for non-classique parcours
    }

    public function clearMcccs(ElementConstitutif|FicheMatiere $objet): void
    {
        // No MCCC structure for non-classique parcours
    }

    public function exportExcelMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): StreamedResponse
    {
        return new StreamedResponse();
    }

    public function exportExcelVersionMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): StreamedResponse
    {
        return new StreamedResponse();
    }

    public function exportExcelAndSaveVersionMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, string $dir, string $fichier, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null): string
    {
        return '';
    }

    public function exportPdfMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): Response
    {
        return new Response();
    }

    public function exportAndSaveExcelMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        return '';
    }

    public function exportAndSavePdfMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        return '';
    }

    public function checkIfMcccValide(FicheMatiere|ElementConstitutif $owner): bool
    {
        return true;
    }

    public function calcul(
        Parcours $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): StructureParcours
    {
        return $this->calculStructureParcours(
            $parcours,
            $optionsCalculStructure->withEcts,
            $optionsCalculStructure->withBcc
        );
    }

    public function calculStructureParcours(
        Parcours $parcours,
        bool     $withEcts = true,
        bool     $withBcc = true
    ): StructureParcours
    {
        $structure = new StructureParcours();
        // No semester structure — just store duration info for display
        $structure->dureeParcours = $parcours->getDureeParcours();
        $structure->dureeParcoursUnite = $parcours->getDureeParcoursUnite();
        return $structure;
    }

    public function calculVersioning(
        Parcours               $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): StructureParcours
    {
        return $this->calculStructureParcours(
            $parcours,
            $optionsCalculStructure->withEcts,
            $optionsCalculStructure->withBcc
        );
    }

    public function calculStructureSemestre(
        SemestreParcours       $semestreParcours,
        Parcours               $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): StructureSemestre
    {
        return new StructureSemestre();
    }

    public function createFormMccc(ElementConstitutif|FicheMatiere $element): FormInterface
    {
        throw new \LogicException('No MCCC form for non-classique parcours.');
    }

    public function getTemplateFolder(): string
    {
        return self::TEMPLATE_FOLDER;
    }

    public function getValidator(): ValideParcoursInterface
    {
        throw new \LogicException('No validator available for non-classique parcours.');
    }

    public function getFormationFormType(): string
    {
        return \App\Form\FormulaireGeneriqueType::class;
    }

    public function getFormationFormTemplate(): string
    {
        return 'formulaire_generique/_creation_form.html.twig';
    }

    public function getFormationFormOptions(array $context): array
    {
        return [
            'data' => null,
            'action' => $context['action'],
            'current_user' => $context['user'],
            'can_choose_responsable' => $context['is_admin'],
        ];
    }

    public function handleFormationSubmission(
        FormInterface $form,
        Request $request,
        array $context
    ): ?Response {
        $data = $form->getData();
        $em = $context['entityManager'];
        $mentionRepository = $context['mentionRepository'];
        $formationRepository = $context['formationRepository'];
        $parcoursRepository = $context['parcoursRepository'];
        $profilRepository = $context['profilRepository'];
        $dpeParcoursWorkflow = $context['workflow'];
        $eventDispatcher = $context['eventDispatcher'];
        $router = $context['router'];

        $mentionId = $form->get('mentionExistante')->getData();
        if (empty($mentionId)) {
            $form->get('mentionExistante')->addError(new \Symfony\Component\Form\FormError('Veuillez sélectionner ou créer un intitulé de formation.'));
            return null;
        }

        $mention = $mentionRepository->find($mentionId);
        if ($mention === null) {
            $form->get('mentionExistante')->addError(new \Symfony\Component\Form\FormError('Intitulé de formation introuvable.'));
            return null;
        }

        try {
            $formationId = $form->get('formationExistante')->getData();
            $formation = null;

            if (!empty($formationId)) {
                $formation = $formationRepository->find($formationId);
            }

            if ($formation === null) {
                $formation = $formationRepository->findOneBy([
                    'mention' => $mention,
                    'typeDiplome' => $data['typeDiplome'],
                    'dpe' => $context['campagne'],
                ]);
            }

            $formationCreee = $formation === null;
            $plusieursParcours = (bool)$form->get('plusieursParcours')->getData();
            $estMono = $formationCreee && !$plusieursParcours;

            if ($formation === null) {
                $formation = new Formation($context['campagne']);
                $formation->setTypeDiplome($data['typeDiplome']);
                $formation->setMention($mention);
                $formation->setSigle($mention->getSigle());

                $responsableMention = $context['is_admin'] ? ($data['responsableMention'] ?? $context['user']) : $context['user'];
                $formation->setResponsableMention($responsableMention);
                $coResponsableMention = $data['coResponsableMention'] ?? null;
                $formation->setCoResponsable($coResponsableMention);

                $formation->setHasParcours($plusieursParcours);
                $formation->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_PARCOURS);
                $formation->setNiveauEntree($data['niveauEntree']);
                $formation->setNiveauSortie($data['niveauSortie']);

                if (!empty($data['localisationMention'])) {
                    foreach ($data['localisationMention'] as $ville) {
                        $formation->addLocalisationMention($ville);
                    }
                }
                if (!empty($data['composantesInscription'])) {
                    foreach ($data['composantesInscription'] as $composante) {
                        $formation->addComposantesInscription($composante);
                    }
                }
                if (!empty($data['regimeInscription'])) {
                    $formation->setRegimeInscription($data['regimeInscription']);
                }

                $formation->setRythmeFormation($data['rythmeFormation'] ?? null);
                $formation->setRythmeFormationTexte($data['rythmeFormationTexte'] ?? null);
                $em->persist($formation);
            } else {
                $formation->setHasParcours(true);
            }

            $parcours = new Parcours($formation);
            if ($estMono) {
                $libelleParcours = $mention->getLibelle();
                $respParcours = $responsableMention;
                $coRespParcours = $coResponsableMention;
            } else {
                $libelleParcours = trim((string)($data['libelle'] ?? '')) ?: $mention->getLibelle();
                $respParcours = $context['is_admin'] ? ($data['respParcours'] ?? $context['user']) : $context['user'];
                $coRespParcours = $data['coRespParcours'] ?? null;
            }
            $parcours->setLibelle($libelleParcours);
            $parcours->setRythmeFormation($data['rythmeFormation'] ?? null);
            $parcours->setRythmeFormationTexte($data['rythmeFormationTexte'] ?? null);
            $parcours->setRespParcours($respParcours);
            $parcours->setCoResponsable($coRespParcours);
            $parcours->setModalitesEnseignement(null);
            $parcours->setDureeParcours($data['dureeParcours'] ?? null);
            $parcours->setDureeParcoursUnite($data['dureeParcoursUnite'] ?? null);

            if ($estMono) {
                $parcours->setComposanteInscription($formation->getComposantesInscription()->first() ?: null);
                $parcours->setLocalisation($formation->getLocalisationMention()->first() ?: null);
                $parcours->setRegimeInscription($formation->getRegimeInscription());
            }

            $em->persist($parcours);

            $dpeParcours = new DpeParcours();
            $dpeParcours->setParcours($parcours);
            $dpeParcours->setFormation($formation);
            $dpeParcours->setCampagneCollecte($context['campagne']);
            $dpeParcours->setVersion('0.1');
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
            $dpeParcoursWorkflow->apply($dpeParcours, 'initialiser');

            if ($data['typeDiplome']->isPassageCfvu()) {
                $dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');
            } else {
                $dpeParcours->setEtatValidation(['en_cours_redaction_ss_cfvu' => 1]);
            }
            $parcours->addDpeParcour($dpeParcours);
            $em->persist($dpeParcours);

            // Create DpeDemande
            $dpeDemande = new \App\Entity\DpeDemande();
            $dpeDemande->setParcours($parcours);
            $dpeDemande->setFormation($formation);
            $dpeDemande->setCampagneCollecte($context['campagne']);
            $dpeDemande->setNiveauDemande('P');
            $dpeDemande->setEtatDemande(\App\Enums\EtatDpeEnum::en_cours_redaction);
            $dpeDemande->setArgumentaireDemande('Création d\'un nouveau parcours');
            $dpeDemande->setNiveauModification(TypeModificationDpeEnum::CREATION);
            $dpeDemande->setAuteur($context['user']);
            $em->persist($dpeDemande);

            // UserProfil for responsable formation
            $profilRespFormation = $profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
            if ($profilRespFormation !== null) {
                $uc = new UserProfil();
                $uc->setUser($context['user']);
                $uc->setCampagneCollecte($context['campagne']);
                $uc->setFormation($formation);
                $uc->setProfil($profilRespFormation);
                $em->persist($uc);
            }

            // UserProfil for co-responsable formation (optionnel)
            if ($formationCreee && $coResponsableMention !== null) {
                $profilCoRespFormation = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_FORMATION']);
                if ($profilCoRespFormation !== null) {
                    $ucCo = new UserProfil();
                    $ucCo->setUser($coResponsableMention);
                    $ucCo->setCampagneCollecte($context['campagne']);
                    $ucCo->setFormation($formation);
                    $ucCo->setProfil($profilCoRespFormation);
                    $em->persist($ucCo);
                }
            }

            $em->flush();
            $parcoursRepository->save($parcours, true);

            // Dispatch events
            $respParcoursProfil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
            if ($respParcoursProfil !== null) {
                $event = new \App\Event\AddCentreParcoursEvent(
                    $parcours,
                    $context['user'],
                    $respParcoursProfil,
                    $context['campagne']
                );
                $eventDispatcher->dispatch($event, \App\Event\AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
            }

            if ($coRespParcours !== null) {
                $coRespParcoursProfil = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_PARCOURS']);
                if ($coRespParcoursProfil !== null) {
                    $event = new \App\Event\AddCentreParcoursEvent(
                        $parcours,
                        $coRespParcours,
                        $coRespParcoursProfil,
                        $context['campagne']
                    );
                    $eventDispatcher->dispatch($event, \App\Event\AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
                }
            }

            $context['controller']->addFlashBag(
                \App\Entity\Constantes::FLASHBAG_SUCCESS,
                $formationCreee
                    ? 'La formation et son parcours ont été créés avec succès.'
                    : 'Le parcours a été créé avec succès.'
            );

            if ($formationCreee) {
                return new RedirectResponse($router->generate('app_formation_show', ['slug' => $formation->getSlug()]));
            }

            return new RedirectResponse($router->generate('app_parcours_show', ['id' => $parcours->getId()]));

        } catch (\Throwable $e) {
            $em->clear();
            $context['controller']->addFlashBag(\App\Entity\Constantes::FLASHBAG_ERROR, 'Une erreur est survenue lors de la création du parcours. Veuillez réessayer.');
            return null;
        }
    }
}
