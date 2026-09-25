<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/DiffObject.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/05/2024 07:59
 */

namespace App\DTO;

class DiffObject implements \Stringable {

    public function __construct(
        public string|float|int|null $original,
        public string|float|int|null $new
    )
    {}

    public function isDifferent(): bool
    {
        return $this->original !== $this->new;
    }

    public function getDataDiff() : DiffObject|false
    {
        return $this->isDifferent() === true ? $this : false;
    }

    public function displayDiff(): string
    {
        return $this->new . ' (au lieu de ' . $this->original.')';
    }

    public function getOriginalFloat(): float
    {
        return $this->original !== '-' ? (float)$this->original : 0.0;
    }

    public function getNewFloat(): float
    {
        return $this->new !== '-' ? (float)$this->new : 0.0;
    }

    public function __toString(): string
    {
        return (string)($this->new ?? '');
    }
}
