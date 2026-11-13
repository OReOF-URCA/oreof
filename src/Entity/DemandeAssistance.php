<?php

namespace App\Entity;

use App\Enums\StatutDemandeAssistanceEnum;
use App\Repository\DemandeAssistanceRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande d'assistance : regroupe les mails échangés avec un usager sur un même sujet.
 */
#[ORM\Entity(repositoryClass: DemandeAssistanceRepository::class)]
#[ORM\Index(name: 'idx_demande_assistance_sujet', columns: ['sujet_normalise'])]
#[ORM\Index(name: 'idx_demande_assistance_dernier', columns: ['date_dernier_message'])]
class DemandeAssistance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $sujet = '';

    #[ORM\Column(length: 255)]
    private string $sujetNormalise = '';

    #[ORM\Column(length: 30, enumType: StatutDemandeAssistanceEnum::class)]
    private StatutDemandeAssistanceEnum $statut = StatutDemandeAssistanceEnum::NOUVELLE;

    #[ORM\Column(length: 180)]
    private string $demandeurEmail = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demandeurNom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $demandeur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $responsable = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Parcours $parcours = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $created;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $dateDernierMessage;

    /**
     * @var Collection<int, MessageAssistance>
     */
    #[ORM\OneToMany(targetEntity: MessageAssistance::class, mappedBy: 'demande', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateEnvoi' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->created = new DateTimeImmutable();
        $this->dateDernierMessage = $this->created;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSujet(): string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = mb_substr($sujet, 0, 255);

        return $this;
    }

    public function getSujetNormalise(): string
    {
        return $this->sujetNormalise;
    }

    public function setSujetNormalise(string $sujetNormalise): static
    {
        $this->sujetNormalise = mb_substr($sujetNormalise, 0, 255);

        return $this;
    }

    public function getStatut(): StatutDemandeAssistanceEnum
    {
        return $this->statut;
    }

    public function setStatut(StatutDemandeAssistanceEnum $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDemandeurEmail(): string
    {
        return $this->demandeurEmail;
    }

    public function setDemandeurEmail(string $demandeurEmail): static
    {
        $this->demandeurEmail = mb_strtolower($demandeurEmail);

        return $this;
    }

    public function getDemandeurNom(): ?string
    {
        return $this->demandeurNom;
    }

    public function setDemandeurNom(?string $demandeurNom): static
    {
        $this->demandeurNom = $demandeurNom !== null ? mb_substr($demandeurNom, 0, 255) : null;

        return $this;
    }

    public function getDemandeur(): ?User
    {
        return $this->demandeur;
    }

    public function setDemandeur(?User $demandeur): static
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    public function getResponsable(): ?User
    {
        return $this->responsable;
    }

    public function setResponsable(?User $responsable): static
    {
        $this->responsable = $responsable;

        return $this;
    }

    public function getParcours(): ?Parcours
    {
        return $this->parcours;
    }

    public function setParcours(?Parcours $parcours): static
    {
        $this->parcours = $parcours;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(DateTimeImmutable $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getDateDernierMessage(): DateTimeImmutable
    {
        return $this->dateDernierMessage;
    }

    public function setDateDernierMessage(DateTimeImmutable $dateDernierMessage): static
    {
        $this->dateDernierMessage = $dateDernierMessage;

        return $this;
    }

    /**
     * @return Collection<int, MessageAssistance>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(MessageAssistance $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setDemande($this);
        }

        if ($message->getDateEnvoi() > $this->dateDernierMessage) {
            $this->dateDernierMessage = $message->getDateEnvoi();
        }

        return $this;
    }

    public function removeMessage(MessageAssistance $message): static
    {
        $this->messages->removeElement($message);

        return $this;
    }

    /**
     * Recalcule la date du dernier message (après fusion ou suppression).
     */
    public function recalculerDateDernierMessage(): void
    {
        $date = $this->created;
        foreach ($this->messages as $message) {
            if ($message->getDateEnvoi() > $date) {
                $date = $message->getDateEnvoi();
            }
        }
        $this->dateDernierMessage = $date;
    }

    /**
     * Dernier message échangé (hors notes internes) portant un identifiant de mail.
     */
    public function getDernierMessageMail(): ?MessageAssistance
    {
        $dernier = null;
        foreach ($this->messages as $message) {
            if ($message->getMessageId() !== null && !$message->estNote()
                && ($dernier === null || $message->getDateEnvoi() >= $dernier->getDateEnvoi())) {
                $dernier = $message;
            }
        }

        return $dernier;
    }
}
