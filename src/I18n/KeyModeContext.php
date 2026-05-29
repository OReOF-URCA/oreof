<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/I18n/KeyModeContext.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/10/2025 10:01
 */

// src/I18n/KeyModeContext.php
namespace App\I18n;

use Symfony\Component\HttpFoundation\RequestStack;

final class KeyModeContext
{
    public function __construct(private RequestStack $rs)
    {
    }

    public function isEnabled(): bool
    {
        $req = $this->rs->getMainRequest() ?? $this->rs->getCurrentRequest();
        $session = $req?->getSession();
        return (bool)($session?->get('i18n_keys', false));
    }

    public function setEnabled(bool $enabled): void
    {
        $this->rs->getSession()?->set('i18n_keys', $enabled);
    }
}
