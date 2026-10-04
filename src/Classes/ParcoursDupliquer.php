<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/ParcoursDupliquer.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/09/2023 08:20
 */

namespace App\Classes;

use App\Entity\CampagneCollecte;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class ParcoursDupliquer
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function recopie(Parcours $parcours, CampagneCollecte $campagneCollecte): \Symfony\Component\HttpFoundation\Response
    {
        $formation = $parcours->getFormation();

        // on clone la structure du parcours
        $newParcours = clone $parcours;
        $newParcours->setLibelle($parcours->getLibelle() . ' (copie)');
        $newParcours->setParcoursOrigineCopie(null);
        $this->entityManager->persist($newParcours);

        $newDpe = new DpeParcours();
        $newDpe->setParcours($newParcours);
        $newDpe->setFormation($formation);
        $newDpe->setVersion('0.1');
        $newDpe->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
        $newDpe->setCreated(new DateTime());
        $newDpe->setCampagneCollecte($campagneCollecte);
        $newDpe->setEtatValidation(['en_cours_redaction' => 1]);
        $this->entityManager->persist($newDpe);

        $newDpeDemande = new DpeDemande();
        $newDpeDemande->setArgumentaireDemande('Création de la copie du parcours');
        $newDpeDemande->setNiveauDemande('P');
        $newDpeDemande->setCreated(new DateTime());
        $newDpeDemande->setCampagneCollecte($campagneCollecte);
        $newDpeDemande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
        $newDpeDemande->setParcours($newParcours);
        $newDpeDemande->setNiveauModification(TypeModificationDpeEnum::CREATION);
        $newDpeDemande->setDateDemande(new DateTime());
        $newDpeDemande->setAuteur($formation?->getResponsableMention());
        $newDpeDemande->setFormation($formation);

        $this->entityManager->persist($newDpeDemande);

        //recopie des contacts du parcours
        foreach($parcours->getContacts() as $contact) {
            $newContact = clone $contact;
            $newContact->setParcours($newParcours);
            $adresse = $contact->getAdresse();
            if (null !== $adresse) {
                $newAdresse = clone $adresse;
                $newAdresse->setAdresseOrigineCopie(null);
                $newContact->setAdresse($newAdresse);
            }
            $this->entityManager->persist($newContact);
        }

        $tabCompetences = [];

        //on duplique les blocs et les compétences
        foreach ($parcours->getBlocCompetences() as $bloc) {
            $newBloc = clone $bloc;
            $newBloc->setParcours($newParcours);
            $newBloc->setBlocCompetenceOrigineCopie(null);
            $this->entityManager->persist($newBloc);

            foreach ($bloc->getCompetences() as $competence) {
                $newCompetence = clone $competence;
                $newCompetence->setBlocCompetence($newBloc);
                $newCompetence->setCompetenceOrigineCopie(null);
                $tabCompetences[$competence->getCode()] = $newCompetence;
                $this->entityManager->persist($newCompetence);
            }
        }

        // on duplique les semestres
        foreach ($parcours->getSemestreParcours() as $sp) {
            //todo: revoir
            // if ($sp->getSemestre()?->isTroncCommun()) {
                //tronc commun, on duplique uniquement la liaison.
//                $newSp = clone $sp;
//                $newSp->setParcours($newParcours);
//                $this->entityManager->persist($newSp);
            //   } else {
                //Pas tronc commun, on duplique semestre, UE et EC
                $newSemestre = clone $sp->getSemestre();
                $newSemestre->setSemestreOrigineCopie(null);
                $this->entityManager->persist($newSemestre);
                $newSp = new SemestreParcours($newSemestre, $newParcours);
                $newSp->setOrdre($sp->getOrdre());
                $this->entityManager->persist($newSp);

                foreach ($sp->getSemestre()?->getUes() as $ue) {
                    if ($ue->getUeParent() === null) {
                        $newUe = clone $ue;
                        $newUe->setUeOrigineCopie(null);
                        $newUe->setSemestre($newSemestre);
                        $this->entityManager->persist($newUe);

                        if ($ue->getUeEnfants()->count() > 0) {
                            foreach ($ue->getUeEnfants() as $ueEnfant) {
                                $newUeEnfant = clone $ueEnfant;
                                $newUeEnfant->setUeOrigineCopie(null);
                                $newUeEnfant->setUeParent($newUe);
                                $newUeEnfant->setSemestre($newSemestre);
                                $this->entityManager->persist($newUeEnfant);

                                //recopie de la structure
                                $this->recopieContenuUe($ueEnfant, $newUeEnfant, $newParcours, $tabCompetences);
                            }
                        } else {
                            $this->recopieContenuUe($ue, $newUe, $newParcours, $tabCompetences);
                        }
                    }

                    //dupliquer les UE enfants et les EC associés
                    //   }
            }
        }

        $this->entityManager->flush();

        return JsonReponse::success('Le parcours a été dupliqué');
    }

    private function recopieContenuUe(Ue $ue, Ue $newUe, Parcours $newParcours, array $tabCompetences): void
    {
        //dupliquer les EC des ue
        foreach ($ue->getElementConstitutifs() as $ec) {
            if ($ec->getEcParent() === null) {
                $newEc = clone $ec;
                $newEc->setEcOrigineCopie(null);
                $newEc->setUe($newUe);
                $newEc->setParcours($newParcours);
                $this->entityManager->persist($newEc);

                //dupliquer les compétences sur l'EC
                foreach ($ec->getCompetences() as $competence) {
                    $newEc->addCompetence($tabCompetences[$competence->getCode()]);
                }

                //Dupliquer la fiche associée à l'EC
                if (null !== $ec->getFicheMatiere()) {
                    $newFiche = clone $ec->getFicheMatiere();
                    $newFiche->setFicheMatiereOrigineCopie(null);
                    $date = new DateTime();
                    $newFiche->setResponsableFicheMatiere(null);
                    $newFiche->setSlug($newFiche->getSlug() . '-' . $date->format('YmdHis'));
                    $newFiche->setParcours($newParcours);

                    foreach ($ec->getFicheMatiere()->getLangueDispense() as $langue) {
                        $newFiche->addLangueDispense($langue);
                    }

                    foreach ($ec->getFicheMatiere()->getLangueSupport() as $langue) {
                        $newFiche->addLangueSupport($langue);
                    }

                    $newEc->setFicheMatiere($newFiche);
                    $this->entityManager->persist($newFiche);

                    //le cas échéant dupliquer les MCCC de la fiche
                    foreach ($ec->getFicheMatiere()->getMcccs() as $mccc) {
                        $newMccc = clone $mccc;
                        $newFiche->addMccc($newMccc);
                        $this->entityManager->persist($newMccc);
                    }

                    //le cas échéant dupliquer les compétences de la fiche
                    foreach ($ec->getFicheMatiere()->getCompetences() as $competence) {
                        if (isset($tabCompetences[$competence->getCode()])) {
                            $newFiche->addCompetence($tabCompetences[$competence->getCode()]);
                        }
                    }
                }

                //dupliquer les MCCC sur les EC
                foreach ($ec->getMcccs() as $mccc) {
                    $newMccc = clone $mccc;
                    $newEc->addMccc($newMccc);
                    $this->entityManager->persist($newMccc);
                }

                // EC enfants
                foreach ($ec->getEcEnfants() as $ece) {
                    $newEce = clone $ece;
                    $newEce->setEcOrigineCopie(null);
                    $newEce->setUe($newUe);
                    $newEce->setParcours($newParcours);
                    $newEce->setEcParent($newEc);
                    $this->entityManager->persist($newEce);

                    //dupliquer les compétences sur l'EC
                    foreach ($ece->getCompetences() as $competence) {
                        $newEce->addCompetence($tabCompetences[$competence->getCode()]);
                    }

                    //Dupliquer la fiche associée à l'EC
                    if (null !== $ece->getFicheMatiere()) {
                        $newFiche = clone $ece->getFicheMatiere();
                        $newFiche->setFicheMatiereOrigineCopie(null);
                        $date = new DateTime();
                        $newFiche->setSlug($newFiche->getSlug() . '-' . $date->format('YmdHis'));
                        $newFiche->setParcours($newParcours);

                        foreach ($ece->getFicheMatiere()->getLangueDispense() as $langue) {
                            $newFiche->addLangueDispense($langue);
                        }

                        foreach ($ece->getFicheMatiere()->getLangueSupport() as $langue) {
                            $newFiche->addLangueSupport($langue);
                        }

                        $newEce->setFicheMatiere($newFiche);
                        $this->entityManager->persist($newFiche);

                        //le cas échéant dupliquer les MCCC de la fiche
                        foreach ($ece->getFicheMatiere()->getMcccs() as $mccc) {
                            $newMccc = clone $mccc;
                            $newFiche->addMccc($newMccc);
                            $this->entityManager->persist($newMccc);
                        }

                        //le cas échéant dupliquer les compétences de la fiche
                        foreach ($ece->getFicheMatiere()->getCompetences() as $competence) {
                            if (isset($tabCompetences[$competence->getCode()])) {
                                $newFiche->addCompetence($tabCompetences[$competence->getCode()]);
                            }
                        }
                    }

                    //dupliquer les MCCC sur les EC
                    foreach ($ece->getMcccs() as $mccc) {
                        $newMccc = clone $mccc;
                        $newEce->addMccc($newMccc);
                        $this->entityManager->persist($newMccc);
                    }
                }
            }
        }
    }
}
