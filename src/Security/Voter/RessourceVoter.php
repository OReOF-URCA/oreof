<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Security/Voter/RessourceVoter.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

// src/Security/Voter/ResourceVoter.php
namespace App\Security\Voter;

use App\Classes\GetDpeParcours;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Etablissement;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use App\Enums\PermissionEnum;
use App\Enums\RessourceEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\UserProfilRepository;
use App\Repository\ProfilDroitsRepository;
use App\Utils\Access;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Workflow\WorkflowInterface;

class RessourceVoter extends Voter
{
    public function __construct(
        private readonly WorkflowInterface      $dpeParcoursWorkflow,
        private readonly WorkflowInterface      $ficheWorkflow,
        private readonly Security               $security,
        private readonly UserProfilRepository   $userProfilRepository,
        private readonly ProfilDroitsRepository $profilDroitsRepository,
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array(strtoupper($attribute), PermissionEnum::getAvailableTypes(), true)) {
            return false;
        }

        if (is_array($subject) && isset($subject['route'], $subject['subject'])) {
            return true;
        }

        if (is_string($subject)) {
            return in_array($subject, [
                'parcours',
                'formation',
                'composante',
                'etablissement',
                'fiche_matiere',
                'fiche_matiere_hd',
                'ec',
            ], true);
        }

        return $subject instanceof Parcours
            || $subject instanceof DpeParcours
            || $subject instanceof Formation
            || $subject instanceof FicheMatiere
            || $subject instanceof Composante
            || $subject instanceof Etablissement;
    }

    private function normalizeSubject(mixed $subject): array
    {
        if (is_array($subject) && isset($subject['route'], $subject['subject'])) {
            return [$subject['route'], $subject['subject']];
        }

        if (is_string($subject)) {
            $route = str_starts_with($subject, 'app_') ? $subject : 'app_' . $subject;
            return [$route, $subject];
        }

        if ($subject instanceof Parcours || $subject instanceof DpeParcours) {
            return [RessourceEnum::app_parcours->value, $subject];
        }

        if ($subject instanceof Formation) {
            return [RessourceEnum::app_formation->value, $subject];
        }

        if ($subject instanceof FicheMatiere) {
            $route = $subject->isHorsDiplome() ? RessourceEnum::app_fiche_matiere_hd->value : RessourceEnum::app_fiche_matiere->value;
            return [$route, $subject];
        }

        if ($subject instanceof Composante) {
            return [RessourceEnum::app_composante->value, $subject];
        }

        if ($subject instanceof Etablissement) {
            return [RessourceEnum::app_etablissement->value, $subject];
        }

        return [null, null];
    }

    private function getAttributesIncludingStronger(string $attribute): array
    {
        // Exemple de hiérarchie : MANAGE > EDIT > SHOW
        return match (strtoupper($attribute)) {
            PermissionEnum::SHOW->value => [PermissionEnum::MANAGE->value, PermissionEnum::SHOW->value, PermissionEnum::EDIT->value],
            PermissionEnum::EDIT->value => [PermissionEnum::MANAGE->value, PermissionEnum::EDIT->value],
            PermissionEnum::MANAGE->value => [PermissionEnum::MANAGE->value],
            default => [$attribute],
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $attribute = strtolower($attribute);
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        [$route, $object] = $this->normalizeSubject($subject);
        if ($route === null || $object === null) {
            return false;
        }

        // Cas administrateur
        if ($this->security->isGranted('ROLE_ADMIN')) {
            if ($attribute === 'show') {
                return true;
            }
            if (is_string($object)) {
                return true;
            }
            if ($object instanceof Parcours || $object instanceof DpeParcours) {
                return $this->checkParcoursWorkflowAdmin($object);
            }
            if ($object instanceof Formation) {
                return Access::isOuvert($object);
            }
            if ($object instanceof FicheMatiere) {
                return $object->isModifiable();
            }
            return true;
        }

        // Récupère tous les profils liés à l'utilisateur
        $userProfils = $this->userProfilRepository->findBy(['user' => $user]);

        $attributesToCheck = $this->getAttributesIncludingStronger($attribute);
        foreach ($userProfils as $userProfil) {
            $profile = $userProfil->getProfil();
            foreach ($attributesToCheck as $attr) {
                if ($this->profilDroitsRepository->hasDroit($profile, $attr, $route) && $this->checkScope($userProfil, $object, $attr)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function checkScope(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        $centre = $userProfil->getProfil()?->getCentre();
        return match ($centre) {
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $this->checkEtablissement($userProfil, $object, $attribute) || $object === 'etablissement',
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $this->checkComposante($userProfil, $object, $attribute) || $object === 'composante',
            CentreGestionEnum::CENTRE_GESTION_FORMATION => $this->checkFormation($userProfil, $object, $attribute) || $object === 'formation',
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => $this->checkParcours($userProfil, $object, $attribute) || $object === 'parcours',
            default => false,
        };
    }

    private function checkEtablissement(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        if ($object instanceof Etablissement) {
            return $userProfil->getEtablissement() === $object;
        }

        if ($object instanceof Composante) {
            return $userProfil->getComposante() === $object;
        }

        if ($object instanceof Formation) {
            return $this->checkFormation($userProfil, $object, $attribute);
        }

        if ($object instanceof DpeParcours || $object instanceof Parcours) {
            return $this->checkParcours($userProfil, $object, $attribute);
        }

        if ($object instanceof FicheMatiere) {
            return $this->checkFicheMatiere($userProfil, $object, $attribute);
        }

        return false;
    }

    private function checkComposante(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        if ($object instanceof Composante) {
            return $userProfil->getComposante() === $object;
        }

        if ($object instanceof Formation) {
            return $this->checkFormation($userProfil, $object, $attribute);
        }

        if ($object instanceof DpeParcours || $object instanceof Parcours) {
            return $this->checkParcours($userProfil, $object, $attribute);
        }

        if ($object instanceof FicheMatiere) {
            return $this->checkFicheMatiere($userProfil, $object, $attribute);
        }

        return false;
    }

    public function checkFicheMatiere(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        if (!$object instanceof FicheMatiere) {
            return false;
        }

        $userId = $userProfil->getUser()?->getId();
        $isRespFiche = ($object->getResponsableFicheMatiere()?->getId() === $userId);

        if ($object->isHorsDiplome()) {
            $inScope = ($isRespFiche || $userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT || ($userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_COMPOSANTE));
        } else {
            $parcours = $object->getParcours();
            $inScope = match ($userProfil->getProfil()?->getCentre()) {
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => $userProfil->getParcours() === $parcours,
                CentreGestionEnum::CENTRE_GESTION_FORMATION => $userProfil->getFormation() === $parcours?->getFormation(),
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $userProfil->getComposante() === $parcours?->getFormation()?->getComposantePorteuse(),
                CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => true,
                default => false,
            };
        }

        if (!$inScope && !$isRespFiche) {
            return false;
        }

        if ($attribute === 'show') {
            return true;
        }

        if (!$object->isModifiable()) {
            return false;
        }

        return $this->ficheWorkflow->can($object, 'autoriser') || $this->ficheWorkflow->can($object, 'valider_fiche_compo');
    }

    private function checkFormation(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        if ($object instanceof Formation) {
            $inScope = match ($userProfil->getProfil()?->getCentre()) {
                CentreGestionEnum::CENTRE_GESTION_FORMATION => $userProfil->getFormation() === $object,
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $userProfil->getComposante() === $object->getComposantePorteuse(),
                CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => true,
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => $object->isHasParcours() === false && $userProfil->getParcours()?->getFormation() === $object,
                default => false,
            };

            if (!$inScope) {
                return false;
            }

            if ($attribute === 'show') {
                return true;
            }

            if (!Access::isOuvert($object)) {
                return false;
            }

            if ($attribute === 'manage') {
                return $object->getEtatReconduction() === TypeModificationDpeEnum::OUVERT;
            }

            // Cas monoparcours
            if ($object->isHasParcours() === false) {
                $firstParcours = $object->getParcours()->first() ?: null;
                if ($firstParcours instanceof Parcours) {
                    $dpeParcours = GetDpeParcours::getFromParcours($firstParcours);
                    if ($dpeParcours !== null) {
                        return $this->checkParcours($userProfil, $dpeParcours, $attribute);
                    }
                }
            }

            return in_array($object->getEtatReconduction()->value, [
                TypeModificationDpeEnum::MODIFICATION->value,
                TypeModificationDpeEnum::MODIFICATION_TEXTE->value,
                TypeModificationDpeEnum::MODIFICATION_INTITULE->value,
                TypeModificationDpeEnum::MODIFICATION_PARCOURS->value,
                TypeModificationDpeEnum::MODIFICATION_MCCC->value,
                TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE->value,
            ], true);
        }

        if ($object instanceof DpeParcours || $object instanceof Parcours) {
            return $this->checkParcours($userProfil, $object, $attribute);
        }

        if ($object instanceof FicheMatiere) {
            return $this->checkFicheMatiere($userProfil, $object, $attribute);
        }

        return false;
    }

    private function checkParcours(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        $parcours = null;
        $dpeParcours = null;
        if ($object instanceof Parcours) {
            $parcours = $object;
            $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        } elseif ($object instanceof DpeParcours) {
            $dpeParcours = $object;
            $parcours = $dpeParcours->getParcours();
        }

        if ($parcours === null) {
            return false;
        }

        $userId = $userProfil->getUser()?->getId();
        if ($userId === null) {
            return false;
        }

        $isRespParcours = ($parcours->getRespParcours()?->getId() === $userId || $parcours->getCoResponsable()?->getId() === $userId);
        $isRespFormation = ($parcours->getFormation()?->getResponsableMention()?->getId() === $userId || $parcours->getFormation()?->getCoResponsable()?->getId() === $userId);
        $isRespComposante = ($parcours->getFormation()?->getComposantePorteuse()?->getResponsableDpe()?->getId() === $userId);
        $isEtablissement = ($userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT);

        $inScope = match ($userProfil->getProfil()?->getCentre()) {
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => $userProfil->getParcours() === $parcours,
            CentreGestionEnum::CENTRE_GESTION_FORMATION => $userProfil->getFormation() === $parcours->getFormation(),
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $userProfil->getComposante() === $parcours->getFormation()?->getComposantePorteuse(),
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => true,
            default => false,
        };

        if (!$inScope && !$isRespParcours && !$isRespFormation && !$isRespComposante && !$isEtablissement) {
            return false;
        }

        if ($attribute === 'show') {
            return true;
        }

        if ($dpeParcours !== null && !Access::isOuvert($dpeParcours)) {
            return false;
        }

        if ($attribute === 'manage') {
            return $dpeParcours === null || (
                $this->dpeParcoursWorkflow->can($dpeParcours, 'reouvrir_mccc') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'relancer_annee') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'reouvrir_tacite') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'reouvrir_avant_publie')
            );
        }

        if ($dpeParcours === null) {
            return true;
        }

        if ($isRespParcours || $userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_PARCOURS) {
            if ($this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_reserve_central_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu')) {
                return true;
            }
        }

        if ($isRespFormation || $userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_FORMATION) {
            if ($this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_reserve_central_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_publication') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_rf')) {
                return true;
            }
        }

        if ($isRespComposante || $userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_COMPOSANTE) {
            if ($this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_reserve_central_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_dpe_composante') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_conseil') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_publication') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_rf')) {
                return true;
            }
        }

        if ($isEtablissement) {
            return $this->checkParcoursWorkflowAdmin($dpeParcours);
        }

        return false;
    }

    private function checkParcoursWorkflowAdmin(DpeParcours|Parcours $object): bool
    {
        $dpeParcours = $object instanceof Parcours ? GetDpeParcours::getFromParcours($object) : $object;
        if ($dpeParcours === null) {
            return true;
        }

        if (!Access::isOuvert($dpeParcours)) {
            return false;
        }

        return $this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_rf') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_dpe_composante') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_reserve_central_cfvu') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_conseil') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_publication') ||
            $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_central');
    }
}
