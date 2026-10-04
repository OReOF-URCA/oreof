<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/CalculStructureParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/10/2023 13:19
 */

namespace App\TypeDiplome\Diplomes\M2E\Services;

use App\DTO\StructureEc;
use App\DTO\StructureEcVersioning;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\Repository\ElementConstitutifCopyRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\ParcoursCopyRepository;
use App\Repository\ParcoursRepository;
use App\Repository\UeCopyRepository;
use App\Repository\UeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CalculStructureParcoursM2e
{
    public function __construct(

        protected EntityManagerInterface                                        $entityManager,
        #[Autowire(expression: 'service("App\\\Repository\\\ElementConstitutifRepository")')]
        protected ElementConstitutifRepository|ElementConstitutifCopyRepository $elementConstitutifRepository,
        #[Autowire(expression: 'service("App\\\Repository\\\UeRepository")')]
        protected UeRepository|UeCopyRepository                                 $ueRepository,
        protected ParcoursRepository|ParcoursCopyRepository|null                $parcoursRepository = null,
    )
    {
    }

    public function calcul(Parcours $parcours, bool $withEcts = true, bool $withBcc = true, bool $dataFromFicheMatiere = true): StructureParcours
    {
        if ($this->parcoursRepository !== null) {
            $parcours = $this->parcoursRepository->find($parcours->getId());
        }

        $dtoStructure = StructureParcours::fromEntity($parcours, $withEcts, $withBcc);

        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $sem = $semestreParcours->getSemestre();
            $semRaccroche = $sem?->getSemestreRaccroche();
            if ($semRaccroche !== null) {
                $semestre = $semRaccroche->getSemestre();
                $raccrocheSemestre = true;
            } else {
                $semestre = $sem;
                $raccrocheSemestre = false;
            }

            if ($semestre !== null && $semestre->isNonDispense() === false && $semestreParcours->isOuvert() === true) {
                $dtoSemestre = new StructureSemestre($semestre, $semestreParcours->getOrdre(), $raccrocheSemestre, $semestreParcours, $withEcts, $withBcc);
                $ues = $this->ueRepository->getBySemestre($semestre);
                foreach ($ues as $ue) {
                    if ($ue !== null && $ue->getUeParent() === null) {
                        $display = $ue->display($parcours);
                        $ueOrigine = $ue;
                        if ($ue->getUeRaccrochee() !== null) {
                            $ue = $ue->getUeRaccrochee()->getUe();
                            $raccrocheUe = true;
                        } else {
                            $raccrocheUe = $raccrocheSemestre;
                        }

                        //si des UE enfants, on ne regarde pas s'il y a des EC
                        $dtoUe = new StructureUe($ue, $raccrocheUe, $display, $ueOrigine, $withEcts, $withBcc);
                        $ecs = $this->elementConstitutifRepository->getByUe($ue);

                        foreach ($ecs as $elementConstitutif) {
                            if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                //récupérer le bon EC selon tous les liens
                                $dtoEc = new StructureEc($elementConstitutif, $parcours, false, $withEcts, $withBcc, $dataFromFicheMatiere);
                                $dtoStructure->statsFichesMatieresParcours->addEc($elementConstitutif, $raccrocheUe);
                                foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                    $dtoEcEnfant = new StructureEc($elementConstitutifEnfant, $parcours, false, $withEcts, $withBcc, $dataFromFicheMatiere);
                                    $dtoStructure->statsFichesMatieresParcours->addEc($elementConstitutifEnfant, $raccrocheUe);
                                    $dtoEc->addEcEnfant($elementConstitutifEnfant->getOrdre(), $dtoEcEnfant);
                                }
                                $dtoUe->addEc($dtoEc);
                            }
                        }

                        foreach ($ue->getUeEnfants() as $ueEnfant) {
                            $display = $ueEnfant->display($parcours);
                            $ueOrigineEnfant = $ueEnfant;
                            if ($ueEnfant !== null && $ueEnfant->getUeRaccrochee() !== null) {
                                $ueEnfant = $ueEnfant->getUeRaccrochee()->getUe();
                                $raccrocheUeEnfant = true;
                            } else {
                                $raccrocheUeEnfant = $raccrocheUe;
                            }

                            if ($ueEnfant !== null) {
                                $dtoUeEnfant = new StructureUe($ueEnfant, $raccrocheUeEnfant, $display, $ueOrigineEnfant ?? null, $withEcts, $withBcc);
                                $ecsUeEnfant = $this->elementConstitutifRepository->getByUe($ueEnfant);
                                foreach ($ecsUeEnfant as $elementConstitutif) {
                                    if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                        $dtoEc = new StructureEc($elementConstitutif, $parcours, false, $withEcts, $withBcc, $dataFromFicheMatiere);
                                        $dtoStructure->statsFichesMatieresParcours->addEc($elementConstitutif, $raccrocheUeEnfant);
                                        foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                            $dtoEcEnfant = new StructureEc($elementConstitutifEnfant, $parcours, false, $withEcts, $withBcc, $dataFromFicheMatiere);
                                            $dtoStructure->statsFichesMatieresParcours->addEc($elementConstitutifEnfant, $raccrocheUeEnfant);
                                            $dtoEc->addEcEnfant($elementConstitutifEnfant->getOrdre(), $dtoEcEnfant);
                                        }
                                        $dtoUeEnfant->addEc($dtoEc);
                                    }
                                }

                                // -------------------------------
                                // Deuxieme niveau d'UE enfant $ueEnfant
                                // -------------------------------
                                foreach ($ueEnfant->getUeEnfants() as $ueEnfant2) {
                                    $display2 = $ueEnfant2->display($parcours, subsubniveau: true, subniveau: $display);
                                    $ueOrigineEnfant2 = $ueEnfant2;
                                    if ($ueEnfant2 !== null && $ueEnfant2->getUeRaccrochee() !== null) {
                                        $ueEnfant2 = $ueEnfant2->getUeRaccrochee()->getUe();
                                        $raccrocheUeEnfant2 = true;
                                    } else {
                                        $raccrocheUeEnfant2 = $raccrocheUeEnfant;
                                    }

                                    if ($ueEnfant2 !== null) {
                                        $dtoUeEnfant2 = new StructureUe($ueEnfant2, $raccrocheUeEnfant2, $display2, $ueOrigineEnfant2 ?? null, $withEcts, $withBcc);
                                        $ecsUeEnfant2 = $this->elementConstitutifRepository->getByUe($ueEnfant2);
                                        foreach ($ecsUeEnfant2 as $elementConstitutif) {
                                            if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                                $dtoEc = new StructureEc($elementConstitutif, $parcours, false, $withEcts, $withBcc, $dataFromFicheMatiere);
                                                $dtoStructure->statsFichesMatieresParcours->addEc($elementConstitutif, $raccrocheUeEnfant2);
                                                foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                                    $dtoEcEnfant = new StructureEc($elementConstitutifEnfant, $parcours, false, $withEcts, $withBcc, $dataFromFicheMatiere);
                                                    $dtoStructure->statsFichesMatieresParcours->addEc($elementConstitutifEnfant, $raccrocheUeEnfant2);
                                                    $dtoEc->addEcEnfant($elementConstitutifEnfant->getOrdre(), $dtoEcEnfant);
                                                }
                                                $dtoUeEnfant2->addEc($dtoEc);
                                            }
                                        }
                                        $dtoUeEnfant->addUeEnfant($ueOrigineEnfant2->getOrdre(), $dtoUeEnfant2);
                                    }
                                }

                                $dtoUe->addUeEnfant($ueOrigineEnfant->getOrdre(), $dtoUeEnfant);
                            }
                        }
                        $dtoSemestre->addUe($ueOrigine->getOrdre(), $dtoUe);
                    }
                }
                $dtoStructure->addSemestre($semestreParcours->getOrdre(), $dtoSemestre);
            }
        }

        return $dtoStructure;
    }

    public function calculVersioning(Parcours $parcours): StructureParcours
    {
        $dtoStructure = StructureParcours::fromEntity($parcours);

        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $sem = $semestreParcours->getSemestre();
            $semRaccroche = $sem?->getSemestreRaccroche();
            if ($semRaccroche !== null) {
                $semestre = $semRaccroche->getSemestre();
                $raccrocheSemestre = true;
            } else {
                $semestre = $sem;
                $raccrocheSemestre = false;
            }

            if ($semestre !== null && $semestre->isNonDispense() === false) {
                $dtoSemestre = new StructureSemestre($semestre, $semestreParcours->getOrdre(), $raccrocheSemestre, $semestreParcours);

                foreach ($semestre->getUes() as $ue) {
                    if ($ue !== null && $ue->getUeParent() === null) {
                        $display = $ue->display($parcours);
                        $ueOrigine = $ue;
                        if ($ue->getUeRaccrochee() !== null) {

                            $ue = $ue->getUeRaccrochee()->getUe();
                            $raccrocheUe = true;
                        } else {
                            $raccrocheUe = $raccrocheSemestre;
                        }

                        //si des UE enfants, on ne regarde pas s'il y a des EC
                        $dtoUe = new StructureUe($ue, $raccrocheUe, $display, $ueOrigine ?? null);
                        // $ecs = $this->elementConstitutifRepository->getByUe($ue);

                        foreach ($ue->getElementConstitutifs() as $elementConstitutif) {
                            if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                //récupérer le bon EC selon tous les liens
                                $dtoEc = new StructureEcVersioning($elementConstitutif, $parcours);
                                foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                    $dtoEcEnfant = new StructureEcVersioning($elementConstitutifEnfant, $parcours);
                                    $dtoEc->addEcEnfant($elementConstitutifEnfant->getOrdre(), $dtoEcEnfant);
                                }
                                $dtoUe->addEc($dtoEc);
                            }
                        }

                        foreach ($ue->getUeEnfants() as $ueEnfant) {
                            $display = $ueEnfant->display($parcours);
                            if ($ueEnfant !== null && $ueEnfant->getUeRaccrochee() !== null) {
                                $ueOrigine = $ueEnfant;
                                $ueEnfant = $ueEnfant->getUeRaccrochee()->getUe();
                                $raccrocheUeEnfant = true;
                            } else {
                                $raccrocheUeEnfant = $raccrocheUe;
                            }

                            if ($ueEnfant !== null) {
                                $dtoUeEnfant = new StructureUe($ueEnfant, $raccrocheUeEnfant, $display, $ueOrigine ?? null);
                                //$ecsEnfant = $this->elementConstitutifRepository->getByUe($ueEnfant);

                                foreach ($ueEnfant->getElementConstitutifs() as $elementConstitutif) {
                                    if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                        $dtoEc = new StructureEcVersioning($elementConstitutif, $parcours);

                                        foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                            $dtoEcEnfant = new StructureEcVersioning($elementConstitutifEnfant, $parcours);
                                            $dtoEc->addEcEnfant($elementConstitutifEnfant->getOrdre(), $dtoEcEnfant);
                                        }
                                        $dtoUeEnfant->addEc($dtoEc);
                                    }
                                }
                                $dtoUe->addUeEnfant($ueEnfant->getOrdre(), $dtoUeEnfant);
                            }
                        }
                        $dtoSemestre->addUe($ueOrigine->getOrdre(), $dtoUe);
                    }
                }
                $dtoStructure->addSemestre($semestreParcours->getOrdre(), $dtoSemestre);
            }
        }

        return $dtoStructure;
    }

}
