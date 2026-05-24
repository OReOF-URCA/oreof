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
    private array $baseJoins = [];
    private array $baseWheres = [];
    private array $baseParameters = [];
    private array $selection = [
        'enabled' => false,
        'name' => 'ids[]',
        'valueField' => 'id',
        'inputClass' => 'check-all',
        'allId' => 'check-all',
    ];

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
     *   - type: string - Type de filtre/affichage: 'text', 'select', 'entity', 'date', 'boolean', 'collection' (défaut: 'text')
     *   - format: string - Format d'affichage: 'date', 'datetime', 'currency', 'boolean', 'badge', 'badges', 'entity_badge' (défaut: null)
     *   - sort_expression: string - Expression SQL pour le tri (ex: 'SIZE(e.formations)' ou '(SELECT COUNT(f) FROM Formation f WHERE f.mention = e)')
     *   - filter_expression: string - Expression SQL pour le filtre
     *   - choices: array - Choix pour type 'select' (défaut: [])
     *   - entity: string - Classe d'entité pour type 'entity'
     *   - entity_label: string - Propriété à afficher pour type 'entity' ou 'collection' (défaut: '__toString')
     *   - null_label: string - Texte affiché si une relation `entity` est nulle (ex: 'Commune')
     *   - collection_property: string - Propriété à afficher dans une collection ManyToMany/OneToMany (défaut: '__toString')
     *     Pour une collection filtrable, utiliser `field` = nom de l'association (ex: `typeDiplomes`),
     *     `type` = `collection`, `filterable` = true, `entity` = classe liée, `entity_label` = libellé à afficher.
     *   - badge_map: array - Mapping valeur => classe CSS pour le format 'badge' (ex: ['active' => 'success', 'inactive' => 'danger'])
     *   - badge_class: string - Classe CSS par défaut pour les badges (défaut: 'bg-secondary')
     *   - null_badge_class: string - Classe CSS du badge quand une relation `entity` est nulle (défaut: 'bg-secondary')
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
            'null_label' => null,
            'collection_property' => '__toString',
            'badge_map' => [],
            'badge_class' => 'bg-secondary',
            'null_badge_class' => 'bg-secondary',
            'separator' => ', ',
            'template' => null,
            'class' => '',
            'is_html' => false,
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
            'method' => 'duplicate',
            'class' => 'inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100',
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
     *   - class: string - Classes CSS Tailwind du bouton
     *   - confirm: string - Message de confirmation
     *   - condition: callable - Fonction qui détermine si l'action est visible pour une ligne
     *   - modal: bool - Ouvrir dans une modal (défaut: false)
     *   - modal_size: string - Taille de la modal: 'sm', 'lg', 'xl' (défaut: 'lg')
     *   - modal_title: string - Titre de la modal (défaut: label de l'action)
     *   - method: string - Méthode HTTP spéciale: 'delete' (stimulus crud#delete), 'duplicate' (stimulus crud#duplicate), null = lien standard
     */
    public function addAction(string $name, array $options = []): self
    {
        $this->actions[$name] = array_merge([
            'label' => ucfirst($name),
            'route' => null,
            'route_params' => [],
            'icon' => null,
            'class' => 'inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50',
            'confirm' => null,
            'condition' => null,
            'modal' => false,
            'modal_size' => 'lg',
            'modal_title' => null,
            'method' => null,
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
            'class' => 'inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-100',
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
            'icon' => 'icon:info',
            'class' => 'inline-flex items-center gap-1 rounded-md border border-cyan-300 bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-100',
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
            'class' => 'inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-100',
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
            'method' => 'delete',
            'class' => 'inline-flex items-center gap-1 rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100',
            'confirm' => 'Êtes-vous sûr de vouloir supprimer cet élément ?',
        ], $options));
    }

    public function setPerPage(int $perPage): self
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * Ajoute un join de base appliqué à toutes les requêtes du DataTable.
     */
    public function addBaseJoin(string $type, string $path, string $alias): self
    {
        $this->baseJoins[] = [
            'type' => strtolower($type),
            'path' => $path,
            'alias' => $alias,
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE de base (en plus des filtres UI).
     */
    public function addBaseWhere(string $expression): self
    {
        $this->baseWheres[] = $expression;
        return $this;
    }

    /**
     * Ajoute un paramètre de base pour les clauses WHERE.
     */
    public function addBaseParameter(string $name, mixed $value): self
    {
        $this->baseParameters[$name] = $value;
        return $this;
    }

    public function setDefaultSort(string $field, string $direction = 'asc'): self
    {
        $this->defaultSort = $field;
        $this->defaultSortDirection = $direction;
        return $this;
    }

    /**
     * Active une colonne de sélection par checkbox avec un checkbox "tout sélectionner".
     *
     * Options:
     *  - name: string (défaut: ids[])
     *  - valueField: string (défaut: id)
     *  - inputClass: string (défaut: check-all)
     *  - allId: string (défaut: check-all)
     */
    public function enableCheckboxSelection(array $options = []): self
    {
        $this->selection = array_merge($this->selection, [
            'enabled' => true,
        ], $options);

        return $this;
    }

    public function disableCheckboxSelection(): self
    {
        $this->selection['enabled'] = false;

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
            'baseJoins' => $this->baseJoins,
            'baseWheres' => $this->baseWheres,
            'baseParameters' => $this->baseParameters,
            'selection' => $this->selection,
        ];
    }
}
