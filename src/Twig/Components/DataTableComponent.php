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

#[AsLiveComponent('DataTable', template: 'components/_layout/datatable.html.twig')]
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

    #[LiveProp(writable: true)]
    public bool $filtersOpen = false;

    // Propriétés calculées à partir de config
    #[LiveProp]
    public string $entityClass = '';

    #[LiveProp]
    public array $columns = [];

    #[LiveProp]
    public array $actions = [];

    #[LiveProp]
    public array $baseJoins = [];

    #[LiveProp]
    public array $baseWheres = [];

    #[LiveProp]
    public array $baseParameters = [];

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
        $this->baseJoins = $this->config['baseJoins'] ?? [];
        $this->baseWheres = $this->config['baseWheres'] ?? [];
        $this->baseParameters = $this->config['baseParameters'] ?? [];
        $this->perPage = $this->config['perPage'] ?? 20;
        $this->sortField = $this->config['sortField'] ?? '';
        $this->sortDirection = $this->config['sortDirection'] ?? 'asc';
        $this->filtersOpen = $this->config['filtersOpen'] ?? false;

        $this->initializeFilters();
    }

    private function initializeFilters(): void
    {
        $initializedFilters = [];

        foreach ($this->columns as $column) {
            if (!($column['filterable'] ?? false)) {
                continue;
            }

            $columnId = $column['id'] ?? $column['field'];

            if (($column['type'] ?? 'text') === 'date') {
                $existingValue = $this->filters[$columnId] ?? null;
                $initializedFilters[$columnId] = [
                    'from' => is_array($existingValue) ? ($existingValue['from'] ?? '') : '',
                    'to' => is_array($existingValue) ? ($existingValue['to'] ?? '') : '',
                ];
                continue;
            }

            $initializedFilters[$columnId] = (string)($this->filters[$columnId] ?? '');
        }

        $this->filters = $initializedFilters;
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

        $joinedAliases = [];

        foreach ($this->baseJoins as $join) {
            $type = strtolower((string)($join['type'] ?? 'left'));
            $path = (string)($join['path'] ?? '');
            $alias = (string)($join['alias'] ?? '');

            if ('' === $path || '' === $alias || in_array($alias, $joinedAliases, true)) {
                continue;
            }

            if ('inner' === $type) {
                $qb->innerJoin($path, $alias);
            } else {
                $qb->leftJoin($path, $alias);
            }

            $joinedAliases[] = $alias;
        }

        foreach ($this->baseWheres as $whereExpression) {
            if (is_string($whereExpression) && '' !== trim($whereExpression)) {
                $qb->andWhere($whereExpression);
            }
        }

        foreach ($this->baseParameters as $name => $value) {
            if (!is_string($name) || '' === $name) {
                continue;
            }
            $qb->setParameter($name, $value);
        }

        // Auto-join des relations utilisées dans les colonnes
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
        foreach ($this->filters as $filterKey => $value) {
            if (empty($value)) {
                continue;
            }

            $column = $this->findColumnById($filterKey) ?? $this->findColumn($filterKey);
            if (!$column || !($column['filterable'] ?? false)) {
                continue;
            }

            $field = $column['field'];

            $paramName = 'f_' . str_replace('-', '_', (string)$filterKey);

            // Si une expression de filtre personnalisée est définie
            if (!empty($column['filter_expression'])) {
                $expression = $column['filter_expression'];

                switch ($column['type'] ?? 'text') {
                    case 'text':
                        $qb->andWhere($qb->expr()->like($expression, ':' . $paramName))
                            ->setParameter($paramName, '%' . $value . '%');
                        break;

                    case 'select':
                    case 'boolean':
                        $qb->andWhere($expression . ' = :' . $paramName)
                            ->setParameter($paramName, $value);
                        break;

                    case 'entity':
                        $qb->andWhere($expression . ' = :' . $paramName)
                            ->setParameter($paramName, $value);
                        break;

                    case 'collection':
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
                        $qb->andWhere($fieldPath . ' = :' . $paramName)
                            ->setParameter($paramName, $value);
                        break;

                    case 'boolean':
                        $qb->andWhere($fieldPath . ' = :' . $paramName)
                            ->setParameter($paramName, '1' === (string)$value);
                        break;

                    case 'entity':
                        $associationPath = $this->getEntityAssociationPath($field);
                        if (null === $associationPath) {
                            continue 2;
                        }

                        $qb->andWhere('IDENTITY(' . $associationPath . ') = :' . $paramName)
                            ->setParameter($paramName, $value);
                        break;

                    case 'collection':
                        if (empty($column['entity'])) {
                            continue 2;
                        }

                        $entity = $this->resolveFilterEntity($column, $value);
                        if (null === $entity) {
                            continue 2;
                        }

                        $qb->andWhere(':' . $paramName . ' MEMBER OF ' . $fieldPath)
                            ->setParameter($paramName, $entity);
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

    public function getEntityChoices(array $column): array
    {
        $entityClass = $column['entity'] ?? null;
        if (!is_string($entityClass) || '' === $entityClass) {
            return [];
        }

        $entityLabel = $column['entity_label'] ?? '__toString';
        $repository = $this->entityManager->getRepository($entityClass);

        try {
            $entities = '__toString' !== $entityLabel
                ? $repository->findBy([], [$entityLabel => 'ASC'])
                : $repository->findAll();
        } catch (\Throwable) {
            $entities = $repository->findAll();
        }

        $choices = [];
        foreach ($entities as $entity) {
            $id = $this->getEntityIdentifier($entity);
            if (null === $id) {
                continue;
            }

            $label = $this->getEntityChoiceLabel($entity, $entityLabel);
            $choices[] = [
                'value' => $id,
                'label' => $label,
            ];
        }

        return $choices;
    }

    public function getEntityDisplayLabel(?object $entity, array $column): string
    {
        if (null === $entity) {
            return (string)($column['null_label'] ?? '');
        }

        return $this->getEntityChoiceLabel($entity, $column['entity_label'] ?? '__toString');
    }

    private function getEntityIdentifier(object $entity): int|string|null
    {
        $identifierValues = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);
        if (1 !== count($identifierValues)) {
            return null;
        }

        return array_values($identifierValues)[0];
    }

    private function getEntityChoiceLabel(object $entity, string $entityLabel): string
    {
        $fallbackLabel = sprintf('%s #%s', $entity::class, (string)($this->getEntityIdentifier($entity) ?? '?'));

        if ('__toString' === $entityLabel && method_exists($entity, '__toString')) {
            return (string)$entity;
        }

        $value = $this->getFieldValue($entity, $entityLabel);

        if (null === $value) {
            return $fallbackLabel;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return $fallbackLabel;
    }

    private function resolveFilterEntity(array $column, int|string $value): ?object
    {
        $entityClass = $column['entity'] ?? null;
        if (!is_string($entityClass) || '' === $entityClass) {
            return null;
        }

        $entity = $this->entityManager->getRepository($entityClass)->find($value);

        return is_object($entity) ? $entity : null;
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

    private function findColumnById(string $id): ?array
    {
        foreach ($this->columns as $column) {
            if (($column['id'] ?? null) === $id) {
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

    private function getEntityAssociationPath(string $field): ?string
    {
        $parts = explode('.', $field);
        if ([] === $parts) {
            return null;
        }

        // Pour un affichage `relation.libelle`, on filtre sur l'association `e.relation`.
        return 'e.' . $parts[0];
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
        $this->initializeFilters();
        $this->globalSearch = '';
        $this->page = 1;
    }

    #[LiveAction]
    public function toggleFilters(): void
    {
        $this->filtersOpen = !$this->filtersOpen;
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = $page;
    }
}
