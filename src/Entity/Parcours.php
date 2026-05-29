<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Parcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Entity;

use App\Classes\verif\ParcoursValide;
use App\DTO\Remplissage;
use App\DTO\StatsFichesMatieresParcours;
use App\Entity\Traits\LifeCycleTrait;
use App\Enums\ModaliteEnseignementEnum;
use App\Enums\NiveauLangueEnum;
use App\Enums\RegimeInscriptionEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Repository\ParcoursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ParcoursRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Parcours
{
    use LifeCycleTrait;

    public const PARCOURS_DEFAUT = 'Parcours par défaut';

    #[Groups(['fiche_matiere_versioning_ec_parcours', 'DTO_json_versioning'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning', 'fiche_matiere_versioning_ec_parcours'])]
    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\ManyToOne(targetEntity: Formation::class, cascade: ['persist'], inversedBy: 'parcours')]
    private ?Formation $formation;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: BlocCompetence::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $blocCompetences;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenuFormation = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objectifsParcours = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resultatsAttendus = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rythmeFormationTexte = null;

    #[ORM\ManyToOne]
    private ?Ville $ville = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?bool $hasStage = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stageText = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?float $nbHeuresStages;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?bool $hasProjet = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $projetText = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?float $nbHeuresProjet;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?bool $hasMemoire = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $memoireText = null;

    #[Groups(['parcours_json_versioning', 'DTO_json_versioning'])]
    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: ModaliteEnseignementEnum::class)]
    private ?ModaliteEnseignementEnum $modalitesEnseignement = ModaliteEnseignementEnum::NON_DEFINI;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prerequis = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $poursuitesEtudes = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $debouches = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?array $codesRome = [];

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne]
    private ?RythmeFormation $rythmeFormation = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: SemestreParcours::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $semestreParcours;

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne]
    private ?Composante $composanteInscription = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?array $regimeInscription = [];

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $modalitesAlternance = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne]
    private ?User $respParcours = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $coordSecretariat = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?bool $hasSituationPro = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $situationProText = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private ?float $nbHeuresSituationPro = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne]
    private ?Ville $localisation = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Groups('parcours_json_versioning')]
    private ?string $sigle = null;

    #[ORM\Column]
    private ?array $etatSteps = [];

    #[ORM\Column(nullable: true)]
    private ?array $etatParcours = [];

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: FicheMatiere::class)]
    private Collection $ficheMatieres;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: FicheMatiereMutualisable::class)]
    private Collection $ficheMatiereParcours;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: SemestreMutualisable::class)]
    private Collection $semestreMutualisables;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: UeMutualisable::class)]
    private Collection $ueMutualisables;

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne(inversedBy: 'coParcours')]
    private ?User $coResponsable = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'parcoursEnfants')]
    private ?self $parcoursParent = null;

    #[ORM\OneToMany(mappedBy: 'parcoursParent', targetEntity: self::class)]
    private Collection $parcoursEnfants;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: HistoriqueParcours::class)]
    #[ORM\OrderBy(['created' => 'DESC'])]
    private Collection $historiqueParcours;

    #[ORM\Column(nullable: true)]
    private ?array $remplissage = [];

    #[ORM\Column(nullable: true)]
    private ?array $etatsFichesMatieres = [];

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: ElementConstitutif::class, cascade: ['persist'])]
    private Collection $elementConstitutifs;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: CommentaireParcours::class)]
    private Collection $commentaires;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: ParcoursVersioning::class)]
    private Collection $parcoursVersionings;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups('parcours_json_versioning')]
    private ?string $modalitesAdmission = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $codeApogee = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups('parcours_json_versioning')]
    private ?string $descriptifHautPageAutomatique = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups('parcours_json_versioning')]
    private ?string $descriptifHautPage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups('parcours_json_versioning')]
    private ?string $descriptifBasPage = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups('parcours_json_versioning')]
    private ?string $codeRNCP = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 20, nullable: true, enumType: TypeParcoursEnum::class)]
    private ?TypeParcoursEnum $typeParcours = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: Contact::class, cascade: ['persist'])]
    private Collection $contacts;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $codeApogeeNumeroVersion = "1";

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: DpeParcours::class, cascade: ['persist'])]
    #[ORM\OrderBy(['created' => 'DESC'])]
    private Collection $dpeParcours;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $parcoursOrigine = null; //ne devrait pas changer mais plus cohérent de le mettre ici

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $codeMentionApogee = null;

    #[ORM\Column(type: Types::STRING, length: 5, enumType: NiveauLangueEnum::class)]
    #[Groups('parcours_json_versioning')]
    private ?NiveauLangueEnum $niveauFrancais;

    /** @var Parcours $parcoursOrigineCopie Référence le parcours d'origine, depuis la copie */
    #[ORM\OneToOne(inversedBy: 'parcoursCopieAnneeUniversitaire', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $parcoursOrigineCopie = null;

    /** @var Parcours $parcoursCopieAnneeUniversitaire Référence l'élément copié, depuis le parcours d'origine */
    #[ORM\OneToOne(mappedBy: 'parcoursOrigineCopie', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $parcoursCopieAnneeUniversitaire = null;

    /**
     * @var Collection<int, UserProfil>
     */
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: UserProfil::class)]
    private Collection $userProfils;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: DpeDemande::class, cascade: ['persist'])]
    private Collection $dpeDemandes;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motsCles = null;

    /**
     * @var Collection<int, Annee>
     */
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: Annee::class)]
    private Collection $annees;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column]
    private ?int $capaciteAccueil = 0;

    /**
     * @var Collection<int, ChangeParcours>
     */
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: ChangeParcours::class)]
    private Collection $changeParcours;

    #[ORM\Column(nullable: true)]
    private ?array $logo = [];

    public function __construct(?Formation $formation)
    {
        $this->formation = $formation;
        $this->blocCompetences = new ArrayCollection();
        $this->semestreParcours = new ArrayCollection();
        $this->dpeDemandes = new ArrayCollection();

        for ($i = 1; $i <= 6; $i++) {
            $this->etatSteps[$i] = false;
        }
        $this->ficheMatieres = new ArrayCollection();
        $this->ficheMatiereParcours = new ArrayCollection();
        $this->semestreMutualisables = new ArrayCollection();
        $this->ueMutualisables = new ArrayCollection();
        $this->parcoursEnfants = new ArrayCollection();
        $this->historiqueParcours = new ArrayCollection();
        $this->elementConstitutifs = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->parcoursVersionings = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->dpeParcours = new ArrayCollection();
        $this->niveauFrancais = NiveauLangueEnum::B2;
        $this->userProfils = new ArrayCollection();
        $this->annees = new ArrayCollection();
        $this->changeParcours = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

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
            $blocCompetence->setParcours($this);
        }

        return $this;
    }

    public function removeBlocCompetence(BlocCompetence $blocCompetence): self
    {
        // set the owning side to null (unless already changed)
        if ($this->blocCompetences->removeElement($blocCompetence) && $blocCompetence->getParcours() === $this) {
            $blocCompetence->setParcours(null);
        }

        return $this;
    }

    public function getVille(): ?Ville
    {
        return $this->ville;
    }

    public function setVille(?Ville $ville): self
    {
        $this->ville = $ville;

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

    public function isHasStage(): ?bool
    {
        return $this->hasStage;
    }

    public function setHasStage(?bool $hasStage): self
    {
        $this->hasStage = $hasStage;

        return $this;
    }

    public function getStageText(): ?string
    {
        return $this->stageText;
    }

    public function setStageText(?string $stageText): self
    {
        $this->stageText = $stageText;

        return $this;
    }

    public function getNbHeuresStages(): ?float
    {
        return $this->nbHeuresStages ?? null;
    }

    public function setNbHeuresStages(?float $nbHeuresStages): self
    {
        $this->nbHeuresStages = $nbHeuresStages;

        return $this;
    }

    public function isHasProjet(): ?bool
    {
        return $this->hasProjet;
    }

    public function setHasProjet(?bool $hasProjet): self
    {
        $this->hasProjet = $hasProjet;

        return $this;
    }

    public function getProjetText(): ?string
    {
        return $this->projetText;
    }

    public function setProjetText(?string $projetText): self
    {
        $this->projetText = $projetText;

        return $this;
    }

    public function getNbHeuresProjet(): ?float
    {
        return $this->nbHeuresProjet ?? null;
    }

    public function setNbHeuresProjet(float $nbHeuresProjet): self
    {
        $this->nbHeuresProjet = $nbHeuresProjet;

        return $this;
    }

    public function isHasMemoire(): ?bool
    {
        return $this->hasMemoire;
    }

    public function setHasMemoire(?bool $hasMemoire): self
    {
        $this->hasMemoire = $hasMemoire;

        return $this;
    }

    public function getMemoireText(): ?string
    {
        return $this->memoireText;
    }

    public function setMemoireText(?string $memoireText): self
    {
        $this->memoireText = $memoireText;

        return $this;
    }

    public function getModalitesEnseignement(): ?ModaliteEnseignementEnum
    {
        return $this->modalitesEnseignement;
    }

    public function setModalitesEnseignement(?ModaliteEnseignementEnum $modalitesEnseignement): self
    {
        $this->modalitesEnseignement = $modalitesEnseignement;

        return $this;
    }

    public function getPrerequis(): ?string
    {
        return $this->prerequis;
    }

    public function setPrerequis(?string $prerequis): self
    {
        $this->prerequis = $prerequis;

        return $this;
    }

    public function getPoursuitesEtudes(): ?string
    {
        return $this->poursuitesEtudes;
    }

    public function setPoursuitesEtudes(?string $poursuitesEtudes): self
    {
        $this->poursuitesEtudes = $poursuitesEtudes;

        return $this;
    }

    public function getDebouches(): ?string
    {
        return $this->debouches;
    }

    public function setDebouches(?string $debouches): self
    {
        $this->debouches = $debouches;

        return $this;
    }

    public function getCodesRome(): array
    {
        return $this->codesRome ?? [];
    }

    public function setCodesRome(?array $codesRome): self
    {
        $this->codesRome = $codesRome;

        return $this;
    }

    public function remplissageBrut(): Remplissage
    {
        if (null !== $this->getFormation()) {
            $verification = new ParcoursValide($this, $this->getFormation()?->getTypeDiplome());
            $verification->valideParcours();

            return $verification->calcul();
        }
        return new Remplissage();
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

    /**
     * @return Collection<int, SemestreParcours>
     */
    public function getSemestreParcours(): Collection
    {
        return $this->semestreParcours;
    }

    public function addSemestreParcour(SemestreParcours $semestreParcour): self
    {
        if (!$this->semestreParcours->contains($semestreParcour)) {
            $this->semestreParcours->add($semestreParcour);
            $semestreParcour->setParcours($this);
        }

        return $this;
    }

    public function removeSemestreParcour(SemestreParcours $semestreParcour): self
    {
        // set the owning side to null (unless already changed)
        if ($this->semestreParcours->removeElement($semestreParcour) && $semestreParcour->getParcours() === $this) {
            $semestreParcour->setParcours(null);
        }

        return $this;
    }

    public function getComposanteInscription(): ?Composante
    {
        if ($this->isParcoursDefaut() === true) {
            return $this->composanteInscription ?? $this->getFormation()?->getComposantesInscription()?->first();
        }
        return $this->composanteInscription;
    }

    public function setComposanteInscription(?Composante $composanteInscription): self
    {
        $this->composanteInscription = $composanteInscription;

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

    public function getRegimeInscription(): array
    {
        if ($this->regimeInscription !== null && count($this->regimeInscription) === 0) {
            if (count($this->getFormation()?->getRegimeInscription()) !== 0) {
                return $this->getFormation()?->getRegimeInscription();
            }
        }

        if ($this->regimeInscription === null) {
            return [];
        }

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

    public function getRespParcours(): ?User
    {
        return $this->respParcours;
    }

    public function setRespParcours(?User $respParcours): self
    {
        $this->respParcours = $respParcours;

        return $this;
    }

    public function getCoordSecretariat(): ?string
    {
        return $this->coordSecretariat;
    }

    public function setCoordSecretariat(?string $coordSecretariat): self
    {
        $this->coordSecretariat = $coordSecretariat;

        return $this;
    }

    public function getNbHeuresSituationPro(): ?float
    {
        return $this->nbHeuresSituationPro;
    }

    public function setNbHeuresSituationPro(?float $nbHeuresSituationPro): self
    {
        $this->nbHeuresSituationPro = $nbHeuresSituationPro;

        return $this;
    }

    public function getLocalisation(): ?Ville
    {
        return $this->localisation;
    }

    public function setLocalisation(?Ville $localisation): self
    {
        $this->localisation = $localisation;

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

    public function getEtatStep(int $step): bool
    {
        if (array_key_exists($step, $this->getEtatSteps())) {
            return $this->getEtatSteps()[$step];
        }

        return false;
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

    public function getObjectifsParcours(): ?string
    {
        return $this->objectifsParcours;
    }

    public function setObjectifsParcours(?string $objectifsParcours): self
    {
        $this->objectifsParcours = $objectifsParcours;

        return $this;
    }

    public function isHasSituationPro(): ?bool
    {
        return $this->hasSituationPro;
    }

    public function setHasSituationPro(bool $hasSituationPro): self
    {
        $this->hasSituationPro = $hasSituationPro;

        return $this;
    }

    public function getSituationProText(): ?string
    {
        return $this->situationProText;
    }

    public function setSituationProText(?string $situationProText): self
    {
        $this->situationProText = $situationProText;

        return $this;
    }

    public function getEtatParcours(): array
    {
        return $this->etatParcours ?? [];
    }

    public function setEtatParcours(?array $etatParcours): self
    {
        $this->etatParcours = $etatParcours;

        return $this;
    }

    public function isParcoursDefaut(): bool
    {
        return $this->libelle === self::PARCOURS_DEFAUT;
    }

    public function getTypeDiplome(): ?TypeDiplome
    {
        return $this->getFormation()?->getTypeDiplome();
    }

    /**
     * @return Collection<int, FicheMatiere>
     */
    public function getFicheMatieres(): Collection
    {
        return $this->ficheMatieres;
    }

    public function addFicheMatiere(FicheMatiere $ficheMatiere): self
    {
        if (!$this->ficheMatieres->contains($ficheMatiere)) {
            $this->ficheMatieres->add($ficheMatiere);
            $ficheMatiere->setParcours($this);
        }

        return $this;
    }

    public function removeFicheMatiere(FicheMatiere $ficheMatiere): self
    {
        if ($this->ficheMatieres->removeElement($ficheMatiere)) {
            // set the owning side to null (unless already changed)
            if ($ficheMatiere->getParcours() === $this) {
                $ficheMatiere->setParcours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FicheMatiereMutualisable>
     */
    public function getFicheMatiereParcours(): Collection
    {
        return $this->ficheMatiereParcours;
    }

    public function addFicheMatiereParcour(FicheMatiereMutualisable $ficheMatiereParcour): self
    {
        if (!$this->ficheMatiereParcours->contains($ficheMatiereParcour)) {
            $this->ficheMatiereParcours->add($ficheMatiereParcour);
            $ficheMatiereParcour->setParcours($this);
        }

        return $this;
    }

    public function removeFicheMatiereParcour(FicheMatiereMutualisable $ficheMatiereParcour): self
    {
        if ($this->ficheMatiereParcours->removeElement($ficheMatiereParcour)) {
            // set the owning side to null (unless already changed)
            if ($ficheMatiereParcour->getParcours() === $this) {
                $ficheMatiereParcour->setParcours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SemestreMutualisable>
     */
    public function getSemestreMutualisables(): Collection
    {
        return $this->semestreMutualisables;
    }

    public function addSemestreMutualisable(SemestreMutualisable $semestreMutualisable): self
    {
        if (!$this->semestreMutualisables->contains($semestreMutualisable)) {
            $this->semestreMutualisables->add($semestreMutualisable);
            $semestreMutualisable->setParcours($this);
        }

        return $this;
    }

    public function removeSemestreMutualisable(SemestreMutualisable $semestreMutualisable): self
    {
        if ($this->semestreMutualisables->removeElement($semestreMutualisable)) {
            // set the owning side to null (unless already changed)
            if ($semestreMutualisable->getParcours() === $this) {
                $semestreMutualisable->setParcours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UeMutualisable>
     */
    public function getUeMutualisables(): Collection
    {
        return $this->ueMutualisables;
    }

    public function addUeMutualisable(UeMutualisable $ueMutualisable): self
    {
        if (!$this->ueMutualisables->contains($ueMutualisable)) {
            $this->ueMutualisables->add($ueMutualisable);
            $ueMutualisable->setParcours($this);
        }

        return $this;
    }

    public function removeUeMutualisable(UeMutualisable $ueMutualisable): self
    {
        if ($this->ueMutualisables->removeElement($ueMutualisable)) {
            // set the owning side to null (unless already changed)
            if ($ueMutualisable->getParcours() === $this) {
                $ueMutualisable->setParcours(null);
            }
        }

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

    public function getParcoursParent(): ?self
    {
        return $this->parcoursParent;
    }

    public function setParcoursParent(?self $parcoursParent): self
    {
        $this->parcoursParent = $parcoursParent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getParcoursEnfants(): Collection
    {
        return $this->parcoursEnfants;
    }

    public function addParcoursEnfant(self $parcoursEnfant): self
    {
        if (!$this->parcoursEnfants->contains($parcoursEnfant)) {
            $this->parcoursEnfants->add($parcoursEnfant);
            $parcoursEnfant->setParcoursParent($this);
        }

        return $this;
    }

    public function removeParcoursEnfant(self $parcoursEnfant): self
    {
        if ($this->parcoursEnfants->removeElement($parcoursEnfant)) {
            // set the owning side to null (unless already changed)
            if ($parcoursEnfant->getParcoursParent() === $this) {
                $parcoursEnfant->setParcoursParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, HistoriqueParcours>
     */
    public function getHistoriqueParcours(): Collection
    {
        return $this->historiqueParcours;
    }

    public function addHistoriqueParcour(HistoriqueParcours $historiqueParcour): static
    {
        if (!$this->historiqueParcours->contains($historiqueParcour)) {
            $this->historiqueParcours->add($historiqueParcour);
            $historiqueParcour->setParcours($this);
        }

        return $this;
    }

    public function removeHistoriqueParcour(HistoriqueParcours $historiqueParcour): static
    {
        if ($this->historiqueParcours->removeElement($historiqueParcour)) {
            // set the owning side to null (unless already changed)
            if ($historiqueParcour->getParcours() === $this) {
                $historiqueParcour->setParcours(null);
            }
        }

        return $this;
    }

    public function getValide()
    {
        return $this->getEtatParcours()['valide'] ?? false;
    }

    public function getRemplissage(): Remplissage
    {
        $remplissage = new Remplissage();

        if ($this->remplissage !== null &&
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

    public function getEtatsFichesMatieres(): StatsFichesMatieresParcours
    {
        $etatsFichesMatieres = new StatsFichesMatieresParcours($this);

        if ($this->etatsFichesMatieres !== null &&
            count($this->etatsFichesMatieres) > 0
            && array_key_exists('nbFiches', $this->etatsFichesMatieres)
            && array_key_exists('nbFichesValidees', $this->etatsFichesMatieres)
            && array_key_exists('nbFichesNonValideesSes', $this->etatsFichesMatieres)
            && array_key_exists('nbFichesCompletes', $this->etatsFichesMatieres)
            && array_key_exists('nbFichesPubliees', $this->etatsFichesMatieres)
            && array_key_exists('nbFichesNonValidees', $this->etatsFichesMatieres)) {

            $etatsFichesMatieres->nbFiches = $this->etatsFichesMatieres['nbFiches'];
            $etatsFichesMatieres->nbFichesValidees = $this->etatsFichesMatieres['nbFichesValidees'];
            $etatsFichesMatieres->nbFichesNonValideesSes = $this->etatsFichesMatieres['nbFichesNonValideesSes'];
            $etatsFichesMatieres->nbFichesCompletes = $this->etatsFichesMatieres['nbFichesCompletes'];
            $etatsFichesMatieres->nbFichesNonValidees = $this->etatsFichesMatieres['nbFichesNonValidees'];
            $etatsFichesMatieres->nbFichesPubliees = $this->etatsFichesMatieres['nbFichesPubliees'];

        }

        return $etatsFichesMatieres;
    }

    public function setEtatsFichesMatieres(?StatsFichesMatieresParcours $etatsFichesMatieres = null): static
    {
        if (null === $etatsFichesMatieres) {
           return $this;
        }

        $this->etatsFichesMatieres = [
            'nbFiches' => $etatsFichesMatieres->nbFiches,
            'nbFichesValidees' => $etatsFichesMatieres->nbFichesValidees,
            'nbFichesNonValideesSes' => $etatsFichesMatieres->nbFichesNonValideesSes,
            'nbFichesCompletes' => $etatsFichesMatieres->nbFichesCompletes,
            'nbFichesNonValidees' => $etatsFichesMatieres->nbFichesNonValidees,
            'nbFichesPubliees' => $etatsFichesMatieres->nbFichesPubliees,
        ];

        return $this;
    }

    /**
     * @return Collection<int, ElementConstitutif>
     */
    public function getElementConstitutifs(): Collection
    {
        return $this->elementConstitutifs;
    }

    public function addElementConstitutif(ElementConstitutif $elementConstitutif): static
    {
        if (!$this->elementConstitutifs->contains($elementConstitutif)) {
            $this->elementConstitutifs->add($elementConstitutif);
            $elementConstitutif->setParcours($this);
        }

        return $this;
    }

    public function removeElementConstitutif(ElementConstitutif $elementConstitutif): static
    {
        if ($this->elementConstitutifs->removeElement($elementConstitutif)) {
            // set the owning side to null (unless already changed)
            if ($elementConstitutif->getParcours() === $this) {
                $elementConstitutif->setParcours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CommentaireParcours>
     */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaire(CommentaireParcours $commentaireParcour): static
    {
        if (!$this->commentaires->contains($commentaireParcour)) {
            $this->commentaires->add($commentaireParcour);
            $commentaireParcour->setParcours($this);
        }

        return $this;
    }

    public function removeCommentaire(CommentaireParcours $commentaireParcour): static
    {
        if ($this->commentaires->removeElement($commentaireParcour)) {
            // set the owning side to null (unless already changed)
            if ($commentaireParcour->getParcours() === $this) {
                $commentaireParcour->setParcours(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ParcoursVersioning>
     */
    public function getParcoursVersionings(): Collection
    {
        return $this->parcoursVersionings;
    }

    public function addParcoursVersioning(ParcoursVersioning $parcoursVersioning): static
    {
        if (!$this->parcoursVersionings->contains($parcoursVersioning)) {
            $this->parcoursVersionings->add($parcoursVersioning);
            $parcoursVersioning->setParcours($this);
        }

        return $this;
    }

    public function removeParcoursVersioning(ParcoursVersioning $parcoursVersioning): static
    {
        if ($this->parcoursVersionings->removeElement($parcoursVersioning)) {
            // set the owning side to null (unless already changed)
            if ($parcoursVersioning->getParcours() === $this) {
                $parcoursVersioning->setParcours(null);
            }
        }

        return $this;
    }

    public function isAlternance(): bool
    {
        // regarder si dans le tableau regimeInscription il y a les valeurs alternance ou apprentissage
        return in_array(RegimeInscriptionEnum::FI_APPRENTISSAGE, $this->getRegimeInscription(), true) ||
            in_array(RegimeInscriptionEnum::FC_CONTRAT_PRO, $this->getRegimeInscription(), true);
    }

    public function getDisplay(): string
    {
        $str = $this->getLibelle();
        if ($this->typeParcours !== null && $this->typeParcours !== TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE) {
            $str .= ' - ' . $this->typeParcours->getLabel();
        }

        return $str;
    }

    public function displayRegimeInscription(): string
    {
        $texte = '';
        foreach ($this->getRegimeInscription() as $regime) {
            $texte .= $regime->value . ', ';
        }

        return substr($texte, 0, -2);
    }

    public function getModalitesAdmission(): ?string
    {
        return $this->modalitesAdmission;
    }

    public function setModalitesAdmission(?string $modalitesAdmission): static
    {
        $this->modalitesAdmission = $modalitesAdmission;

        return $this;
    }

    public function getCodeApogee(): ?string
    {
        if ($this->isParcoursDefaut()) {
            return 'X';
        }

        return $this->codeApogee;
    }

    public function setCodeApogee(?string $codeApogee): static
    {
        $this->codeApogee = $codeApogee;

        return $this;
    }

    public function getCodeRegimeInscription(): int
    {
        $t = [];
        foreach ($this->getRegimeInscription() as $regime) {
            $t[] = $regime;
        }

        if ((count($t) === 1 || count($t) === 4) && in_array(RegimeInscriptionEnum::FI, $t, true)) {
            return 1;
        }

        if (count($t) >= 3 && !in_array(RegimeInscriptionEnum::FI, $t, true)) {
            return 3;
        }

        if (count($t) === 1 && in_array(RegimeInscriptionEnum::FC, $t, true)) {
            return 2;
        }

        if (in_array(RegimeInscriptionEnum::FI_APPRENTISSAGE, $t, true) || in_array(RegimeInscriptionEnum::FC_CONTRAT_PRO, $t, true)) {

            return 3;
        }

        return 1;
    }

    public function getDescriptifHautPageAutomatique(): ?string
    {
        return $this->descriptifHautPageAutomatique;
    }

    public function getDescriptifHautPage(): ?string
    {
        return $this->descriptifHautPage;
    }

    public function getDescriptifHautPageAffichage(): ?string
    {
        if ($this->descriptifHautPageAutomatique === null && $this->descriptifHautPage=== null) {
            return $this->getFormation()?->getComposantePorteuse()?->getEtablissement()?->getEtablissementInformation()?->getDescriptifHautPage();
        }

        if ($this->descriptifHautPageAutomatique !== null && $this->descriptifHautPage !== null) {
            return $this->descriptifHautPageAutomatique. '<br>'. $this->descriptifHautPage;
        }

        if ($this->descriptifHautPageAutomatique !== null && $this->descriptifHautPage === null) {
            return $this->descriptifHautPageAutomatique;
        }

        if ($this->descriptifHautPageAutomatique === null && $this->descriptifHautPage !== null) {
            return $this->descriptifHautPage;
        }

        return '' ;
    }

    public function setDescriptifHautPage(?string $descriptifHautPage): static
    {
        $this->descriptifHautPage = $descriptifHautPage;

        return $this;
    }

    public function setDescriptifHautPageAutomatique(?string $descriptifHautPageAutomatique): static
    {
        $this->descriptifHautPageAutomatique = $descriptifHautPageAutomatique;

        return $this;
    }

    public function getDescriptifBasPage(): ?string
    {
        return $this->descriptifBasPage;
    }

    public function getDescriptifBasPageAffichage(): ?string
    {
        return $this->descriptifBasPage ?? $this->getFormation()?->getComposantePorteuse()?->getEtablissement()?->getEtablissementInformation()?->getDescriptifBasPage();
    }

    public function setDescriptifBasPage(?string $descriptifBasPage): static
    {
        $this->descriptifBasPage = $descriptifBasPage;

        return $this;
    }

    public function getCodeRNCP(): ?string
    {
        return $this->codeRNCP;
    }

    public function setCodeRNCP(?string $codeRNCP): static
    {
        $this->codeRNCP = $codeRNCP;

        return $this;
    }

    public function getTypeParcours(): ?TypeParcoursEnum
    {
        return $this->typeParcours ?? TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE;
    }

    public function setTypeParcours(?TypeParcoursEnum $typeParcours): static
    {
        $this->typeParcours = $typeParcours;

        return $this;
    }


    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setParcours($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getParcours() === $this) {
                $contact->setParcours(null);
            }
        }

        return $this;
    }

//    public function getAnnees(): array
//    {
//        $annees = [];
//        foreach ($this->getSemestreParcours() as $semestreParcours) {
//
//            if ($semestreParcours->getSemestre()?->isNonDispense() === false) {
//
//                if ($semestreParcours->getOrdre() % 2 === 0) {
//
//                    $annees[] = $semestreParcours->getOrdre() / 2;
//                } else {
//                    $annees[] = ($semestreParcours->getOrdre() + 1) / 2;
//                }
//            }
//        }
//
//        return array_unique($annees);
//    }

    public function getCodeDiplome(?int $annee): ?string
    {
        if ($annee === null) {
            return $this->getSemestreParcours()->first()?->getCodeApogeeDiplome();
        }
        return $this->getSemestrePourAnnee($annee)?->first()?->getCodeApogeeDiplome();
    }

    public function getSemestrePourAnnee(int $annee): ?Collection
    {
        $semestres = $this->semestreParcours;

        $semestres = $semestres->filter(fn (SemestreParcours $semestre) => $semestre->getOrdre() === $annee * 2 - 1);

        if ($semestres->count() === 0) {
            return null;
        }

        return $semestres;
    }

    public function getCodeEtape(int $annee): ?string
    {
        return $this->getSemestrePourAnnee($annee)?->first()?->getCodeApogeeEtapeAnnee();
    }

    public function getCodeVersionDiplome(?int $annee): ?string
    {
        if ($annee === null) {
            return $this->getSemestreParcours()->first()?->getCodeApogeeVersionDiplome();
        }
        return $this->getSemestrePourAnnee($annee)?->first()?->getCodeApogeeVersionDiplome();
    }

    public function getCodeVersionEtape(int $annee): ?string
    {
        return $this->getSemestrePourAnnee($annee)?->first()?->getCodeApogeeEtapeVersion();

    }

    public function getCodeApogeeNumeroVersion(): ?string
    {
        return $this->codeApogeeNumeroVersion ?? "1";
    }

    public function setCodeApogeeNumeroVersion(?string $codeApogeeNumeroVersion = "1"): static
    {
        $this->codeApogeeNumeroVersion = $codeApogeeNumeroVersion;

        return $this;
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
            $dpeParcour->setParcours($this);
        }

        return $this;
    }

    public function removeDpeParcour(DpeParcours $dpeParcour): static
    {
        if ($this->dpeParcours->removeElement($dpeParcour)) {
            // set the owning side to null (unless already changed)
            if ($dpeParcour->getParcours() === $this) {
                $dpeParcour->setParcours(null);
            }
        }

        return $this;
    }

    public function getParcoursOrigine(): ?self
    {
        return $this->parcoursOrigine;
    }

    public function setParcoursOrigine(?self $parcoursOrigine): static
    {
        $this->parcoursOrigine = $parcoursOrigine;

        return $this;
    }

    public function getCodeMentionApogee(): ?string
    {
        return $this->codeMentionApogee;
    }

    public function setCodeMentionApogee(?string $codeMentionApogee): static
    {
        $this->codeMentionApogee = $codeMentionApogee;

        return $this;
    }

    public function getNiveauFrancais(): ?NiveauLangueEnum
    {
        return $this->niveauFrancais ?? NiveauLangueEnum::B2;
    }

    public function setNiveauFrancais(?NiveauLangueEnum $niveauFrancais): static
    {
        $this->niveauFrancais = $niveauFrancais;

        return $this;
    }

    public function etatDpeParcours() : array
    {
        return $this->dpeParcours->first()?->getEtatValidation();
    }

    public function hasReouverture() : bool
    {
        return in_array($this->dpeParcours->first()?->getEtatReconduction(), [
            TypeModificationDpeEnum::MODIFICATION_TEXTE,
            TypeModificationDpeEnum::MODIFICATION_MCCC,
            TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE,
        ])  ;
    }

    public function withCfvu() : bool
    {
        return $this->dpeParcours->first()?->withCfvu();
    }

    public function getParcoursOrigineCopie(): ?self
    {
        return $this->parcoursOrigineCopie;
    }

    public function setParcoursOrigineCopie(?self $parcoursOrigineCopie): static
    {
        $this->parcoursOrigineCopie = $parcoursOrigineCopie;

        return $this;
    }

    public function getParcoursCopieAnneeUniversitaire(): ?self
    {
        return $this->parcoursCopieAnneeUniversitaire;
    }

    public function setParcoursCopieAnneeUniversitaire(?self $parcoursCopieAnneeUniversitaire): static
    {
        // unset the owning side of the relation if necessary
        if ($parcoursCopieAnneeUniversitaire === null && $this->parcoursCopieAnneeUniversitaire !== null) {
            $this->parcoursCopieAnneeUniversitaire->setParcoursOrigineCopie(null);
        }

        // set the owning side of the relation if necessary
        if ($parcoursCopieAnneeUniversitaire !== null && $parcoursCopieAnneeUniversitaire->getParcoursOrigineCopie() !== $this) {
            $parcoursCopieAnneeUniversitaire->setParcoursOrigineCopie($this);
        }

        $this->parcoursCopieAnneeUniversitaire = $parcoursCopieAnneeUniversitaire;

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
            $userProfil->setParcours($this);
        }

        return $this;
    }

    public function removeUserProfil(UserProfil $userProfil): static
    {
        if ($this->userProfils->removeElement($userProfil)) {
            // set the owning side to null (unless already changed)
            if ($userProfil->getParcours() === $this) {
                $userProfil->setParcours(null);
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

    public function hasStage(): ?bool
    {
        return $this->hasStage;
    }

    public function hasProjet(): ?bool
    {
        return $this->hasProjet;
    }

    public function hasMemoire(): ?bool
    {
        return $this->hasMemoire;
    }

    public function hasSituationPro(): ?bool
    {
        return $this->hasSituationPro;
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
            $dpeDemande->setParcours($this);
        }

        return $this;
    }

    public function removeDpeDemande(DpeDemande $dpeDemande): static
    {
        if ($this->dpeDemandes->removeElement($dpeDemande)) {
            // set the owning side to null (unless already changed)
            if ($dpeDemande->getParcours() === $this) {
                $dpeDemande->setParcours(null);
            }
        }

        return $this;
    }

    public function getMotsCles(): ?string
    {
        return $this->motsCles;
    }

    public function setMotsCles(?string $motsCles): static
    {
        $this->motsCles = $motsCles;

        return $this;
    }

    public function getAnnees(): Collection
    {
        return $this->annees;
    }

    public function addAnnee(Annee $annee): static
    {
        if (!$this->annees->contains($annee)) {
            $this->annees->add($annee);
            $annee->setParcours($this);
        }

        return $this;
    }

    public function removeAnnee(Annee $annee): static
    {
        if ($this->annees->removeElement($annee)) {
            // set the owning side to null (unless already changed)
            if ($annee->getParcours() === $this) {
                $annee->setParcours(null);
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
            $changeParcour->setParcours($this);
        }

        return $this;
    }

    public function removeChangeParcour(ChangeParcours $changeParcour): static
    {
        if ($this->changeParcours->removeElement($changeParcour)) {
            // set the owning side to null (unless already changed)
            if ($changeParcour->getParcours() === $this) {
                $changeParcour->setParcours(null);
            }
        }

        return $this;
    }

    public function getLogo(): ?array
    {
        return $this->logo;
    }

    public function setLogo(?array $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function addLogo(string $filename): static
    {
        $this->logo[] = $filename;
        return $this;
    }
}
