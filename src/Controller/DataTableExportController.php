<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/DataTableExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/12/2025 10:46
 */


namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Routing\Attribute\Route;

class DataTableExportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }

    #[Route('/datatable/export/csv', name: 'datatable_export')]
    public function exportCsv(Request $request): Response
    {
        // Récupérer les données de l'état du composant depuis la session ou la requête
        $entityClass = $request->query->get('entityClass');
        $columns = json_decode($request->query->get('columns', '[]'), true);
        $filters = json_decode($request->query->get('filters', '[]'), true);
        $globalSearch = $request->query->get('globalSearch', '');
        $sortField = $request->query->get('sortField', '');
        $sortDirection = $request->query->get('sortDirection', 'asc');

        if (!$entityClass) {
            throw new \InvalidArgumentException('Entity class is required');
        }

        // Créer la requête avec tous les filtres appliqués
        $qb = $this->createQueryBuilder($entityClass, $columns);
        $this->applyFilters($qb, $columns, $filters);
        $this->applyGlobalSearch($qb, $columns, $globalSearch);
        $this->applySorting($qb, $columns, $sortField, $sortDirection);

        $results = $qb->getQuery()->getResult();

        // Créer le fichier CSV
        $response = new StreamedResponse(function () use ($results, $columns) {
            $handle = fopen('php://output', 'w+');

            // Ajouter le BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // En-têtes
            $headers = array_map(fn($col) => $col['label'] ?? $col['field'], $columns);
            fputcsv($handle, $headers, ';');

            // Données
            foreach ($results as $item) {
                $row = [];
                foreach ($columns as $column) {
                    $value = $this->getFieldValue($item, $column['field']);

                    // Formater selon le type/format
                    if ($value instanceof \DateTimeInterface) {
                        $format = $column['format'] === 'datetime' ? 'd/m/Y H:i' : 'd/m/Y';
                        $value = $value->format($format);
                    } elseif (is_bool($value)) {
                        $value = $value ? 'Oui' : 'Non';
                    } elseif ($column['type'] === 'collection' && $value) {
                        // Gérer les collections ManyToMany
                        if (is_iterable($value)) {
                            $items = [];
                            foreach ($value as $subItem) {
                                $property = $column['collection_property'] ?? '__toString';
                                if ($property === '__toString') {
                                    $items[] = (string)$subItem;
                                } else {
                                    $getter = 'get' . ucfirst($property);
                                    if (method_exists($subItem, $getter)) {
                                        $items[] = $subItem->$getter();
                                    }
                                }
                            }
                            $value = implode($column['separator'] ?? ', ', $items);
                        }
                    } elseif (is_object($value)) {
                        $value = method_exists($value, '__toString') ? (string)$value : '';
                    } elseif ($column['format'] === 'currency' && is_numeric($value)) {
                        $value = number_format($value, 2, ',', ' ') . ' €';
                    } elseif ($column['format'] === 'badge' && isset($column['choices'][$value])) {
                        $value = $column['choices'][$value];
                    }

                    $row[] = $value ?? '';
                }
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $entityShortName = (new \ReflectionClass($entityClass))->getShortName();
        $filename = sprintf(
            'export_%s_%s.csv',
            strtolower($entityShortName),
            date('Y-m-d_His')
        );

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    private function createQueryBuilder(string $entityClass, array $columns): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')->from($entityClass, 'e');

        // Auto-join des relations utilisées dans les colonnes
        $joinedAliases = [];
        foreach ($columns as $column) {
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

    private function applyFilters(QueryBuilder $qb, array $columns, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if (empty($value) && $value !== '0') {
                continue;
            }

            $column = $this->findColumn($columns, $field);
            if (!$column || !($column['filterable'] ?? false)) {
                continue;
            }

            $paramName = str_replace('.', '_', $field);
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
                    if (is_array($value)) {
                        if (!empty($value['from'])) {
                            $qb->andWhere($fieldPath . ' >= :' . $paramName . '_from')
                                ->setParameter($paramName . '_from', new \DateTime($value['from']));
                        }
                        if (!empty($value['to'])) {
                            $qb->andWhere($fieldPath . ' <= :' . $paramName . '_to')
                                ->setParameter($paramName . '_to', new \DateTime($value['to']));
                        }
                    }
                    break;

                case 'boolean':
                    $qb->andWhere($fieldPath . ' = :' . $paramName)
                        ->setParameter($paramName, (bool)$value);
                    break;
            }
        }
    }

    private function findColumn(array $columns, string $field): ?array
    {
        foreach ($columns as $column) {
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

    private function applyGlobalSearch(QueryBuilder $qb, array $columns, string $search): void
    {
        if (empty($search)) {
            return;
        }

        $searchableColumns = array_filter($columns, fn($col) => $col['searchable'] ?? true);

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
            $qb->andWhere($orX)->setParameter('globalSearch', '%' . $search . '%');
        }
    }

    private function applySorting(QueryBuilder $qb, array $columns, string $sortField, string $sortDirection): void
    {
        if (empty($sortField)) {
            return;
        }

        $column = $this->findColumn($columns, $sortField);
        if (!$column || !($column['sortable'] ?? false)) {
            return;
        }

        $fieldPath = $this->getFieldPath($sortField);
        $qb->orderBy($fieldPath, strtoupper($sortDirection));
    }

    private function getFieldValue(object $entity, string $field): mixed
    {
        $parts = explode('.', $field);
        $value = $entity;

        foreach ($parts as $part) {
            if ($value === null) {
                return null;
            }

            $getter = 'get' . ucfirst($part);
            if (method_exists($value, $getter)) {
                $value = $value->$getter();
            } else {
                return null;
            }
        }

        return $value;
    }
}
