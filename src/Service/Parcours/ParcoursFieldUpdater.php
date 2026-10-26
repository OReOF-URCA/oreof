<?php

namespace App\Service\Parcours;

use App\Classes\LdapImporter;
use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\RegimeInscriptionEnum;
use App\Events\AddCentreParcoursEvent;
use App\Repository\ProfilRepository;
use App\Repository\VilleRepository;
use App\Repository\UserRepository;
use App\Repository\RythmeFormationRepository;
use App\Repository\ComposanteRepository;
use App\Service\AbstractFieldUpdater;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ParcoursFieldUpdater extends AbstractFieldUpdater
{
    public function __construct(
        private readonly UserRepository            $userRepo,
        private readonly VilleRepository           $villeRepo,
        private readonly RythmeFormationRepository $rythmeRepo,
        private readonly ComposanteRepository      $composanteRepo,
        private readonly LdapImporter              $ldapImporter,
        private readonly ProfilRepository          $profilRepository,
        private readonly EventDispatcherInterface  $eventDispatcher
    )
    {
    }

    public function applyForTab(Parcours $parcours, string $tabKey, string $field, mixed $value): void
    {
        $allowed = $this->allowedFieldsByTab();
        if (!\in_array($field, $allowed[$tabKey] ?? [], true)) {
            throw new \InvalidArgumentException("Field not allowed for tab $tabKey: $field");
        }

        $handlers = $this->handlers();
        if (!isset($handlers[$field])) {
            throw new \InvalidArgumentException('Field not supported for autosave: ' . $field);
        }

        $handlers[$field]($parcours, $value);
    }

    /** @return array<string, callable(Parcours, mixed): void> */
    private function handlers(): array
    {
        return [
            // ----------------- STEP 1 (presentation) -----------------
            'parcours_step1[respParcours][entity]' => function (Parcours $p, $v) {
                $p->setRespParcours($this->addFromEntity($v, 'ROLE_RESP_PARCOURS', $p));
            },
            'parcours_step1[coResponsable][entity]' => function (Parcours $p, $v) {
                $p->setCoResponsable($this->addFromEntity($v, 'ROLE_CO_RESP_PARCOURS', $p));
            },
            'parcours_step1[objectifsParcours]' => fn(Parcours $p, $v) => $p->setObjectifsParcours($this->toString($v)),
            'parcours_step1[motsCles]' => fn(Parcours $p, $v) => $p->setMotsCles($this->toString($v)),
            'parcours_step1[resultatsAttendus]' => fn(Parcours $p, $v) => $p->setResultatsAttendus($this->toString($v)),
            'parcours_step1[contenuFormation]' => fn(Parcours $p, $v) => $p->setContenuFormation($this->toString($v)),
            'parcours_step1[rythmeFormation]' => function (Parcours $p, $v) {
                $p->setRythmeFormation($this->toEntity($this->rythmeRepo, $v));
            },
            'parcours_step1[rythmeFormationTexte]' => fn(Parcours $p, $v) => $p->setRythmeFormationTexte($this->toString($v)),
            'parcours_step1[localisation]' => function (Parcours $p, $v) {
                $p->setLocalisation($this->toEntity($this->villeRepo, $v));
            },
            'parcours_step1[coResponsable][new]' => fn(Parcours $p, $v) => $p->setCoResponsable($this->addFromLdap($v, 'ROLE_CO_RESP_PARCOURS', $p)),
            'parcours_step1[respParcours][new]' => fn(Parcours $p, $v) => $p->setRespParcours($this->addFromLdap($v, 'ROLE_RESP_PARCOURS', $p)),


            // ----------------- STEP 2 (descriptif) -----------------
            // EnumType : si ton setter attend un enum, adapte via toEnum()
            'parcours_step2[modalitesEnseignement]' => function (Parcours $p, $v) {
                $p->setModalitesEnseignementValue($v === '' || $v === null ? null : (string) $v);
            },
            // YesNoType -> bool|null
            'parcours_step2[hasStage]' => fn(Parcours $p, $v) => $p->setHasStage($this->toBoolOrNull($v)),
            'parcours_step2[stageText]' => fn(Parcours $p, $v) => $p->setStageText($this->toString($v)),
            'parcours_step2[nbHeuresStages]' => fn(Parcours $p, $v) => $p->setNbHeuresStages($this->toFloatOrNull($v)),

            'parcours_step2[hasProjet]' => fn(Parcours $p, $v) => $p->setHasProjet($this->toBoolOrNull($v)),
            'parcours_step2[projetText]' => fn(Parcours $p, $v) => $p->setProjetText($this->toString($v)),
            'parcours_step2[nbHeuresProjet]' => fn(Parcours $p, $v) => $p->setNbHeuresProjet($this->toFloatOrNull($v)),

            'parcours_step2[hasMemoire]' => fn(Parcours $p, $v) => $p->setHasMemoire($this->toBoolOrNull($v)),
            'parcours_step2[memoireText]' => fn(Parcours $p, $v) => $p->setMemoireText($this->toString($v)),

            'parcours_step2[hasSituationPro]' => fn(Parcours $p, $v) => $p->setHasSituationPro($this->toBoolOrNull($v)),
            'parcours_step2[nbHeuresSituationPro]' => fn(Parcours $p, $v) => $p->setNbHeuresSituationPro($this->toFloatOrNull($v)),
            'parcours_step2[situationProText]' => fn(Parcours $p, $v) => $p->setSituationProText($this->toString($v)),

            // ----------------- STEP 3 (Structure maquette) -----------------
            'parcours_step3[semestreDebut]' => fn(Parcours $p, $v) => $p->setSemestreDebut($this->toIntOrNull($v)),
            'parcours_step3[semestreFin]' => fn(Parcours $p, $v) => $p->setSemestreFin($this->toIntOrNull($v)),

            // ----------------- STEP 5 (admission) -----------------
            'parcours_step5[niveauFrancais]' => function (Parcours $p, $v) {
                $p->setNiveauFrancaisValue($v !== null && $v !== '' ? (string) $v : null);
            },
            'parcours_step5[prerequis]' => fn(Parcours $p, $v) => $p->setPrerequis($this->toString($v)),
            'parcours_step5[composanteInscription]' => function (Parcours $p, $v) {
                $p->setComposanteInscription($this->toEntity($this->composanteRepo, $v));
            },
            // regimeInscription[] : array de valeurs (checkboxes)
            'parcours_step5[regimeInscription][]' => function (Parcours $p, $v) {
                $p->setRegimeInscription(\App\Referentiel\RegimeInscription::normalize((array) $v));
            },
            'parcours_step5[modalitesAlternance]' => fn(Parcours $p, $v) => $p->setModalitesAlternance($this->toString($v)),
            'parcours_step5[coordSecretariat]' => fn(Parcours $p, $v) => $p->setCoordSecretariat($this->toString($v)),
            //'parcours_step5[modalitesAdmission]' => fn(Parcours $p, $v) => $p->setModalitesAdmission($this->toString($v)),

            // ----------------- STEP 6 (et_apres) -----------------
            'parcours_step6[poursuitesEtudes]' => fn(Parcours $p, $v) => $p->setPoursuitesEtudes($this->toString($v)),
            'parcours_step6[debouches]' => fn(Parcours $p, $v) => $p->setDebouches($this->toString($v)),
            'parcours_step6[codesRome]' => fn(Parcours $p, $v) => $p->setCodesRome($this->toRomeCodes($v)),

            // ----------------- STEP 7 (configuration) -----------------
            'parcours_step7[descriptifHautPageAutomatique]' => fn(Parcours $p, $v) => $p->setDescriptifHautPageAutomatique($this->toString($v)),
            'parcours_step7[descriptifHautPage]' => fn(Parcours $p, $v) => $p->setDescriptifHautPage($this->toString($v)),
            'parcours_step7[descriptifBasPage]' => fn(Parcours $p, $v) => $p->setDescriptifBasPage($this->toString($v)),
            'parcours_step7[codeRNCP]' => fn(Parcours $p, $v) => $p->setCodeRNCP($this->toString($v)),
        ];
    }

    /** @return array<string, string[]> */
    private function allowedFieldsByTab(): array
    {
        return [
            'presentation' => [
                'parcours_step1[respParcours][entity]',
                'parcours_step1[coResponsable][entity]',
                'parcours_step1[objectifsParcours]',
                'parcours_step1[motsCles]',
                'parcours_step1[resultatsAttendus]',
                'parcours_step1[contenuFormation]',
                'parcours_step1[rythmeFormation]',
                'parcours_step1[rythmeFormationTexte]',
                'parcours_step1[localisation]',
                'parcours_step1[coResponsable][new]',
                'parcours_step1[respParcours][new]'
            ],
            'descriptif' => [
                'parcours_step2[modalitesEnseignement]',
                'parcours_step2[hasStage]',
                'parcours_step2[stageText]',
                'parcours_step2[nbHeuresStages]',
                'parcours_step2[hasProjet]',
                'parcours_step2[projetText]',
                'parcours_step2[nbHeuresProjet]',
                'parcours_step2[hasMemoire]',
                'parcours_step2[memoireText]',
                'parcours_step2[hasSituationPro]',
                'parcours_step2[nbHeuresSituationPro]',
                'parcours_step2[situationProText]',
            ],
            'maquette' => [
                'parcours_step3[semestreDebut]',
                'parcours_step3[semestreFin]',
            ],
            'admission' => [
                'parcours_step5[niveauFrancais]',
                'parcours_step5[prerequis]',
                'parcours_step5[composanteInscription]',
                'parcours_step5[regimeInscription][]',
                'parcours_step5[modalitesAlternance]',
                'parcours_step5[coordSecretariat]',
                'parcours_step5[modalitesAdmission]',
            ],
            'et_apres' => [
                'parcours_step6[poursuitesEtudes]',
                'parcours_step6[debouches]',
                'parcours_step6[codesRome]',
            ],
            'configuration' => [
                'parcours_step7[descriptifHautPageAutomatique]',
                'parcours_step7[descriptifHautPage]',
                'parcours_step7[descriptifBasPage]',
                'parcours_step7[codeRNCP]'
            ],
        ];
    }

    private function addFromLdap(string $email, string $codeProfil, Parcours $parcours): ?User
    {
        $user = $this->ldapImporter->addFromLdap($email);

        if (!$user) {
            return null;
        }

        $this->updateProfil($user, $codeProfil, $parcours);
        return $user;
    }

    private function addFromEntity(mixed $v, string $codeProfil, Parcours $parcours): ?User
    {
        $user = $this->toEntity($this->userRepo, $v);

        if (!$user) {
            return null;
        }

        $this->updateProfil($user, $codeProfil, $parcours);
        return $user;
    }

    private function updateProfil(User $user, string $codeProfil, Parcours $parcours): void
    {
        $campagneCollecte = $parcours->getDpeParcours()->first()->getCampagneCollecte();
        //ajouter des droits.
        //On retire les anciens
        switch ($codeProfil) {
            case 'ROLE_CO_RESP_PARCOURS':
                $profil = $this->profilRepository->findOneBy(['code' => $codeProfil]);
                if (null === $profil) {
                    throw new \InvalidArgumentException('Profil ' . $codeProfil . ' not found');
                }
                if ($parcours->getCoResponsable() !== null) {
                    $event = new AddCentreParcoursEvent($parcours, $parcours->getCoResponsable(), $profil, $campagneCollecte);
                    $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);
                }
                $event = new AddCentreParcoursEvent($parcours, $user, $profil, $campagneCollecte);
                $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);

                break;
            case 'ROLE_RESP_PARCOURS':
                $profil = $this->profilRepository->findOneBy(['code' => $codeProfil]);
                if (null === $profil) {
                    throw new \InvalidArgumentException('Profil ' . $codeProfil . ' not found');
                }
                if ($parcours->getRespParcours() !== null) {
                    $event = new AddCentreParcoursEvent($parcours, $parcours->getRespParcours(), $profil, $campagneCollecte);
                    $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);
                }
                $event = new AddCentreParcoursEvent($parcours, $user, $profil, $campagneCollecte);
                $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);

                break;
        }
    }

    private function toRomeCodes(mixed $v): array
    {
        $rawCodes = $this->toArray($v);
        $unique = [];

        foreach ($rawCodes as $rawCode) {
            $code = strtoupper(trim((string)$rawCode));
            if ($code === '' || preg_match('/^[A-Z]\d{4}$/', $code) !== 1) {
                continue;
            }
            $unique[$code] = ['code' => $code];
        }

        return array_values($unique);
    }
}
