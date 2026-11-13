<?php

namespace App\Entity;

use App\Enums\SensMessageAssistanceEnum;
use App\Repository\MessageAssistanceRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Message d'une demande d'assistance : mail reçu, réponse envoyée ou note interne.
 */
#[ORM\Entity(repositoryClass: MessageAssistanceRepository::class)]
class MessageAssistance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DemandeAssistance $demande = null;

    #[ORM\Column(length: 10, enumType: SensMessageAssistanceEnum::class)]
    private SensMessageAssistanceEnum $sens = SensMessageAssistanceEnum::ENTRANT;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $inReplyTo = null;

    #[ORM\Column(name: 'references_mail', type: Types::TEXT, nullable: true)]
    private ?string $references = null;

    #[ORM\Column(length: 180)]
    private string $expediteurEmail = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $expediteurNom = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $destinataires = [];

    #[ORM\Column(length: 255)]
    private string $sujet = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $corpsTexte = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $corpsHtml = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $dateEnvoi;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $auteur = null;

    #[ORM\Column]
    private bool $automatique = false;

    /**
     * @var Collection<int, PieceJointeAssistance>
     */
    #[ORM\OneToMany(targetEntity: PieceJointeAssistance::class, mappedBy: 'message', cascade: ['persist'], orphanRemoval: true)]
    private Collection $piecesJointes;

    public function __construct()
    {
        $this->piecesJointes = new ArrayCollection();
        $this->dateEnvoi = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemande(): ?DemandeAssistance
    {
        return $this->demande;
    }

    public function setDemande(?DemandeAssistance $demande): static
    {
        $this->demande = $demande;

        return $this;
    }

    public function getSens(): SensMessageAssistanceEnum
    {
        return $this->sens;
    }

    public function setSens(SensMessageAssistanceEnum $sens): static
    {
        $this->sens = $sens;

        return $this;
    }

    public function estNote(): bool
    {
        return $this->sens === SensMessageAssistanceEnum::NOTE;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): static
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getInReplyTo(): ?string
    {
        return $this->inReplyTo;
    }

    public function setInReplyTo(?string $inReplyTo): static
    {
        $this->inReplyTo = $inReplyTo;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getReferences(): array
    {
        if ($this->references === null || trim($this->references) === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($this->references)) ?: []));
    }

    /**
     * @param list<string> $references
     */
    public function setReferences(array $references): static
    {
        $this->references = $references === [] ? null : implode(' ', $references);

        return $this;
    }

    public function getExpediteurEmail(): string
    {
        return $this->expediteurEmail;
    }

    public function setExpediteurEmail(string $expediteurEmail): static
    {
        $this->expediteurEmail = mb_strtolower($expediteurEmail);

        return $this;
    }

    public function getExpediteurNom(): ?string
    {
        return $this->expediteurNom;
    }

    public function setExpediteurNom(?string $expediteurNom): static
    {
        $this->expediteurNom = $expediteurNom !== null ? mb_substr($expediteurNom, 0, 255) : null;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getDestinataires(): array
    {
        return $this->destinataires;
    }

    /**
     * @param list<string> $destinataires
     */
    public function setDestinataires(array $destinataires): static
    {
        $this->destinataires = array_values($destinataires);

        return $this;
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

    public function getCorpsTexte(): string
    {
        return $this->corpsTexte;
    }

    public function setCorpsTexte(string $corpsTexte): static
    {
        $this->corpsTexte = $corpsTexte;

        return $this;
    }

    public function getCorpsHtml(): ?string
    {
        return $this->corpsHtml;
    }

    /**
     * Le HTML doit avoir été nettoyé (sanitizer « assistance ») avant d'être passé ici.
     */
    public function setCorpsHtml(?string $corpsHtml): static
    {
        $this->corpsHtml = $corpsHtml;

        return $this;
    }

    public function getDateEnvoi(): DateTimeImmutable
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(DateTimeImmutable $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function isAutomatique(): bool
    {
        return $this->automatique;
    }

    public function setAutomatique(bool $automatique): static
    {
        $this->automatique = $automatique;

        return $this;
    }

    /**
     * @return Collection<int, PieceJointeAssistance>
     */
    public function getPiecesJointes(): Collection
    {
        return $this->piecesJointes;
    }

    public function addPieceJointe(PieceJointeAssistance $pieceJointe): static
    {
        if (!$this->piecesJointes->contains($pieceJointe)) {
            $this->piecesJointes->add($pieceJointe);
            $pieceJointe->setMessage($this);
        }

        return $this;
    }
}
