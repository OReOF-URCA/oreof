<?php

namespace App\Entity;

use App\Repository\NotificationCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationCategoryRepository::class)]
class NotificationCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $code; // ex: "news", "security", "system"

    #[ORM\Column(length: 150)]
    private string $label;

    /**
     * @var Collection<int, UserCategoryNotificationSetting>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: UserCategoryNotificationSetting::class)]
    private Collection $userCategoryNotificationSettings;

    public function __construct()
    {
        $this->userCategoryNotificationSettings = new ArrayCollection();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection<int, UserCategoryNotificationSetting>
     */
    public function getUserCategoryNotificationSettings(): Collection
    {
        return $this->userCategoryNotificationSettings;
    }

    public function addUserCategoryNotificationSetting(UserCategoryNotificationSetting $userCategoryNotificationSetting): static
    {
        if (!$this->userCategoryNotificationSettings->contains($userCategoryNotificationSetting)) {
            $this->userCategoryNotificationSettings->add($userCategoryNotificationSetting);
            $userCategoryNotificationSetting->setCategory($this);
        }

        return $this;
    }

    public function removeUserCategoryNotificationSetting(UserCategoryNotificationSetting $userCategoryNotificationSetting): static
    {
        if ($this->userCategoryNotificationSettings->removeElement($userCategoryNotificationSetting)) {
            // set the owning side to null (unless already changed)
            if ($userCategoryNotificationSetting->getCategory() === $this) {
                $userCategoryNotificationSetting->setCategory(null);
            }
        }

        return $this;
    }
}
