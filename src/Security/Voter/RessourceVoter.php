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
use App\Enums\TypeModificationDpeEnum;
use App\Repository\UserProfilRepository;
use App\Repository\ProfilDroitsRepository;
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
        return in_array($attribute, PermissionEnum::getAvailableTypes(), true)
            && is_array($subject)
            && isset($subject['route'], $subject['subject']);
    }

    private function getAttributesIncludingStronger(string $attribute): array
    {
        // Exemple de hiérarchie : MANAGE > EDIT > SHOW
        return match ($attribute) {
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

        $route = $subject['route'];
        $object = $subject['subject'];

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
            // Si l'objet est une formation, on vérifie si la composante porteuse de la formation correspond à la composante de l'utilisateur
            return $this->checkFormation($userProfil, $object, $attribute);
        }

        if ($object instanceof DpeParcours || $object instanceof Parcours) {
            // Si l'objet est un DPE Parcours, on vérifie si la composante porteuse de la formation correspond à la composante de l'utilisateur
            return $this->checkParcours($userProfil, $object, $attribute);
        }

        if ($object instanceof FicheMatiere) {
            return $this->checkFicheMatiere($userProfil, $object, $attribute);
        }

        return false;
    }

    private function checkComposante(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        //todo: attribute est utile car si EDIT ou MANAGE alors vérifier que c'est le DPE ou le RF/RP, sinon juste SHOW et juste le centre dans ce cas là
        // reflechir au cas d'un DPE qui est sur composante et peut éditer et un directeur qui ne peux pas éditer mais juste voir ? ou une scolarité qui peut voir mais pas éditer ?

        if ($object instanceof Composante) {
            return $userProfil->getComposante() === $object;
        }

        if ($object instanceof Formation) {
            // Si l'objet est une formation, on vérifie si la composante porteuse de la formation correspond à la composante de l'utilisateur
            return $this->checkFormation($userProfil, $object, $attribute);
        }

        if ($object instanceof DpeParcours || $object instanceof Parcours) {
            // Si l'objet est un DPE Parcours, on vérifie si la composante porteuse de la formation correspond à la composante de l'utilisateur
            return $this->checkParcours($userProfil, $object, $attribute);
        }

        if ($object instanceof FicheMatiere) {
            if ($object->isHorsDiplome() === true) {
                return true;
            }
            return $this->checkFicheMatiere($userProfil, $object, $attribute);
        }

        return false;
    }

    public function checkFicheMatiere(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        $isProprietaire = false;
        $canAccess = false;
        //une fiche peut être éditée par le RP, RF ou le DPE
        if ($object instanceof FicheMatiere) {

            $canAccess = $this->ficheWorkflow->can($object, 'autoriser') || $this->ficheWorkflow->can($object, 'valider_fiche_compo');

            if ($object->isHorsDiplome() && $object->getResponsableFicheMatiere() === $userProfil->getUser()) {
                return $canAccess;
            }

            $isProprietaire = (
                ($userProfil->getParcours() === $object->getParcours() && ($object->getParcours()?->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $object->getParcours()?->getRespParcours()?->getId() === $userProfil->getUser()?->getId())) ||
                ($userProfil->getFormation() === $object->getParcours()?->getFormation() && ($object->getParcours()?->getFormation()?->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $object->getParcours()?->getFormation()?->getResponsableMention()?->getId() === $userProfil->getUser()?->getId())) ||
                ($userProfil->getComposante() === $object->getParcours()?->getFormation()?->getComposantePorteuse() && $object->getParcours()?->getFormation()?->getComposantePorteuse()?->getResponsableDpe()?->getId() === $userProfil->getUser()?->getId())
            );
        }

        return $isProprietaire && $canAccess;
    }

    private function checkFormation(UserProfil $userProfil, mixed $object, string $attribute): bool
    {
        if ($object instanceof Formation) {
            // if ($attribute === 'show') {
            $isProprietaire = ($userProfil->getFormation() === $object);
            /* } else {
                 $isProprietaire = (($userProfil->getFormation() === $object && ($object->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $object->getResponsableMention()?->getId() === $userProfil->getUser()?->getId())) || ($userProfil->getComposante() === $object->getComposantePorteuse() && $object->getComposantePorteuse()?->getResponsableDpe()?->getId() === $userProfil->getUser()?->getId()));
           }*/
//todo: gérer le workflow?
            $canAccess =
                $object->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_TEXTE ||
                $object->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE ||
                $object->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC ||
                $object->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_PARCOURS ||
                $object->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION;

            if ($object->isHasParcours() === false) {
                // parcours par défaut
                $parcours = $object->getParcours()->first();
                if ($parcours instanceof Parcours) {
                    $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                    if ($dpeParcours !== null) {
                        $canAccess = $canAccess || $this->checkParcours($userProfil, $dpeParcours, $attribute);
                    }
                }
            }

            if ($attribute === 'manage') {
                $canAccess = $canAccess || $object->getEtatReconduction() === TypeModificationDpeEnum::OUVERT;
            }


            return $canAccess && $isProprietaire;
        }
        if ($object instanceof DpeParcours) {
            return $this->checkParcours($userProfil, $object, $attribute);
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
        }

        if ($object instanceof DpeParcours) {
            $dpeParcours = $object;
            $parcours = $dpeParcours->getParcours();
        }

        if ($parcours === null) {
            return false;
        }

        $canAccess = false;
        $isProprietaire = (
            ($userProfil->getParcours() === $parcours && ($parcours->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $parcours->getRespParcours()?->getId() === $userProfil->getUser()?->getId())) ||
            ($userProfil->getFormation() === $parcours->getFormation() && ($parcours->getFormation()?->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $parcours->getFormation()?->getResponsableMention()?->getId() === $userProfil->getUser()?->getId())) ||
            ($userProfil->getComposante() === $parcours->getFormation()?->getComposantePorteuse() &&
                $parcours->getFormation()?->getComposantePorteuse()?->getResponsableDpe()?->getId() === $userProfil->getUser()?->getId()) ||
            //c'est le niveau établissement
            ($userProfil->getProfil()?->getCentre() === CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT)
        );

        if ($parcours->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $parcours->getRespParcours()?->getId() === $userProfil->getUser()?->getId()) {
            $canAccess = $this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') || $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu');
        }

        if ($parcours->getFormation()?->getCoResponsable()?->getId() === $userProfil->getUser()?->getId() || $parcours->getFormation()?->getResponsableMention()?->getId() === $userProfil->getUser()?->getId()) {
            $canAccess = $this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_publication') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_rf');
        }

        if ($userProfil->getComposante() === $parcours->getFormation()?->getComposantePorteuse() &&
            $userProfil->getComposante()?->getResponsableDpe() === $userProfil->getUser()) {
            //todo: filtre pas si les bons droits... Edit ou lecture ?
            $canAccess = $this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_dpe_composante') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_conseil') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_publication') ||

                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_rf');
        }

        if ($attribute === 'manage') {
            $canAccess = $canAccess || $this->dpeParcoursWorkflow->can($dpeParcours, 'reouvrir_mccc') || $this->dpeParcoursWorkflow->can($dpeParcours, 'relancer_annee') || $this->dpeParcoursWorkflow->can($dpeParcours, 'reouvrir_tacite') || $this->dpeParcoursWorkflow->can($dpeParcours, 'reouvrir_avant_publie');
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $isProprietaire = true;
            $canAccess =
                $this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_ouverture_sans_cfvu') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_parcours') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_rf') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_dpe_composante') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_conseil') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_publication') ||
                $this->dpeParcoursWorkflow->can($dpeParcours, 'valider_central');
        }

        return $canAccess && $isProprietaire;

    }
}
