<?php

namespace App\Service;

class DataTableBuilder
{
    private string $entityClass;
    private array $columns = [];
    private array $actions = [];
    private int $perPage = 20;
    private string $defaultSort = '';
    private string $defaultSortDirection = 'asc';

    public function setEntity(string $entityClass): self
    {
        $this->entityClass = $entityClass;
        return $this;
    }

    /**
     * Ajoute une colonne au tableau
     *
     * @param string $field Le champ de l'entité (support des relations: 'category.name')
     * @param array $options Options disponibles:
     *   - label: string - Label affiché (défaut: field en titlecase)
     *   - sortable: bool - Colonne triable (défaut: false)
     *   - filterable: bool - Colonne filtrable (défaut: false)
     *   - searchable: bool - Incluse dans la recherche globale (défaut: true)
     *   - type: string - Type de filtre: 'text', 'select', 'entity', 'date', 'boolean', 'collection' (défaut: 'text')
     *   - format: string - Format d'affichage: 'date', 'datetime', 'currency', 'boolean', 'badge', 'badges' (défaut: null)
     *   - sort_expression: string - Expression SQL pour le tri (ex: 'SIZE(e.formations)' ou '(SELECT COUNT(f) FROM Formation f WHERE f.mention = e)')
     *   - filter_expression: string - Expression SQL pour le filtre
     *   - choices: array - Choix pour type 'select' (défaut: [])
     *   - entity: string - Classe d'entité pour type 'entity'
     *   - entity_label: string - Propriété à afficher pour type 'entity' ou 'collection' (défaut: '__toString')
     *   - collection_property: string - Propriété à afficher dans une collection ManyToMany (défaut: '__toString')
     *   - badge_map: array - Mapping valeur => classe CSS pour le format 'badge' (ex: ['active' => 'success', 'inactive' => 'danger'])
     *   - badge_class: string - Classe CSS par défaut pour les badges (défaut: 'bg-secondary')
     *   - separator: string - Séparateur pour les collections (défaut: ', ')
     *   - template: string - Template Twig custom pour la cellule
     *   - class: string - Classes CSS pour la colonne
     */
    public function addColumn(string $field, array $options = []): self
    {
        $this->columns[] = array_merge([
            'id' => md5($field),
            'field' => $field,
            'label' => $this->generateLabel($field),
            'sortable' => false,
            'filterable' => false,
            'searchable' => true,
            'type' => 'text',
            'format' => null,
            'sort_expression' => null,
            'filter_expression' => null,
            'choices' => [],
            'entity' => null,
            'entity_label' => '__toString',
            'collection_property' => '__toString',
            'badge_map' => [],
            'badge_class' => 'bg-secondary',
            'separator' => ', ',
            'template' => null,
            'class' => '',
        ], $options);

        return $this;
    }

    private function generateLabel(string $field): string
    {
        $field = str_replace('.', ' ', $field);
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Ajoute une action "Dupliquer" standardisée
     */
    public function addDuplicateAction(string $route, array $options = []): self
    {
        return $this->addAction('duplicate', array_merge([
            'label' => 'Dupliquer',
            'route' => $route,
            'icon' => 'fal fa-copy',
            'class' => 'text-success',
        ], $options));
    }

    /**
     * Ajoute une action (bouton) pour chaque ligne
     *
     * @param string $name Nom de l'action
     * @param array $options Options:
     *   - label: string - Label du bouton
     *   - route: string - Nom de la route Symfony
     *   - route_params: array - Paramètres de route additionnels
     *   - icon: string - Classe d'icône Bootstrap/FontAwesome
     *   - class: string - Classes CSS du bouton (défaut: 'btn-sm btn-primary')
     *   - confirm: string - Message de confirmation
     *   - condition: callable - Fonction qui détermine si l'action est visible pour une ligne
     *   - modal: bool - Ouvrir dans une modal (défaut: false)
     *   - modal_size: string - Taille de la modal: 'sm', 'lg', 'xl' (défaut: 'lg')
     *   - modal_title: string - Titre de la modal (défaut: label de l'action)
     */
    public function addAction(string $name, array $options = []): self
    {
        $this->actions[$name] = array_merge([
            'label' => ucfirst($name),
            'route' => null,
            'route_params' => [],
            'icon' => null,
            'class' => 'btn-sm btn-primary',
            'confirm' => null,
            'condition' => null,
            'modal' => false,
            'modal_size' => 'lg',
            'modal_title' => null,
        ], $options);

        // Si modal_title n'est pas défini, utiliser le label
        if ($this->actions[$name]['modal'] && !$this->actions[$name]['modal_title']) {
            $this->actions[$name]['modal_title'] = $this->actions[$name]['label'];
        }

        return $this;
    }

    /**
     * Ajoute une action "Activer/Désactiver" standardisée
     */
    public function addToggleAction(string $route, array $options = []): self
    {
        return $this->addAction('toggle', array_merge([
            'label' => 'Activer/Désactiver',
            'route' => $route,
            'icon' => 'fal fa-toggle-on',
            'class' => 'text-warning',
        ], $options));
    }

    /**
     * Ajoute un set d'actions CRUD standard (Show, Edit, Delete)
     *
     * @param string $routePrefix Préfixe des routes (ex: 'product' générera 'product_show', 'product_edit', 'product_delete')
     * @param array $actions Actions à inclure: ['show', 'edit', 'delete'] (défaut: toutes)
     * @param array $options Options globales à appliquer à toutes les actions
     */
    public function addCrudActions(string $routePrefix, array $actions = ['show', 'edit', 'delete'], array $options = []): self
    {
        if (in_array('show', $actions, true)) {
            $this->addShowAction($routePrefix . '_show', $options['show'] ?? []);
        }

        if (in_array('edit', $actions, true)) {
            $this->addEditAction($routePrefix . '_edit', $options['edit'] ?? []);
        }

        if (in_array('delete', $actions, true)) {
            $this->addDeleteAction($routePrefix . '_delete', $options['delete'] ?? []);
        }

        return $this;
    }

    /**
     * Ajoute une action "Voir" standardisée
     */
    public function addShowAction(string $route, array $options = []): self
    {
        return $this->addAction('show', array_merge([
            'label' => 'Voir',
            'route' => $route,
            'icon' => 'fal fa-eye',
            'class' => 'text-info',
        ], $options));
    }

    /**
     * Ajoute une action "Modifier" standardisée
     */
    public function addEditAction(string $route, array $options = []): self
    {
        return $this->addAction('edit', array_merge([
            'label' => 'Modifier',
            'route' => $route,
            'icon' => 'fal fa-edit',
            'class' => 'text-warning',
            'modal' => false, // Peut être surchargé
        ], $options));
    }

    /**
     * Ajoute une action "Supprimer" standardisée
     */
    public function addDeleteAction(string $route, array $options = []): self
    {
        return $this->addAction('delete', array_merge([
            'label' => 'Supprimer',
            'route' => $route,
            'icon' => 'fal fa-trash-alt',
            'class' => 'text-danger',
            'confirm' => 'Êtes-vous sûr de vouloir supprimer cet élément ?',
        ], $options));
    }

    public function setPerPage(int $perPage): self
    {
        $this->perPage = $perPage;
        return $this;
    }

    public function setDefaultSort(string $field, string $direction = 'asc'): self
    {
        $this->defaultSort = $field;
        $this->defaultSortDirection = $direction;
        return $this;
    }

    public function build(): array
    {
        return [
            'entityClass' => $this->entityClass,
            'columns' => $this->columns,
            'actions' => $this->actions,
            'perPage' => $this->perPage,
            'sortField' => $this->defaultSort,
            'sortDirection' => $this->defaultSortDirection,
        ];
    }
}
