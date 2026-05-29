<?php

namespace App\Entity;

use App\Entity\Traits\LifeCycleTrait;
use App\Repository\EmailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EmailTemplate
{
    use LifeCycleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $workflow;

    #[ORM\Column(type: 'text')]
    private string $subject = '';

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $subjects = []; // ex: {"student":"Sujet {{ user.fullName }}", "tutor":"..."}

    #[ORM\Column(type: 'text')]
    private string $bodyHtml = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(type: 'json')]
    private array $availableVariables = []; // clé => description (pour UI)

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkflow(): ?string
    {
        return $this->workflow;
    }

    public function setWorkflow(string $workflow): static
    {
        $this->workflow = $workflow;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubjects(): array
    {
        return $this->subjects;
    }

    public function setSubjects(array $subjects): self
    {
        $this->subjects = $subjects;
        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(string $bodyHtml): static
    {
        $this->bodyHtml = $bodyHtml;

        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): static
    {
        $this->bodyText = $bodyText;

        return $this;
    }

    public function getAvailableVariables(): array
    {
        return $this->availableVariables;
    }

    public function setAvailableVariables(array $availableVariables): static
    {
        $this->availableVariables = $availableVariables;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
