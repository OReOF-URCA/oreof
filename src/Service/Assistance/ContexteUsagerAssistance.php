<?php

namespace App\Service\Assistance;

use App\Entity\CampagneCollecte;
use App\Entity\DemandeAssistance;
use App\Entity\DpeDemande;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CampagneModuleEnum;
use App\Repository\CampagneCollecteRepository;
use App\Repository\UserRepository;
use App\Service\CampagneAccessibilityService;

/**
 * Contexte ORéOF du demandeur d'une demande d'assistance : compte, profils, ouverture des modules
 * pour la campagne par défaut et les campagnes de ses profils, dernières demandes d'accès.
 */
class ContexteUsagerAssistance
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CampagneCollecteRepository $campagneRepository,
        private readonly CampagneAccessibilityService $campagneAccessibility,
    ) {
    }

    /**
     * @return array{
     *     user: User,
     *     roles: list<string>,
     *     compteValide: bool,
     *     profils: list<UserProfil>,
     *     campagnes: list<array{campagne: CampagneCollecte, modules: list<array<string, mixed>>}>,
     *     demandesAcces: list<DpeDemande>
     * }|null
     */
    public function pour(DemandeAssistance $demande): ?array
    {
        $user = $demande->getDemandeur() ?? $this->userRepository->findOneBy(['email' => $demande->getDemandeurEmail()]);
        if (!$user instanceof User) {
            return null;
        }

        $profils = array_values($user->getUserProfils()->toArray());

        $campagnes = [];
        $defaut = $this->campagneRepository->findOneBy(['defaut' => true]);
        if ($defaut !== null) {
            $campagnes[$defaut->getId()] = $defaut;
        }
        foreach ($profils as $profil) {
            $campagne = $profil->getCampagneCollecte();
            if ($campagne !== null && !isset($campagnes[$campagne->getId()])) {
                $campagnes[$campagne->getId()] = $campagne;
            }
        }

        $blocs = [];
        foreach ($campagnes as $campagne) {
            $modules = [];
            foreach (CampagneModuleEnum::cases() as $module) {
                $modules[] = $this->campagneAccessibility->getModuleStatus($module, $campagne);
            }
            $blocs[] = ['campagne' => $campagne, 'modules' => $modules];
        }

        $demandesAcces = $user->getDpeDemandes()->toArray();
        usort($demandesAcces, static fn (DpeDemande $a, DpeDemande $b) => $b->getDateDemande() <=> $a->getDateDemande());

        return [
            'user' => $user,
            'roles' => array_values($user->getRoles()),
            'compteValide' => (bool) $user->isEnable(),
            'profils' => $profils,
            'campagnes' => $blocs,
            'demandesAcces' => array_slice($demandesAcces, 0, 5),
        ];
    }
}
