<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Formation.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/03/2023 13:25
 */

namespace App\Entity;

use App\Classes\verif\FormationValide;
use App\DTO\Remplissage;
use App\Entity\Traits\LifeCycleTrait;
use App\Enums\NiveauFormationEnum;
use App\Enums\RegimeInscriptionEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\PseudoTypes\ArrayShapeItem;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Index(columns: ['sigle'], name: 'sigle_formation', flags: ['fulltext'])]
#[ORM\Index(columns: ['slug'], name: 'slug_formation', flags: ['fulltext'])]
#[ORM\HasLifecycleCallbacks]
class Formation
{
    use LifeCycleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne]
    /** @deprecated */
    private ?Domaine $domaine = null;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne(targetEntity: Composante::class, inversedBy: 'formationsPortees', fetch: 'EAGER')]
    private ?Composante $composantePorteuse = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne(cascade: ['persist'])]
    /** @deprecated("Sur le Dpe")  */
    private ?CampagneCollecte $dpe;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne(targetEntity: Mention::class, cascade: ['persist'], fetch: 'EAGER', inversedBy: 'formations')]
    private ?Mention $mention = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['formation:read', 'fiche_matiere_versioning', 'formation_json_versioning', 'parcours_json_versioning'])]
    private ?string $mentionTexte = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::INTEGER, enumType: NiveauFormationEnum::class)]
    private ?NiveauFormationEnum $niveauEntree = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::INTEGER, enumType: NiveauFormationEnum::class)]
    private ?NiveauFormationEnum $niveauSortie = null;

    #[ORM\Column]
    #[Groups(['formation:read', 'formation_json_versioning'])]
    private ?bool $inRncp = true;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeRNCP = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'formationsResponsableMention')]
    private ?User $responsableMention = null;

    #[Groups('formation_json_versioning')]
    #[ORM\Column]
    private ?int $semestreDebut = 1;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToMany(targetEntity: Ville::class, cascade: ['persist'])]
    private Collection $localisationMention;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToMany(targetEntity: Composante::class, inversedBy: 'formations', cascade: ['persist'])]
    private Collection $composantesInscription;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $regimeInscriptionTexte = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $modalitesAlternance = null;

    #[Groups('formation_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?array $regimeInscription = [];

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objectifsFormation = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenuFormation = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resultatsAttendus = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rythmeFormationTexte = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(nullable: true)]
    private ?bool $hasParcours = null;

    #[ORM\Column(nullable: true)]
    private ?array $structureSemestres = [];

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Parcours::class)]
    #[ORM\OrderBy(['libelle' => 'ASC'])]
    private Collection $parcours;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: BlocCompetence::class)]
    private Collection $blocCompetences;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne]
    private ?RythmeFormation $rythmeFormation = null;

    #[ORM\Column(nullable: true)]
    /** @deprecated("sur le DPE") */
    private ?array $etatDpe = [];

    #[ORM\Column]
    private ?array $etatSteps = [];

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'formations')]
    private ?TypeDiplome $typeDiplome = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['formation:read', 'fiche_matiere_versioning', 'formation_json_versioning', 'parcours_json_versioning'])]
    private ?string $sigle = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\ManyToOne(inversedBy: 'coFormations')]
    private ?User $coResponsable = null;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: TypeEc::class)]
    private Collection $typeEcs;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: ButCompetence::class)]
    private Collection $butCompetences;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: HistoriqueFormation::class)]
    #[ORM\OrderBy(['created' => 'DESC'])]
    private Collection $historiqueFormations;

    #[Groups('formation_json_versioning')]
    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['sigle'], unique: true)]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?array $remplissage = [];

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: CommentaireFormation::class)]
    private Collection $commentaires;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: DpeParcours::class)]
    private Collection $dpeParcours;

    #[ORM\Column(length: 1, nullable: true)]
    /** @deprecated  */
    private ?string $codeMentionApogee = null;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: FormationVersioning::class)]
    private Collection $formationVersionings;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: ChangeRf::class)]
    private Collection $changeRves;

    #[ORM\Column(length: 255, nullable: true, enumType: TypeModificationDpeEnum::class)]
    private ?TypeModificationDpeEnum $etatReconduction = null;

    /** @var Formation $formationOrigineCopie Référence la formation d'origine, depuis la copie */
    #[ORM\OneToOne(inversedBy: 'formationCopieAnneeUniversitaire', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $formationOrigineCopie = null;

    /** @var Formation $formationCopieAnneeUniversitaire Référence la formation copiée, depuis celle d'origine */
    #[ORM\OneToOne(mappedBy: 'formationOrigineCopie', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $formationCopieAnneeUniversitaire = null;

    /**
     * @var Collection<int, UserProfil>
     */
    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: UserProfil::class)]
    private Collection $userProfils;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: DpeDemande::class, cascade: ['persist'])]
    private Collection $dpeDemandes;

    #[ORM\Column]
    private ?int $capaciteAccueil = 0;

    /**
     * @var Collection<int, ChangeParcours>
     */
    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: ChangeParcours::class)]
    private Collection $changeParcours;
    public function __construct(?CampagneCollecte $anneeUniversitaire)
    {
        $this->dpe = $anneeUniversitaire;
        $this->localisationMention = new ArrayCollection();
        $this->parcours = new ArrayCollection();
        $this->composantesInscription = new ArrayCollection();
        $this->blocCompetences = new ArrayCollection();
        $this->dpeDemandes = new ArrayCollection();

        for ($i = 1; $i <= 3; $i++) {
            $this->etatSteps[$i] = false;
        }
        $this->typeEcs = new ArrayCollection();
        $this->butCompetences = new ArrayCollection();
        $this->historiqueFormations = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->dpeParcours = new ArrayCollection();
        $this->formationVersionings = new ArrayCollection();
        $this->changeRves = new ArrayCollection();
        $this->userProfils = new ArrayCollection();
        $this->changeParcours = new ArrayCollection();
    }

    #[ORM\PreFlush]
    public function updateSlug(): void
    {
        $texte = $this->getMention() === null ? $this->getMentionTexte() : $this->getMention()->getLibelle();
        $texte = ($this->getTypeDiplome() != null ? $this->getTypeDiplome()->getLibelleCourt() : '') . '-' . $texte . '-' . $this->getDpe()?->getAnneeUniversitaire()?->getAnnee();

        $this->setSlug($texte);
    }


    public function getEtatStep(int $step): bool
    {
        if (array_key_exists($step, $this->getEtatSteps())) {
            return $this->getEtatSteps()[$step];
        }
        return false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @deprecated */
    public function getDomaine(): ?Domaine
    {
        return $this->domaine;
    }

    public function getDomaines(): ?Collection
    {
        return $this->mention?->getDomaines();
    }


    /** @deprecated */
    public function setDomaine(?Domaine $domaine): self
    {
        $this->domaine = $domaine;

        return $this;
    }

    public function getComposantePorteuse(): ?Composante
    {
        return $this->composantePorteuse;
    }

    public function setComposantePorteuse(?Composante $composantePorteuse): self
    {
        $this->composantePorteuse = $composantePorteuse;

        return $this;
    }

    public function getDpe(): ?CampagneCollecte
    {
        return $this->dpe;
    }

    public function setDpe(?CampagneCollecte $dpe): self
    {
        $this->dpe = $dpe;

        return $this;
    }

    public function getMention(): ?Mention
    {
        return $this->mention;
    }

    public function setMention(?Mention $mention): self
    {
        $this->mention = $mention;

        return $this;
    }

    public function getMentionTexte(): ?string
    {
        return $this->mentionTexte;
    }

    public function setMentionTexte(?string $mentionTexte): self
    {
        $this->mentionTexte = $mentionTexte;

        return $this;
    }

    public function getNiveauEntree(): ?NiveauFormationEnum
    {
        return $this->niveauEntree;
    }

    public function setNiveauEntree(NiveauFormationEnum $niveauEntree): self
    {
        $this->niveauEntree = $niveauEntree;

        return $this;
    }

    public function getNiveauSortie(): ?NiveauFormationEnum
    {
        return $this->niveauSortie;
    }

    public function setNiveauSortie(NiveauFormationEnum $niveauSortie): self
    {
        $this->niveauSortie = $niveauSortie;

        return $this;
    }

    public function isInRncp(): ?bool
    {
        return $this->inRncp;
    }

    public function setInRncp(bool $inRncp): self
    {
        $this->inRncp = $inRncp;

        return $this;
    }

    public function getCodeRNCP(): ?string
    {
        return $this->codeRNCP;
    }

    public function setCodeRNCP(?string $codeRNCP): self
    {
        $this->codeRNCP = $codeRNCP;

        return $this;
    }

    public function getResponsableMention(): ?User
    {
        return $this->responsableMention;
    }

    public function setResponsableMention(?User $responsableMention): self
    {
        $this->responsableMention = $responsableMention;

        return $this;
    }

    public function getSemestreDebut(): ?int
    {
        return $this->semestreDebut;
    }

    public function setSemestreDebut(int $semestreDebut): self
    {
        $this->semestreDebut = $semestreDebut;

        return $this;
    }

    /**
     * @return Collection<int, Ville>
     */
    public function getLocalisationMention(): Collection
    {
        return $this->localisationMention;
    }

    public function addLocalisationMention(Ville $localisationMention): self
    {
        if (!$this->localisationMention->contains($localisationMention)) {
            $this->localisationMention->add($localisationMention);
        }

        return $this;
    }

    public function removeLocalisationMention(Ville $localisationMention): self
    {
        $this->localisationMention->removeElement($localisationMention);

        return $this;
    }

    /**
     * @return Collection<int, Composante>
     */
    public function getComposantesInscription(): Collection
    {
        return $this->composantesInscription;
    }

    public function addComposantesInscription(Composante $composantesInscription): self
    {
        if (!$this->composantesInscription->contains($composantesInscription)) {
            $this->composantesInscription->add($composantesInscription);
        }

        return $this;
    }

    public function removeComposantesInscription(Composante $composantesInscription): self
    {
        $this->composantesInscription->removeElement($composantesInscription);

        return $this;
    }


    public function getRegimeInscriptionTexte(): ?string
    {
        return $this->regimeInscriptionTexte;
    }

    public function setRegimeInscriptionTexte(?string $regimeInscriptionTexte): self
    {
        $this->regimeInscriptionTexte = $regimeInscriptionTexte;

        return $this;
    }

    public function getModalitesAlternance(): ?string
    {
        return $this->modalitesAlternance;
    }

    public function setModalitesAlternance(?string $modalitesAlternance): self
    {
        $this->modalitesAlternance = $modalitesAlternance;

        return $this;
    }

    #[Groups(['formation:read'])]
    public function getDisplay(bool $avecSigle = true): ?string
    {
        $texte = $this->getMention() === null ? $this->getMentionTexte() : $this->getMention()->getLibelle();
        if ($avecSigle && $this->sigle !== null && trim($this->sigle) !== '') {
            $texte .= ' (' . $this->sigle . ')';
        }

        return $texte;
    }

    public function getRegimeInscription(): array
    {
        $t = [];
        foreach ($this->regimeInscription as $value) {
            if ($value instanceof RegimeInscriptionEnum) {
                $t[] = $value;
            } else {
                $t[] = RegimeInscriptionEnum::from($value);
            }
        }


        return $t;
    }

    public function setRegimeInscription(?array $regimeInscription): self
    {
        $this->regimeInscription = $regimeInscription;

        return $this;
    }

    public function getContenuFormation(): ?string
    {
        return $this->contenuFormation;
    }

    public function setContenuFormation(?string $contenuFormation): self
    {
        $this->contenuFormation = $contenuFormation;

        return $this;
    }

    public function getResultatsAttendus(): ?string
    {
        return $this->resultatsAttendus;
    }

    public function setResultatsAttendus(?string $resultatsAttendus): self
    {
        $this->resultatsAttendus = $resultatsAttendus;

        return $this;
    }

    public function getRythmeFormationTexte(): ?string
    {
        return $this->rythmeFormationTexte;
    }

    public function setRythmeFormationTexte(?string $rythmeFormationTexte): self
    {
        $this->rythmeFormationTexte = $rythmeFormationTexte;

        return $this;
    }

    public function isHasParcours(): ?bool
    {
        return $this->hasParcours;
    }

    public function setHasParcours(?bool $hasParcours): self
    {
        $this->hasParcours = $hasParcours;

        return $this;
    }

    public function getStructureSemestres(): array
    {
        return $this->structureSemestres ?? [];
    }

    public function setStructureSemestres(?array $structureSemestres): self
    {
        $this->structureSemestres = $structureSemestres;

        return $this;
    }

    /**
     * @return Collection<int, Parcours>
     */
    public function getParcours(): Collection
    {
        return $this->parcours;
    }

    public function addParcour(Parcours $parcour): self
    {
        if (!$this->parcours->contains($parcour)) {
            $this->parcours->add($parcour);
            $parcour->setFormation($this);
        }

        return $this;
    }

    public function removeParcour(Parcours $parcour): self
    {
        // set the owning side to null (unless already changed)
        if ($this->parcours->removeElement($parcour) && $parcour->getFormation() === $this) {
            $parcour->setFormation(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, BlocCompetence>
     */
    public function getBlocCompetences(): Collection
    {
        return $this->blocCompetences;
    }

    public function addBlocCompetence(BlocCompetence $blocCompetence): self
    {
        if (!$this->blocCompetences->contains($blocCompetence)) {
            $this->blocCompetences->add($blocCompetence);
            $blocCompetence->setFormation($this);
        }

        return $this;
    }

    public function removeBlocCompetence(BlocCompetence $blocCompetence): self
    {
        // set the owning side to null (unless already changed)
        if ($this->blocCompetences->removeElement($blocCompetence) && $blocCompetence->getFormation() === $this) {
            $blocCompetence->setFormation(null);
        }

        return $this;
    }

    public function remplissageBrut(): Remplissage
    {
        $valide = new FormationValide($this);
        if ($this->hasParcours === true) {
            $valide->valideOnlyFormation();
            $remplissage = $valide->calcul();
            if ($this->getParcours()->count() > 0) {
                foreach ($this->getParcours() as $parcours) {
                    $remp = $parcours->getRemplissage();
                    $remplissage->addRemplissage($remp);
                }
            } else {
                $remplissage->total += 30;
            }
        } else {
            $valide->valideFormation();
            $remplissage = $valide->calcul();
        }

        return $remplissage;
    }

    public function getRythmeFormation(): ?RythmeFormation
    {
        return $this->rythmeFormation;
    }

    public function setRythmeFormation(?RythmeFormation $rythmeFormation): self
    {
        $this->rythmeFormation = $rythmeFormation;

        return $this;
    }

    /** @deprecated("sur le DPE") */
    public function getEtatDpe(): array
    {
        return $this->etatDpe ?? [];
    }

    /** @deprecated("sur le DPE") */
    public function setEtatDpe(?array $etatDpe): self
    {
        $this->etatDpe = $etatDpe;

        return $this;
    }

    public function getEtatSteps(): array
    {
        return $this->etatSteps ?? [];
    }

    public function setEtatSteps(array $etatSteps): self
    {
        $this->etatSteps = $etatSteps;

        return $this;
    }

    public function getObjectifsFormation(): ?string
    {
        return $this->objectifsFormation;
    }

    public function setObjectifsFormation(?string $objectifsFormation): self
    {
        $this->objectifsFormation = $objectifsFormation;

        return $this;
    }

    public function getTypeDiplome(): ?TypeDiplome
    {
        return $this->typeDiplome;
    }

    public function setTypeDiplome(?TypeDiplome $typeDiplome): self
    {
        $this->typeDiplome = $typeDiplome;

        return $this;
    }

    public function getSigle(): ?string
    {
        return $this->sigle;
    }

    public function setSigle(?string $sigle): self
    {
        $this->sigle = $sigle;

        return $this;
    }

    public function getCoResponsable(): ?User
    {
        return $this->coResponsable;
    }

    public function setCoResponsable(?User $coResponsable): self
    {
        $this->coResponsable = $coResponsable;

        return $this;
    }

    /**
     * @return Collection<int, TypeEc>
     */
    public function getTypeEcs(): Collection
    {
        return $this->typeEcs;
    }

    public function addTypeEc(TypeEc $typeEc): self
    {
        if (!$this->typeEcs->contains($typeEc)) {
            $this->typeEcs->add($typeEc);
            $typeEc->setFormation($this);
        }

        return $this;
    }

    public function removeTypeEc(TypeEc $typeEc): self
    {
        if ($this->typeEcs->removeElement($typeEc)) {
            // set the owning side to null (unless already changed)
            if ($typeEc->getFormation() === $this) {
                $typeEc->setFormation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ButCompetence>
     */
    public function getButCompetences(): Collection
    {
        return $this->butCompetences;
    }

    public function addButCompetence(ButCompetence $butCompetence): self
    {
        if (!$this->butCompetences->contains($butCompetence)) {
            $this->butCompetences->add($butCompetence);
            $butCompetence->setFormation($this);
        }

        return $this;
    }

    public function removeButCompetence(ButCompetence $butCompetence): self
    {
        if ($this->butCompetences->removeElement($butCompetence)) {
            // set the owning side to null (unless already changed)
            if ($butCompetence->getFormation() === $this) {
                $butCompetence->setFormation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, HistoriqueFormation>
     */
    public function getHistoriqueFormations(): Collection
    {
        return $this->historiqueFormations;
    }

    public function addHistoriqueFormation(HistoriqueFormation $historiqueFormation): static
    {
        if (!$this->historiqueFormations->contains($historiqueFormation)) {
            $this->historiqueFormations->add($historiqueFormation);
            $historiqueFormation->setFormation($this);
        }

        return $this;
    }

    public function removeHistoriqueFormation(HistoriqueFormation $historiqueFormation): static
    {
        if ($this->historiqueFormations->removeElement($historiqueFormation)) {
            // set the owning side to null (unless already changed)
            if ($historiqueFormation->getFormation() === $this) {
                $historiqueFormation->setFormation(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getRemplissage(): Remplissage
    {
        $remplissage = new Remplissage();

        if (
            $this->remplissage !== null &&
            count($this->remplissage) > 0
            && array_key_exists('score', $this->remplissage)
            && array_key_exists('total', $this->remplissage)) {
            $remplissage->setScore($this->remplissage['score']);
            $remplissage->setTotal($this->remplissage['total']);
        }


        return $remplissage;
    }

    public function setRemplissage(?Remplissage $remplissage = null): static
    {
        if (null === $remplissage) {
            $remplissage = $this->remplissageBrut();
        }

        $this->remplissage = [
            'score' => $remplissage->score,
            'total' => $remplissage->total,
        ];

        return $this;
    }

    #[ORM\PreFlush]
    public function updateRemplissage(PreFlushEventArgs $args): void
    {
        $remplissage = $this->remplissageBrut();
        $this->setRemplissage($remplissage);
    }

    public function getDisplayLong(bool $avecSigle = true): string
    {
        $typeD = $this->getTypeDiplome() !== null ? $this->getTypeDiplome()->getLibelle() . ' - ' : '';
        return $typeD . $this->getDisplay($avecSigle);
    }

    /**
     * @return Collection<int, CommentaireFormation>
     */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaires(CommentaireFormation $commentaireFormation): static
    {
        if (!$this->commentaires->contains($commentaireFormation)) {
            $this->commentaires->add($commentaireFormation);
            $commentaireFormation->setFormation($this);
        }

        return $this;
    }

    public function removeCommentaires(CommentaireFormation $commentaireFormation): static
    {
        if ($this->commentaires->removeElement($commentaireFormation)) {
            // set the owning side to null (unless already changed)
            if ($commentaireFormation->getFormation() === $this) {
                $commentaireFormation->setFormation(null);
            }
        }

        return $this;
    }

    public function defaultParcours(): ?Parcours
    {
        if ($this->hasParcours === false &&  $this->parcours->count() === 1) {
            return $this->parcours->first();
        }
        return null;
    }

    /**
     * @return Collection<int, DpeParcours>
     */
    public function getDpeParcours(): Collection
    {
        return $this->dpeParcours;
    }

    public function addDpeParcour(DpeParcours $dpeParcour): static
    {
        if (!$this->dpeParcours->contains($dpeParcour)) {
            $this->dpeParcours->add($dpeParcour);
            $dpeParcour->setFormation($this);
        }

        return $this;
    }

    public function removeDpeParcour(DpeParcours $dpeParcour): static
    {
        if ($this->dpeParcours->removeElement($dpeParcour)) {
            // set the owning side to null (unless already changed)
            if ($dpeParcour->getFormation() === $this) {
                $dpeParcour->setFormation(null);
            }
        }

        return $this;
    }

    /** @deprecated  */
    public function getCodeMentionApogee(): ?string
    {
        return $this->codeMentionApogee;
    }

    /** @deprecated  */
    public function setCodeMentionApogee(?string $codeMentionApogee): static
    {
        $this->codeMentionApogee = $codeMentionApogee;

        return $this;
    }

    /**
     * @return Collection<int, FormationVersioning>
     */
    public function getFormationVersionings(): Collection
    {
        return $this->formationVersionings;
    }

    public function addFormationVersioning(FormationVersioning $formationVersioning): static
    {
        if (!$this->formationVersionings->contains($formationVersioning)) {
            $this->formationVersionings->add($formationVersioning);
            $formationVersioning->setFormation($this);
        }

        return $this;
    }

    public function removeFormationVersioning(FormationVersioning $formationVersioning): static
    {
        if ($this->formationVersionings->removeElement($formationVersioning)) {
            // set the owning side to null (unless already changed)
            if ($formationVersioning->getFormation() === $this) {
                $formationVersioning->setFormation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChangeRf>
     */
    public function getChangeRves(): Collection
    {
        return $this->changeRves;
    }

    public function addChangeRf(ChangeRf $changeRf): static
    {
        if (!$this->changeRves->contains($changeRf)) {
            $this->changeRves->add($changeRf);
            $changeRf->setFormation($this);
        }

        return $this;
    }

    public function removeChangeRf(ChangeRf $changeRf): static
    {
        if ($this->changeRves->removeElement($changeRf)) {
            // set the owning side to null (unless already changed)
            if ($changeRf->getFormation() === $this) {
                $changeRf->setFormation(null);
            }
        }

        return $this;
    }

    public function etatDpeParcours() : array
    {
        //synthèse des états des parcours de la formation
        $etatParcours = [];
        foreach ($this->getParcours() as $parcours) {
            $etatParcours[] = $parcours->getDpeParcours()->first()?->getEtatValidation();
        }
        return array_merge(...$etatParcours);
    }

    public function getEtatReconduction(): ?TypeModificationDpeEnum
    {
        return $this->etatReconduction ?? TypeModificationDpeEnum::OUVERT;
    }

    public function setEtatReconduction(TypeModificationDpeEnum $etatReconduction): static
    {
        $this->etatReconduction = $etatReconduction;

        return $this;
    }

    public function getFormationOrigineCopie(): ?self
    {
        return $this->formationOrigineCopie;
    }

    public function setFormationOrigineCopie(?self $formationOrigineCopie): static
    {
        $this->formationOrigineCopie = $formationOrigineCopie;

        return $this;
    }

    public function getFormationCopieAnneeUniversitaire(): ?self
    {
        return $this->formationCopieAnneeUniversitaire;
    }

    public function setFormationCopieAnneeUniversitaire(?self $formationCopieAnneeUniversitaire): static
    {
        // unset the owning side of the relation if necessary
        if ($formationCopieAnneeUniversitaire === null && $this->formationCopieAnneeUniversitaire !== null) {
            $this->formationCopieAnneeUniversitaire->setFormationOrigineCopie(null);
        }

        // set the owning side of the relation if necessary
        if ($formationCopieAnneeUniversitaire !== null && $formationCopieAnneeUniversitaire->getFormationOrigineCopie() !== $this) {
            $formationCopieAnneeUniversitaire->setFormationOrigineCopie($this);
        }

        $this->formationCopieAnneeUniversitaire = $formationCopieAnneeUniversitaire;

        return $this;
    }

    /**
     * @return Collection<int, UserProfil>
     */
    public function getUserProfils(): Collection
    {
        return $this->userProfils;
    }

    public function addUserProfil(UserProfil $userProfil): static
    {
        if (!$this->userProfils->contains($userProfil)) {
            $this->userProfils->add($userProfil);
            $userProfil->setFormation($this);
        }

        return $this;
    }

    public function removeUserProfil(UserProfil $userProfil): static
    {
        if ($this->userProfils->removeElement($userProfil)) {
            // set the owning side to null (unless already changed)
            if ($userProfil->getFormation() === $this) {
                $userProfil->setFormation(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection<int, DpeDemande>
     */
    public function getDpeDemandes(): Collection
    {
        return $this->dpeDemandes;
    }

    public function addDpeDemande(DpeDemande $dpeDemande): static
    {
        if (!$this->dpeDemandes->contains($dpeDemande)) {
            $this->dpeDemandes->add($dpeDemande);
            $dpeDemande->setFormation($this);
        }

        return $this;
    }

    public function removeDpeDemande(DpeDemande $dpeDemande): static
    {
        if ($this->dpeDemandes->removeElement($dpeDemande)) {
            // set the owning side to null (unless already changed)
            if ($dpeDemande->getFormation() === $this) {
                $dpeDemande->setFormation(null);
            }
        }

        return $this;
    }

    public function getCapaciteAccueil(): ?int
    {
        return $this->capaciteAccueil ?? 0;
    }

    public function setCapaciteAccueil(int $capaciteAccueil): static
    {
        $this->capaciteAccueil = $capaciteAccueil;

        return $this;
    }

    public function getCapacite(): int
    {
        // si capacite accueil définie sur parcours, on retour cette valeur, sinon somme des capacités des parcours

        if ($this->capaciteAccueil !== null && $this->capaciteAccueil > 0) {
            return $this->capaciteAccueil;
        }

        return array_sum(array_map(fn($parcours) => $parcours->getCapaciteAccueil(), $this->getParcours()->toArray()));
    }

    /**
     * @return Collection<int, ChangeParcours>
     */
    public function getChangeParcours(): Collection
    {
        return $this->changeParcours;
    }

    public function addChangeParcour(ChangeParcours $changeParcour): static
    {
        if (!$this->changeParcours->contains($changeParcour)) {
            $this->changeParcours->add($changeParcour);
            $changeParcour->setFormation($this);
        }

        return $this;
    }

    public function removeChangeParcour(ChangeParcours $changeParcour): static
    {
        if ($this->changeParcours->removeElement($changeParcour)) {
            // set the owning side to null (unless already changed)
            if ($changeParcour->getFormation() === $this) {
                $changeParcour->setFormation(null);
            }
        }

        return $this;
    }

    public function isNonOuvert(): bool
    {
        return $this->etatReconduction === TypeModificationDpeEnum::FERMETURE_DEFINITIVE;
    }

}
