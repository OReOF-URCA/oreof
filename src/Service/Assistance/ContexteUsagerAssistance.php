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
 * Contexte ORéOF du demandeur d'une demande d'assistance : compte, profils, ouverture des modules résumée
 * pour la campagne par défaut et les campagnes encore actives de ses profils, dernières demandes d'accès.
 */
class ContexteUsagerAssistance
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CampagneCollecteRepository $campagneRepository,
        private readonly CampagneAccessibilityService $campagneAccessibility,
        private readonly ResumePeriodesSaisie $resumePeriodes,
    ) {
    }

    /**
     * @return array{
     *     user: User,
     *     roles: list<string>,
     *     compteValide: bool,
     *     profils: list<UserProfil>,
     *     campagnes: list<array{campagne: CampagneCollecte, etat: string, resume: string, details: list<array<string, mixed>>, active: bool}>,
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
            $resume = $this->resumePeriodes->resumer($modules);
            // campagne d'un profil sans saisie ouverte ni à venir (campagne passée) : rien d'utile à afficher
            if ($campagne->isDefaut() !== true && !$resume['active']) {
                continue;
            }
            $blocs[] = ['campagne' => $campagne] + $resume;
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

    /**
     * Même contexte en texte brut pour l'assistant IA (sans adresse mail ni identifiant de connexion).
     */
    public function enTexte(DemandeAssistance $demande): string
    {
        $contexte = $this->pour($demande);
        if ($contexte === null) {
            return "Contexte ORéOF de l'usager : aucun compte ORéOF.";
        }

        $roles = array_values(array_filter($contexte['roles'], static fn (string $r) => $r !== 'ROLE_USER'));
        $lignes = [
            "Contexte ORéOF de l'usager :",
            '- ' . ($contexte['compteValide'] ? 'Compte actif' : 'Compte non activé')
                . ($roles !== [] ? ', rôles : ' . implode(', ', $roles) : ''),
        ];

        $profils = array_map(static function (UserProfil $p): string {
            $texte = $p->getProfil()?->getLibelle() ?? '—';
            if ($p->getDisplayCentre()) {
                $texte .= ' sur ' . $p->getDisplayCentre();
            }

            return $texte . ($p->getCampagneCollecte() !== null ? ' (' . $p->getCampagneCollecte()->getLibelle() . ')' : '');
        }, $contexte['profils']);
        $lignes[] = '- Profils : ' . ($profils !== [] ? implode(' ; ', $profils) : 'aucun');

        if ($contexte['campagnes'] !== []) {
            $lignes[] = '- Périodes de saisie :';
            foreach ($contexte['campagnes'] as $bloc) {
                $lignes[] = sprintf('  - %s%s : %s', $bloc['campagne']->getLibelle(), $bloc['campagne']->isDefaut() === true ? ' (par défaut)' : '', $bloc['resume']);
                foreach ($bloc['details'] as $statut) {
                    $lignes[] = sprintf('    - %s : %s', $statut['module']->getLabel(), $statut['message']);
                }
            }
        }

        if ($contexte['demandesAcces'] !== []) {
            $lignes[] = '- Dernières demandes de modification :';
            foreach ($contexte['demandesAcces'] as $dpe) {
                $objet = $dpe->getParcours()?->getDisplay() ?? $dpe->getFormation()?->getDisplay() ?? '—';
                $lignes[] = sprintf('  - %s %s%s', $dpe->getDateDemande()?->format('d/m/Y') ?? '', $objet, $dpe->getEtatDemande() !== null ? ' (' . $dpe->getEtatDemande()->getLibelle() . ')' : '');
            }
        }

        return implode("\n", $lignes);
    }
}
