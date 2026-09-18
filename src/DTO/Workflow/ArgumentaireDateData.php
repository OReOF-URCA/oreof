<?php

declare(strict_types=1);

namespace App\DTO\Workflow;

final class ArgumentaireDateData
{
    public ?string $argumentaire = null;
    public ?\DateTimeInterface $dateConseil = null;
    public ?\DateTimeInterface $dateCfvu = null;
}
