<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/ProjectDirProvider.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/06/2025 10:53
 */

namespace App\Service;

class ProjectDirProvider
{
    public function __construct(private string $projectDir)
    {
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }
}
