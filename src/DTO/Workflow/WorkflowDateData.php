<?php

declare(strict_types=1);

namespace App\DTO\Workflow;

final class WorkflowDateData
{
    public ?\DateTimeInterface $dateConseil = null;
    public ?\DateTimeInterface $dateCfvu = null;
    public ?\DateTimeInterface $datePublication = null;
}
