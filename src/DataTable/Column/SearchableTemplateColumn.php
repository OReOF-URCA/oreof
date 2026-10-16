<?php

declare(strict_types=1);

namespace App\DataTable\Column;

use Pentiminax\UX\DataTables\Column\TemplateColumn;

/**
 * TemplateColumn::new() désactive la recherche (colonne + globale) : la zone « Rechercher » du
 * datatable ignore alors la colonne. Ici, déclarer la colonne comme recherchable
 * (setSearchable(true) ou setSearchField()) la réintègre aussi dans la recherche globale.
 */
class SearchableTemplateColumn extends TemplateColumn
{
    public function setSearchable(bool $searchable = true): static
    {
        parent::setSearchable($searchable);
        $this->globalSearchable = $searchable;

        return $this;
    }

    public function setSearchField(string $searchField): static
    {
        parent::setSearchField($searchField);

        return $this->setSearchable();
    }
}
