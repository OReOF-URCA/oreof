<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/ParcoursActionStatusEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/12/2025 18:25
 */

namespace App\Enums;

enum ParcoursActionStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
