<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/GetFormations.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/02/2024 08:57
 */

namespace App\Classes;

use App\Entity\CampagneCollecte;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Repository\FormationRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class GetFormations
{

    public function __construct(
        private FormationRepository $formationRepository,
        private AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    public function isEtablissement(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN') ||
            $this->authorizationChecker->isGranted('SHOW', ['route' => 'app_etablissement', 'subject' => 'etablissement']);
    }

    /**
     * @return array<int, \App\Entity\Formation>
     */
    public function getBaseFormations(
        User|UserInterface|null $user,
        CampagneCollecte $campagneCollecte
    ): array {
        if ($this->isEtablissement()) {
            $formations = $this->formationRepository->findBySearch('', $campagneCollecte, []);
            $tFormations = [];
            foreach ($formations as $formation) {
                $tFormations[$formation->getId()] = $formation;
            }
            return $tFormations;
        }

        $formations = [];
        if ($user instanceof User) {
            $centres = $user->getUserProfils();
            $tempFormation = [];
            /** @var UserProfil $centre */
            foreach ($centres as $centre) {
                if (
                    $centre->getComposante() !== null &&
                    $this->authorizationChecker->isGranted('SHOW', ['route' => 'app_composante', 'subject' => $centre->getComposante()])) {
                    $formations[] = $this->formationRepository->findByComposante(
                        $centre->getComposante(),
                        $campagneCollecte
                    );
                }

                if ($centre->getFormation() !== null) {
                    $formation = $centre->getFormation();
                    if ($formation->getDpe()?->getId() === $campagneCollecte->getId()) {
                        $tempFormation[] = $formation;
                    }
                }
            }

            $formations[] = $tempFormation;

            $formations[] = $this->formationRepository->findByComposanteDpe(
                $user,
                $campagneCollecte
            );
            $formations[] = $this->formationRepository->findByResponsableOuCoResponsable(
                $user,
                $campagneCollecte
            );
            $formations[] = $this->formationRepository->findByResponsableOuCoResponsableParcours(
                $user,
                $campagneCollecte,
                []
            );
        }

        $merged = array_merge(...$formations);
        $tFormations = [];
        foreach ($merged as $formation) {
            $tFormations[$formation->getId()] = $formation;
        }

        return $tFormations;
    }

    /**
     * @param array<\App\Entity\Formation> $formations
     * @return array{
     *     composantes: array<int, \App\Entity\Composante>,
     *     mentions: array<int, \App\Entity\Mention>,
     *     typeDiplomes: array<int, \App\Entity\TypeDiplome>,
     *     responsables: array<int, \App\Entity\User>
     * }
     */
    public function getFilterOptions(array $formations): array
    {
        $composantes = [];
        $mentions = [];
        $typeDiplomes = [];
        $responsables = [];

        foreach ($formations as $formation) {
            if ($formation->getComposantePorteuse() !== null) {
                $composantes[$formation->getComposantePorteuse()->getId()] = $formation->getComposantePorteuse();
            }

            if ($formation->getMention() !== null) {
                $mentions[$formation->getMention()->getId()] = $formation->getMention();
            }

            if ($formation->getTypeDiplome() !== null) {
                $typeDiplomes[$formation->getTypeDiplome()->getId()] = $formation->getTypeDiplome();
            }

            if ($formation->getResponsableMention() !== null) {
                $responsables[$formation->getResponsableMention()->getId()] = $formation->getResponsableMention();
            }

            if ($formation->getCoResponsable() !== null) {
                $responsables[$formation->getCoResponsable()->getId()] = $formation->getCoResponsable();
            }

            foreach ($formation->getParcours() as $parcours) {
                if ($parcours->getRespParcours() !== null) {
                    $responsables[$parcours->getRespParcours()->getId()] = $parcours->getRespParcours();
                }
                if ($parcours->getCoResponsable() !== null) {
                    $responsables[$parcours->getCoResponsable()->getId()] = $parcours->getCoResponsable();
                }
            }
        }

        uasort($composantes, fn($a, $b) => strcmp((string) $a->getLibelle(), (string) $b->getLibelle()));
        uasort($mentions, fn($a, $b) => strcmp((string) $a->getLibelle(), (string) $b->getLibelle()));
        uasort($typeDiplomes, fn($a, $b) => strcmp((string) $a->getLibelle(), (string) $b->getLibelle()));
        uasort($responsables, fn($a, $b) => strcmp((string) $a->getDisplay(), (string) $b->getDisplay()));

        return [
            'composantes' => array_values($composantes),
            'mentions' => array_values($mentions),
            'typeDiplomes' => array_values($typeDiplomes),
            'responsables' => array_values($responsables),
        ];
    }

    /**
     * @return array<int, \App\Entity\Formation>
     */
    public function getFormations(
        User|UserInterface|null $user,
        CampagneCollecte $campagneCollecte,
        array $options = [],
        bool $isCfvu = false
    ): array {
        $sort = $options['sort'] ?? 'typeDiplome';
        $direction = strtolower($options['direction'] ?? 'asc');
        $q = $options['q'] ?? null;

        if ($this->isEtablissement()) {
            $formations = $this->formationRepository->findBySearch($q, $campagneCollecte, $options);
            $tFormations = [];
            foreach ($formations as $formation) {
                if (isset($options['remplissage']) && $options['remplissage'] !== 'all' && $options['remplissage'] !== '') {
                    $remplissageVal = (int) $options['remplissage'];
                    $calcul = $formation->getRemplissage()->calcul();
                    if ($remplissageVal === 100 && $calcul < 100) {
                        continue;
                    }
                    if ($remplissageVal === 0 && $calcul >= 100) {
                        continue;
                    }
                }
                $tFormations[(int) $formation->getId()] = $formation;
            }
            return $tFormations;
        }

        $baseFormations = $this->getBaseFormations($user, $campagneCollecte);
        $filtered = [];

        foreach ($baseFormations as $formation) {
            // Recherche textuelle (q)
            if ($q !== null && trim($q) !== '') {
                $needle = mb_strtolower(trim($q));
                $found = false;

                $mentionLibelle = mb_strtolower((string) $formation->getMention()?->getLibelle());
                $mentionSigle = mb_strtolower((string) $formation->getMention()?->getSigle());
                $formationSigle = mb_strtolower((string) $formation->getSigle());
                $mentionTexte = mb_strtolower((string) $formation->getMentionTexte());
                $display = mb_strtolower((string) $formation->getDisplay());

                if (
                    str_contains($mentionLibelle, $needle) ||
                    str_contains($mentionSigle, $needle) ||
                    str_contains($formationSigle, $needle) ||
                    str_contains($mentionTexte, $needle) ||
                    str_contains($display, $needle)
                ) {
                    $found = true;
                } else {
                    foreach ($formation->getParcours() as $parcours) {
                        $parcoursLibelle = mb_strtolower((string) $parcours->getLibelle());
                        $parcoursSigle = mb_strtolower((string) $parcours->getSigle());
                        if (str_contains($parcoursLibelle, $needle) || str_contains($parcoursSigle, $needle)) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    continue;
                }
            }

            // Filtre par composante porteuse
            if (!empty($options['composantePorteuse'])) {
                if ($formation->getComposantePorteuse()?->getId() !== (int) $options['composantePorteuse']) {
                    continue;
                }
            }

            // Filtre par type diplôme
            if (!empty($options['typeDiplome'])) {
                if ($formation->getTypeDiplome()?->getId() !== (int) $options['typeDiplome']) {
                    continue;
                }
            }

            // Filtre par mention
            if (!empty($options['mention'])) {
                if ($formation->getMention()?->getId() !== (int) $options['mention']) {
                    continue;
                }
            }

            // Filtre par responsable
            if (!empty($options['responsable'])) {
                $respId = (int) $options['responsable'];
                $isResp = ($formation->getResponsableMention()?->getId() === $respId)
                    || ($formation->getCoResponsable()?->getId() === $respId);

                if (!$isResp) {
                    foreach ($formation->getParcours() as $parcours) {
                        if ($parcours->getRespParcours()?->getId() === $respId || $parcours->getCoResponsable()?->getId() === $respId) {
                            $isResp = true;
                            break;
                        }
                    }
                }

                if (!$isResp) {
                    continue;
                }
            }

            // Filtre par remplissage
            if (isset($options['remplissage']) && $options['remplissage'] !== 'all' && $options['remplissage'] !== '') {
                $remplissageVal = (int) $options['remplissage'];
                $calcul = $formation->getRemplissage()->calcul();
                if ($remplissageVal === 100 && $calcul < 100) {
                    continue;
                }
                if ($remplissageVal === 0 && $calcul >= 100) {
                    continue;
                }
            }

            $filtered[(int) $formation->getId()] = $formation;
        }

        // Tri
        uasort($filtered, function ($a, $b) use ($sort, $direction) {
            $valA = match ($sort) {
                'mention' => (string) ($a->getMention()?->getLibelle() ?? $a->getMentionTexte()),
                'composantePorteuse' => (string) $a->getComposantePorteuse()?->getLibelle(),
                'typeDiplome' => (string) $a->getTypeDiplome()?->getLibelle(),
                default => (string) $a->getDisplay(),
            };
            $valB = match ($sort) {
                'mention' => (string) ($b->getMention()?->getLibelle() ?? $b->getMentionTexte()),
                'composantePorteuse' => (string) $b->getComposantePorteuse()?->getLibelle(),
                'typeDiplome' => (string) $b->getTypeDiplome()?->getLibelle(),
                default => (string) $b->getDisplay(),
            };

            $cmp = strcasecmp($valA, $valB);
            return $direction === 'desc' ? -$cmp : $cmp;
        });

        return $filtered;
    }
}
