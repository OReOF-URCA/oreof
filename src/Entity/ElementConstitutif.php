<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/ElementConstitutif.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Entity;

use App\Classes\GetElementConstitutif;
use App\Entity\Traits\HasBeenEditedTrait;
use App\Entity\Traits\LifeCycleTrait;
use App\Entity\Traits\ValidatableTrait;
use App\Enums\ModaliteEnseignementEnum;
use App\Repository\ElementConstitutifRepository;
use App\Service\McccCompletionCheckerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: ElementConstitutifRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ElementConstitutif implements McccCompletionCheckerInterface
{
    use LifeCycleTrait;
    use HasBeenEditedTrait;
    use ValidatableTrait;

    #[Groups(['fiche_matiere_versioning_ec_parcours', 'DTO_json_versioning'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 30, nullable: true, enumType: ModaliteEnseignementEnum::class)]
    private ?ModaliteEnseignementEnum $modaliteEnseignement = null;

    #[ORM\Column(nullable: true)]
    private ?float $ects;

    #[ORM\Column(nullable: true)]
    private ?float $volumeCmPresentiel;

    #[ORM\Column(nullable: true)]
    private ?float $volumeTdPresentiel;

    #[ORM\Column(nullable: true)]
    private ?float $volumeTpPresentiel;

    #[ORM\Column(nullable: true)]
    private ?float $volumeCmDistanciel;

    #[ORM\Column(nullable: true)]
    private ?float $volumeTdDistanciel;

    #[ORM\Column(nullable: true)]
    private ?float $volumeTpDistanciel;

    #[ORM\Column(nullable: true)]
    private ?bool $isCmPresentielMutualise;

    #[ORM\Column(nullable: true)]
    private ?bool $isTdPresentielMutualise;

    #[ORM\Column(nullable: true)]
    private ?bool $isTpPresentielMutualise;

    #[ORM\Column(nullable: true)]
    private ?bool $isCmDistancielMutualise;

    #[ORM\Column(nullable: true)]
    private ?bool $isTdDistancielMutualise;

    #[ORM\Column(nullable: true)]
    private ?bool $isTpDistancielMutualise;

    #[Groups('DTO_json_versioning')]
    #[ORM\ManyToOne(cascade: ['persist'])]
    private ?NatureUeEc $natureUeEc = null;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\OneToMany(mappedBy: 'ec', targetEntity: Mccc::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $mcccs;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Column(length: 15)]
    private ?string $code = null;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Column]
    private ?int $ordre = null;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER', inversedBy: 'elementConstitutifs')]
    private ?FicheMatiere $ficheMatiere = null;

    #[Groups(['fiche_matiere_versioning_ec_parcours', 'DTO_json_versioning'])]
    #[ORM\ManyToOne(inversedBy: 'elementConstitutifs')]
    private ?Parcours $parcours = null;

    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER', inversedBy: 'elementConstitutifs')]
    private ?Ue $ue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $texteEcLibre = null;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER', inversedBy: 'elementConstitutifs')]
    private ?TypeEc $typeEc = null;

    #[MaxDepth(1)]
    #[Groups(['DTO_json_versioning'])]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'ecEnfants')]
    private ?self $ecParent = null;

    #[ORM\OneToMany(mappedBy: 'ecParent', targetEntity: self::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $ecEnfants;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $typeMccc = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etatMccc = null;

    #[ORM\Column(nullable: true)]
    private ?float $volumeTe = null;

    #[ORM\Column]
    private ?bool $mcccEnfantsIdentique = false;

    #[ORM\Column]
    private ?bool $heuresEnfantsIdentiques = false;

    #[Groups('DTO_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?bool $quitus = false;

    #[Groups('DTO_json_versioning')]
    #[ORM\ManyToMany(targetEntity: Competence::class, inversedBy: 'elementConstitutifs')]
    private Collection $competences;

    #[ORM\Column(nullable: true)]
    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    private ?bool $synchroMccc = null;

    #[ORM\Column(nullable: true)]
    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    private ?bool $synchroHeures = null;

    #[ORM\Column(nullable: true)]
    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    private ?bool $synchroBcc = null;

    #[ORM\Column(nullable: true)]
    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    private ?bool $synchroEcts = null;

    #[ORM\Column(nullable: true)]
    #[Groups('DTO_json_versioning')]
    private ?bool $sansHeure = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeApogee = null;

    #[ORM\Column(nullable: true)]
    private ?bool $heuresSpecifiques = null;

    #[Groups('DTO_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?bool $mccc_specifiques = null;

    #[ORM\Column(nullable: true)]
    private ?bool $ects_specifiques = null;

    #[ORM\OneToOne(targetEntity: self::class)]
    private ?self $ecOrigineCopie = null;


    private ?int $deserializedId = null;

    #[ORM\Column(nullable: true)]
    #[Groups('DTO_json_versioning')]
    private ?bool $controleAssiduite = null;

    #[Groups('DTO_json_versioning')]
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $quitusText = null;

    public function __construct()
    {
        $this->mcccs = new ArrayCollection();
        $this->ecEnfants = new ArrayCollection();
        $this->competences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModaliteEnseignement(): ?ModaliteEnseignementEnum
    {
        return $this->modaliteEnseignement;
    }

    public function setModaliteEnseignement(?ModaliteEnseignementEnum $modaliteEnseignement): self
    {
        $this->modaliteEnseignement = $modaliteEnseignement;

        return $this;
    }

    public function getParcours(): ?Parcours
    {
        return $this->parcours;
    }

    public function setParcours(?Parcours $parcours): self
    {
        $this->parcours = $parcours;

        return $this;
    }

    public function getEcts(): ?float
    {
        return $this->ects ?? 0;
    }

    public function setEcts(?float $ects): self
    {
        $this->ects = $ects;

        return $this;
    }

    public function getVolumeCmPresentiel(): ?float
    {
        return $this->volumeCmPresentiel ?? 0;
    }

    public function setVolumeCmPresentiel(float $volumeCmPresentiel): self
    {
        $this->volumeCmPresentiel = $volumeCmPresentiel;

        return $this;
    }

    public function getVolumeTdPresentiel(): ?float
    {
        return $this->volumeTdPresentiel ?? 0;
    }

    public function setVolumeTdPresentiel(float $volumeTdPresentiel): self
    {
        $this->volumeTdPresentiel = $volumeTdPresentiel;

        return $this;
    }

    public function getVolumeTpPresentiel(): ?float
    {
        return $this->volumeTpPresentiel ?? 0;
    }

    public function setVolumeTpPresentiel(float $volumeTpPresentiel): self
    {
        $this->volumeTpPresentiel = $volumeTpPresentiel;

        return $this;
    }

    public function getVolumeCmDistanciel(): ?float
    {
        return $this->volumeCmDistanciel ?? 0;
    }

    public function setVolumeCmDistanciel(float $volumeCmDistanciel): self
    {
        $this->volumeCmDistanciel = $volumeCmDistanciel;

        return $this;
    }

    public function getVolumeTdDistanciel(): ?float
    {
        return $this->volumeTdDistanciel ?? 0;
    }

    public function setVolumeTdDistanciel(float $volumeTdDistanciel): self
    {
        $this->volumeTdDistanciel = $volumeTdDistanciel;

        return $this;
    }

    public function getVolumeTpDistanciel(): ?float
    {
        return $this->volumeTpDistanciel ?? 0;
    }

    public function setVolumeTpDistanciel(float $volumeTpDistanciel): self
    {
        $this->volumeTpDistanciel = $volumeTpDistanciel;

        return $this;
    }

    public function etatStructure(): string
    {
        $cmPresentiel = $this->volumeCmPresentiel ?? 0.0;
        $tdPresentiel = $this->volumeTdPresentiel ?? 0.0;
        $tpPresentiel = $this->volumeTpPresentiel ?? 0.0;
        $cmDistanciel = $this->volumeCmDistanciel ?? 0.0;
        $tdDistanciel = $this->volumeTdDistanciel ?? 0.0;
        $tpDistanciel = $this->volumeTpDistanciel ?? 0.0;
        $volumeTe = $this->volumeTe ?? 0.0;

        $nbHeures = $cmPresentiel + $tdPresentiel + $tpPresentiel + $cmDistanciel + $tdDistanciel + $tpDistanciel + $volumeTe;

        if (($nbHeures === 0.0 && $this->isSansHeure() === false) && $this->modaliteEnseignement === null) {
            return 'À compléter';
        }

        if ($nbHeures === 0.0 && $this->isSansHeure() === false) {
            return 'Pas d\'heures';
        }

        if ($this->modaliteEnseignement === null) {
            return 'Volumes horaires non renseignés';
        }

        return 'Complet';
    }

    public function isIsCmPresentielMutualise(): ?bool
    {
        return $this->isCmPresentielMutualise;
    }

    public function setIsCmPresentielMutualise(bool $isCmPresentielMutualise): self
    {
        $this->isCmPresentielMutualise = $isCmPresentielMutualise;

        return $this;
    }

    public function isIsTdPresentielMutualise(): ?bool
    {
        return $this->isTdPresentielMutualise;
    }

    public function setIsTdPresentielMutualise(bool $isTdPresentielMutualise): self
    {
        $this->isTdPresentielMutualise = $isTdPresentielMutualise;

        return $this;
    }

    public function isIsTpPresentielMutualise(): ?bool
    {
        return $this->isTpPresentielMutualise;
    }

    public function setIsTpPresentielMutualise(bool $isTpPresentielMutualise): self
    {
        $this->isTpPresentielMutualise = $isTpPresentielMutualise;

        return $this;
    }

    public function isIsCmDistancielMutualise(): ?bool
    {
        return $this->isCmDistancielMutualise;
    }

    public function setIsCmDistancielMutualise(bool $isCmDistancielMutualise): self
    {
        $this->isCmDistancielMutualise = $isCmDistancielMutualise;

        return $this;
    }

    public function isIsTdDistancielMutualise(): ?bool
    {
        return $this->isTdDistancielMutualise;
    }

    public function setIsTdDistancielMutualise(bool $isTdDistancielMutualise): self
    {
        $this->isTdDistancielMutualise = $isTdDistancielMutualise;

        return $this;
    }

    public function isIsTpDistancielMutualise(): ?bool
    {
        return $this->isTpDistancielMutualise;
    }

    public function setIsTpDistancielMutualise(bool $isTpDistancielMutualise): self
    {
        $this->isTpDistancielMutualise = $isTpDistancielMutualise;

        return $this;
    }

    public function getNatureUeEc(): ?NatureUeEc
    {
        return $this->natureUeEc;
    }

    public function setNatureUeEc(?NatureUeEc $natureUeEc): self
    {
        $this->natureUeEc = $natureUeEc;

        return $this;
    }

    /**
     * @return Collection<int, Mccc>
     */
    public function getMcccs(): Collection
    {
        return $this->mcccs;
    }

    public function addMccc(Mccc $mccc): self
    {
        if (!$this->mcccs->contains($mccc)) {
            $this->mcccs->add($mccc);
            $mccc->setEc($this);
        }

        return $this;
    }

    public function removeMccc(Mccc $mccc): self
    {
        // set the owning side to null (unless already changed)
        if ($this->mcccs->removeElement($mccc) && $mccc->getEc() === $this) {
            $mccc->setEc(null);
        }

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function genereCode(): void
    {
        if ($this->ecParent === null) {
            $this->setCode('EC ' . $this->ordre);
            //numéroter les enfants
            foreach ($this->ecEnfants as $ecEnfant) {
                $ecEnfant->genereCode();
            }
        } else {
            $this->setCode('EC ' . $this->ecParent->getOrdre() . '.' . chr($this->ordre + 64));
        }
    }

    public function display(): string
    {
        if ($this->ficheMatiere !== null) {
            return $this->ficheMatiere->getLibelle();
        }

        if ($this->libelle !== null) {
            return $this->libelle;
        }

        return $this->texteEcLibre ?? 'Aucun libellé';
    }

    public function getFicheMatiere(): ?FicheMatiere
    {
        return $this->ficheMatiere;
    }

    public function setFicheMatiere(?FicheMatiere $ficheMatiere): self
    {
        $this->ficheMatiere = $ficheMatiere;

        return $this;
    }

    public function getUe(): ?Ue
    {
        return $this->ue;
    }

    public function setUe(?Ue $ue): self
    {
        $this->ue = $ue;

        return $this;
    }

    public function getTexteEcLibre(): ?string
    {
        return $this->texteEcLibre;
    }

    public function setTexteEcLibre(?string $texteEcLibre): self
    {
        $this->texteEcLibre = $texteEcLibre;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getTypeEc(): ?TypeEc
    {
        return $this->typeEc;
    }

    public function setTypeEc(?TypeEc $typeEc): self
    {
        $this->typeEc = $typeEc;

        return $this;
    }

    public function getEcParent(): ?self
    {
        return $this->ecParent;
    }

    public function setEcParent(?self $ecParent): self
    {
        $this->ecParent = $ecParent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getEcEnfants(): Collection
    {
        return $this->ecEnfants;
    }

    public function addEcEnfant(self $ecEnfant): self
    {
        if (!$this->ecEnfants->contains($ecEnfant)) {
            $this->ecEnfants->add($ecEnfant);
            $ecEnfant->setEcParent($this);
        }

        return $this;
    }

    public function removeEcEnfant(self $ecEnfant): self
    {
        if ($this->ecEnfants->removeElement($ecEnfant)) {
            // set the owning side to null (unless already changed)
            if ($ecEnfant->getEcParent() === $this) {
                $ecEnfant->setEcParent(null);
            }
        }

        return $this;
    }

    public function getTypeMccc(): ?string
    {
        return $this->typeMccc;
    }

    public function setTypeMccc(?string $typeMccc): self
    {
        $this->typeMccc = $typeMccc;

        return $this;
    }

    public function getEtatMccc(): ?string
    {
        if ($this->natureUeEc !== null && $this->natureUeEc->isLibre() === true) {
            if (
                ($this->ects !== null && $this->ects > 0.0) ||
                ($this->ecParent !== null && $this->ecParent->getEcts() !== null && $this->ecParent->getEcts() > 0.0)
            ) {
                return 'Complet';
            }
            return 'A Saisir';
        }

        if ($this->mcccs->count() === 0 && $this->ficheMatiere?->getMcccs()->count() === 0) {
            return 'A Saisir';
        }

        if ($this->ficheMatiere !== null) {
            if ($this->ficheMatiere->isMcccImpose() === true &&
                $this->ficheMatiere->isEctsImpose() === true) {
                return $this->ficheMatiere->getEtatMccc();
            }
            if ($this->ficheMatiere->isMcccImpose() === true &&
                $this->ficheMatiere->isEctsImpose() === false) {
                return $this->getEcts() === null ? 'A Saisir' : $this->ficheMatiere->getEtatMccc();
            }

            if ($this->ficheMatiere->isMcccImpose() === false &&
                $this->ficheMatiere->isEctsImpose() === true) {
                return $this->ficheMatiere->getEcts() === null ? 'A Saisir' : $this->etatMccc;
            }
        }


        return $this->etatMccc === null ? 'A Saisir' : $this->etatMccc;
    }

    public function setEtatMccc(?string $etatMccc): static
    {
        $this->etatMccc = $etatMccc;

        return $this;
    }

    public function volumeTotalPresentiel(): float
    {
        return $this->volumeCmPresentiel + $this->volumeTdPresentiel + $this->volumeTpPresentiel;
    }

    public function volumeTotalDistanciel(): float
    {
        return $this->volumeCmDistanciel + $this->volumeTdDistanciel + $this->volumeTpDistanciel;
    }

    public function volumeTotal(): float
    {
        return $this->volumeTotalPresentiel() + $this->volumeTotalDistanciel();
    }

    public function getVolumeTe(): ?float
    {
        return $this->volumeTe;
    }

    public function setVolumeTe(?float $volumeTe): self
    {
        $this->volumeTe = $volumeTe;

        return $this;
    }

    public function isMcccEnfantsIdentique(): ?bool
    {
        return $this->mcccEnfantsIdentique ?? true;
    }

    public function setMcccEnfantsIdentique(bool $mcccEnfantsIdentique): self
    {
        $this->mcccEnfantsIdentique = $mcccEnfantsIdentique;

        return $this;
    }

    public function isHeuresEnfantsIdentiques(): ?bool
    {
        return $this->heuresEnfantsIdentiques ?? true;
    }

    public function setHeuresEnfantsIdentiques(bool $heuresEnfantsIdentiques): self
    {
        $this->heuresEnfantsIdentiques = $heuresEnfantsIdentiques;

        return $this;
    }

    public function isQuitus(): ?bool
    {
        return $this->quitus ?? false;
    }

    public function setQuitus(?bool $quitus): self
    {
        $this->quitus = $quitus;

        return $this;
    }

    public function getEtatBcc(Parcours $parcours): ?string
    {
        $raccroche = $this->getFicheMatiere()?->getParcours() !== $parcours;
        $getElement = new GetElementConstitutif($this, $parcours);
        $getElement->setIsRaccroche($raccroche);
        return $getElement->getEtatBcc();
    }

    public function isFicheFromParcours(): bool
    {
        return $this->getParcours()?->getId() === $this->ficheMatiere?->getParcours()?->getId();
    }

    /**
     * @return Collection<int, Competence>
     */
    public function getCompetences(): Collection
    {
        return $this->competences;
    }

    public function addCompetence(Competence $competence): static
    {
        if (!$this->competences->contains($competence)) {
            $this->competences->add($competence);
        }

        return $this;
    }

    public function removeCompetence(Competence $competence): static
    {
        $this->competences->removeElement($competence);

        return $this;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function isSynchroMccc(): ?bool
    {
        return $this->synchroMccc ?? false;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function setSynchroMccc(?bool $synchroMccc): static
    {
        $this->synchroMccc = $synchroMccc;

        return $this;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function isSynchroHeures(): ?bool
    {
        return $this->synchroHeures ?? false;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function setSynchroHeures(?bool $synchroHeures): static
    {
        $this->synchroHeures = $synchroHeures;

        return $this;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function isSynchroBcc(): ?bool
    {
        return $this->synchroBcc ?? false;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function setSynchroBcc(?bool $synchroBcc): static
    {
        $this->synchroBcc = $synchroBcc;

        return $this;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function isSynchroEcts(): ?bool
    {
        return $this->synchroEcts ?? false;
    }

    /** @deprecated("A supprimer une fois le transfert des données EC => Fiche") */
    public function setSynchroEcts(?bool $synchroEcts): static
    {
        $this->synchroEcts = $synchroEcts;

        return $this;
    }

    public function isSansHeure(): ?bool
    {
        return $this->sansHeure ?? false;
    }

    public function setSansHeure(?bool $sansHeure): static
    {
        $this->sansHeure = $sansHeure;

        return $this;
    }

    public function getCodeApogee(): ?string
    {
        if ($this->getNatureUeEc()?->isChoix()) {
            //if ($this->getUe()?->getUeParent() === null) {
                return $this->getUe()?->getCodeApogee() . $this->getOrdre();
            //}
        }

        return $this->codeApogee;
    }

    public function setCodeApogee(?string $codeApogee): static
    {
        $this->codeApogee = $codeApogee;

        return $this;
    }

    public function displayCodeApogee(): ?string
    {
        if ($this->codeApogee !== null) {
            return $this->getCodeApogee();
        }

        if ($this->ficheMatiere !== null) {
            return $this->ficheMatiere->getCodeApogee() ?? 'Aucun code Apogée';
        }

        return 'Aucun code Apogée';
    }

    public function getTypeApogee(): ?string
    {
        if ($this->ficheMatiere !== null) {
            return $this->ficheMatiere->getTypeApogee();
        }

        return '-';
    }

    public function displayId(): string
    {
        if ($this->codeApogee !== null) {
            return 'EC_'.$this->id;
        }

        if ($this->ficheMatiere !== null) {
            return 'FM_'.$this->ficheMatiere->getId() ?? '-err-';
        }

        return '-err-';
    }

    public function isHeuresSpecifiques(): ?bool
    {
        return $this->heuresSpecifiques ?? false;
    }

    public function setHeuresSpecifiques(?bool $heuresSpecifiques): static
    {
        $this->heuresSpecifiques = $heuresSpecifiques;

        return $this;
    }

    public function isMcccSpecifiques(): ?bool
    {
        return $this->mccc_specifiques ?? false;
    }

    public function setMcccSpecifiques(?bool $mccc_specifiques): static
    {
        $this->mccc_specifiques = $mccc_specifiques;

        return $this;
    }

    public function isEctsSpecifiques(): ?bool
    {
        return $this->ects_specifiques ?? false;
    }

    public function setEctsSpecifiques(?bool $ects_specifiques): static
    {
        $this->ects_specifiques = $ects_specifiques;

        return $this;
    }

    public function getEcOrigineCopie(): ?self
    {
        return $this->ecOrigineCopie;
    }

    public function setEcOrigineCopie(?self $ecOrigineCopie): static
    {
        $this->ecOrigineCopie = $ecOrigineCopie;

        return $this;
    }

    /**
     * Méthode appelée lors de la duplication de l'année universitaire
     */
    public function prepareCloneForNewAnnee() : void {
        $this->competences = new ArrayCollection();
    }


    public function getDeserializedId() : ?int {
        return $this->deserializedId;
    }

    public function setDeserializedId(?int $id) : self {
        $this->deserializedId = $id;

        return $this;
    }

    public function isControleAssiduite(): ?bool
    {
        return $this->controleAssiduite ?? false;
    }

    public function setControleAssiduite(?bool $controleAssiduite): static
    {
        $this->controleAssiduite = $controleAssiduite;

        return $this;
    }

    public function getQuitusText(): ?string
    {
        return $this->quitusText;
    }

    public function setQuitusText(?string $quitusText): static
    {
        $this->quitusText = $quitusText;

        return $this;
    }

    public function getHasQuitus(): bool
    {
        // si MCCC spécifiques alors le quitus est sur l'element constitutif, sinon sur la fiche matière
        if ($this->isMcccSpecifiques()) {
            return $this->quitus ?? false;
        }

        if ($this->ficheMatiere !== null) {
            return $this->ficheMatiere->isQuitus() ?? false;
        }

        return false;
    }

    //todo: inutile si passe par le DTO ?
    public function isHeuresComplete(): bool
    {
        if ($this->isHeuresSpecifiques() === true) {
            $etatHeuresComplet = $this->etatStructure() === 'Complet';
        } elseif ($this->getFicheMatiere() !== null) {
            $etatHeuresComplet = $this->getFicheMatiere()->etatStructure() === 'Complet';
        } else {
            $etatHeuresComplet = $this->etatStructure() === 'Complet';
        }

        if ($this->getEcParent() !== null && $this->getEcParent()->isHeuresEnfantsIdentiques() === true) {
            if ($this->getEcParent()->getFicheMatiere() !== null) {
                $etatHeuresComplet = $this->getEcParent()->getFicheMatiere()->etatStructure() === 'Complet';
            } else {
                $etatHeuresComplet = $this->getEcParent()->etatStructure() === 'Complet';
            }
        }

        if ($this->getEcParent() === null && $this->getNatureUeEc()?->isChoix() && $this->isHeuresEnfantsIdentiques() === false) {
            $etatHeuresComplet = true;
        }

        return $etatHeuresComplet;
    }

    public function isMcccComplete(Parcours $parcours): bool
    {
        if ($this->getNatureUeEc()?->isLibre()) {
            $etatMcccComplet = $this->getEcts() !== 0; // sur un EC libre, juste des ECTS
        } else {
            $getElement = new GetElementConstitutif($this, $parcours);
            $etatMcccComplet = $this->isControleAssiduite() === true || $getElement->getEtatsMccc() === 'Complet';
        }

        return $etatMcccComplet;
    }

}
