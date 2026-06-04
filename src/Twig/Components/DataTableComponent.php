<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/DataTableComponent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/12/2025 10:46
 */

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsLiveComponent('DataTable', template: 'components/datatable.html.twig')]
class DataTableComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public array $config = [];

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public string $sortField = '';

    #[LiveProp(writable: true)]
    public string $sortDirection = 'asc';

    #[LiveProp(writable: true)]
    public array $filters = [];

    #[LiveProp(writable: true)]
    public string $globalSearch = '';

    // Propriétés calculées à partir de config
    #[LiveProp]
    public string $entityClass = '';

    #[LiveProp]
    public array $columns = [];

    #[LiveProp]
    public array $actions = [];

    #[LiveProp(writable: true)]
    public int $perPage = 20;

    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }

    #[PostMount]
    public function mount(): void
    {
        // Initialiser les propriétés depuis config
        $this->entityClass = $this->config['entityClass'] ?? '';
        $this->columns = $this->config['columns'] ?? [];
        $this->actions = $this->config['actions'] ?? [];
        $this->perPage = $this->config['perPage'] ?? 20;
        $this->sortField = $this->config['sortField'] ?? '';
        $this->sortDirection = $this->config['sortDirection'] ?? 'asc';

        // initialiser filters avec les colonnes filtrables
        foreach ($this->columns as $column) {
            if ($column['filterable'] ?? false) {
                // si date ajouter to et from
                if ($column['type'] === 'date') {
                    $this->filters[$column['field']] = ['from' => '', 'to' => ''];
                } else {
                    $this->filters[$column['field']] = '';
                }
            }
        }
    }

    #[ExposeInTemplate]
    public function getData(): array
    {
        if (empty($this->entityClass)) {
            return [];
        }

        $qb = $this->createQueryBuilder();
        $this->applyFilters($qb);
        $this->applyGlobalSearch($qb);
        $this->applySorting($qb);

        $query = $qb->getQuery();
        $query->setFirstResult(($this->page - 1) * $this->perPage);
        $query->setMaxResults($this->perPage);

        return $query->getResult();
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')->from($this->entityClass, 'e');

        // Auto-join des relations utilisées dans les colonnes
        $joinedAliases = [];
        foreach ($this->columns as $column) {
            $field = $column['field'];
            if (strpos($field, '.') !== false) {
                $parts = explode('.', $field);
                $currentAlias = 'e';

                for ($i = 0; $i < count($parts) - 1; $i++) {
                    $relation = $parts[$i];
                    $joinAlias = $relation . '_' . $i;

                    if (!in_array($joinAlias, $joinedAliases)) {
                        $qb->leftJoin($currentAlias . '.' . $relation, $joinAlias);
                        $joinedAliases[] = $joinAlias;
                    }

                    $currentAlias = $joinAlias;
                }
            }
        }

        return $qb;
    }

    private function applyFilters(QueryBuilder $qb): void
    {
        foreach ($this->filters as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $column = $this->findColumn($field);
            if (!$column || !($column['filterable'] ?? false)) {
                continue;
            }

            $paramName = str_replace('.', '_', $field);

            // Si une expression de filtre personnalisée est définie
            if (!empty($column['filter_expression'])) {
                $expression = $column['filter_expression'];

                switch ($column['type'] ?? 'text') {
                    case 'text':
                        $qb->andWhere($qb->expr()->like($expression, ':' . $paramName))
                            ->setParameter($paramName, '%' . $value . '%');
                        break;

                    case 'select':
                    case 'entity':
                    case 'boolean':
                        $qb->andWhere($expression . ' = :' . $paramName)
                            ->setParameter($paramName, $value);
                        break;
                }
            } else {
                $fieldPath = $this->getFieldPath($field);

                switch ($column['type'] ?? 'text') {
                    case 'text':
                        $qb->andWhere($qb->expr()->like($fieldPath, ':' . $paramName))
                            ->setParameter($paramName, '%' . $value . '%');
                        break;

                    case 'select':
                    case 'entity':
                        $qb->andWhere($fieldPath . ' = :' . $paramName)
                            ->setParameter($paramName, $value);
                        break;

                    case 'date':
                        if (isset($value['from']) && !empty($value['from'])) {
                            $qb->andWhere($fieldPath . ' >= :' . $paramName . '_from')
                                ->setParameter($paramName . '_from', new \DateTime($value['from']));
                        }
                        if (isset($value['to']) && !empty($value['to'])) {
                            $qb->andWhere($fieldPath . ' <= :' . $paramName . '_to')
                                ->setParameter($paramName . '_to', new \DateTime($value['to']));
                        }
                        break;
                }
            }
        }
    }

    private function findColumn(string $field): ?array
    {
        foreach ($this->columns as $column) {
            if ($column['field'] === $field) {
                return $column;
            }
        }
        return null;
    }

    private function getFieldPath(string $field): string
    {
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $alias = $parts[0] . '_0';
            return $alias . '.' . $parts[count($parts) - 1];
        }
        return 'e.' . $field;
    }

    private function applyGlobalSearch(QueryBuilder $qb): void
    {
        if (empty($this->globalSearch)) {
            return;
        }

        $searchableColumns = array_filter($this->columns, fn($col) => $col['searchable'] ?? true);

        if (empty($searchableColumns)) {
            return;
        }

        $orX = $qb->expr()->orX();
        foreach ($searchableColumns as $column) {
            if (($column['type'] ?? 'text') === 'text') {
                $fieldPath = $this->getFieldPath($column['field']);
                $orX->add($qb->expr()->like($fieldPath, ':globalSearch'));
            }
        }

        if ($orX->count() > 0) {
            $qb->andWhere($orX)->setParameter('globalSearch', '%' . $this->globalSearch . '%');
        }
    }

    private function applySorting(QueryBuilder $qb): void
    {
        if (empty($this->sortField)) {
            return;
        }

        $column = $this->findColumn($this->sortField);
        if (!$column || !($column['sortable'] ?? false)) {
            return;
        }

        // Si une expression de tri personnalisée est définie, l'utiliser
        if (!empty($column['sort_expression'])) {
            $qb->orderBy($column['sort_expression'], strtoupper($this->sortDirection));
        } else {
            $fieldPath = $this->getFieldPath($this->sortField);
            $qb->orderBy($fieldPath, strtoupper($this->sortDirection));
        }
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        if (empty($this->entityClass)) {
            return 0;
        }

        $qb = $this->createQueryBuilder();
        $this->applyFilters($qb);
        $this->applyGlobalSearch($qb);

        $qb->select('COUNT(e.id)');
        $total = $qb->getQuery()->getSingleScalarResult();

        return (int)ceil($total / $this->perPage);
    }

    #[ExposeInTemplate]
    public function getTotal(): int
    {
        if (empty($this->entityClass)) {
            return 0;
        }
        $qb = $this->createQueryBuilder();
        $this->applyFilters($qb);
        $this->applyGlobalSearch($qb);

        $qb->select('COUNT(e.id)');
        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function getFieldValue(object $entity, string $field): mixed
    {
        $parts = explode('.', $field);
        $value = $entity;

        foreach ($parts as $part) {
            if ($value === null) {
                return null;
            }

            // Essayer d'abord le getter standard
            $getter = 'get' . ucfirst($part);
            if (method_exists($value, $getter)) {
                $value = $value->$getter();
                continue;
            }

            // Essayer le getter boolean (is/has)
            $isGetter = 'is' . ucfirst($part);
            if (method_exists($value, $isGetter)) {
                $value = $value->$isGetter();
                continue;
            }

            $hasGetter = 'has' . ucfirst($part);
            if (method_exists($value, $hasGetter)) {
                $value = $value->$hasGetter();
                continue;
            }

            // Si c'est déjà une méthode (avec parenthèses dans le field)
            // Ex: 'isUtilise()' ou 'getFullName()'
            $methodName = rtrim($part, '()');
            if (method_exists($value, $methodName)) {
                $value = $value->$methodName();
                continue;
            }

            // Si aucune méthode trouvée, retourner null
            return null;
        }

        return $value;
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->page = 1;
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->filters = [];
        $this->globalSearch = '';
        $this->page = 1;
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = $page;
    }
}
