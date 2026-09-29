<?php

declare(strict_types=1);

namespace App\DataTable;

use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Enum\StyleFramework;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\DataTable;

abstract class AbstractAppDataTable extends AbstractDataTable
{
    public const BTN_SHOW_CLASS = 'inline-flex items-center gap-1 rounded-md border border-info-300 bg-info-50 px-2.5 py-1 text-xs font-semibold text-info-700 transition hover:bg-info-100 dark:border-info-500 dark:bg-info-900 dark:text-info-300 dark:hover:border-info-300 dark:hover:text-info-100';
    public const BTN_EDIT_CLASS = 'inline-flex items-center gap-1 rounded-md border border-warning-300 bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700 transition hover:bg-warning-100 dark:border-warning-500 dark:bg-warning-900 dark:text-warning-300 dark:hover:border-warning-300 dark:hover:text-warning-100';
    public const BTN_DUPLICATE_CLASS = 'inline-flex items-center gap-1 rounded-md border border-success-300 bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 transition hover:bg-success-100 dark:border-success-500 dark:bg-success-900 dark:text-success-300 dark:hover:border-success-300 dark:hover:text-success-100';
    public const BTN_DELETE_CLASS = 'inline-flex items-center gap-1 rounded-md border border-danger-300 bg-danger-50 px-2.5 py-1 text-xs font-semibold text-danger-700 transition hover:bg-danger-100 dark:border-danger-500 dark:bg-danger-900 dark:text-danger-300 dark:hover:border-danger-300 dark:hover:text-danger-100';

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->processing()
            ->stateSave()
            ->showHeaderResetButton()
            ->pageLength(20)
            ->lengthMenu([10, 20, 50, 100])
            ->styleFramework(StyleFramework::DataTables);
    }

    /**
     * Crée une action "Voir" standardisée
     */
    protected function createShowAction(
        string $routeName,
        callable $routeParameters,
        string $label = 'Voir',
        bool $modal = true,
        Icon|string|null $icon = Icon::Eye
    ): Action {
        $action = Action::new('show', $label, self::BTN_SHOW_CLASS)
            ->linkToRoute($routeName, $routeParameters);

        if ($icon !== null && $icon !== '') {
            $action->icon($icon);
        }

        $attrs = [
            'data-turbo-prefetch' => 'false',
            'data-turbo-preload' => 'false',
        ];

        if ($modal) {
            $attrs['data-action'] = 'click->modalturbo#open';
            $attrs['data-turbo'] = 'true';
        }

        $action->htmlAttributes($attrs);

        return $action;
    }

    /**
     * Crée une action "Modifier" standardisée
     */
    protected function createEditAction(
        string $routeName,
        callable $routeParameters,
        string $label = 'Modifier',
        bool $modal = true,
        Icon|string|null $icon = Icon::Pencil
    ): Action {
        $action = Action::edit($label, self::BTN_EDIT_CLASS)
            ->linkToRoute($routeName, $routeParameters);

        if ($icon !== null && $icon !== '') {
            $action->icon($icon);
        }

        $attrs = [
            'data-turbo-prefetch' => 'false',
            'data-turbo-preload' => 'false',
        ];

        if ($modal) {
            $attrs['data-action'] = 'click->modalturbo#open';
            $attrs['data-turbo'] = 'true';
        }

        $action->htmlAttributes($attrs);

        return $action;
    }

    /**
     * Crée une action "Dupliquer" standardisée
     */
    protected function createDuplicateAction(
        string $routeName,
        callable $routeParameters,
        string $label = 'Dupliquer',
        Icon|string|null $icon = Icon::Copy
    ): Action {
        $action = Action::new('duplicate', $label, self::BTN_DUPLICATE_CLASS)
            ->linkToRoute($routeName, $routeParameters)
            ->htmlAttributes([
                'data-turbo' => 'true',
                'data-turbo-prefetch' => 'false',
                'data-turbo-preload' => 'false',
            ]);

        if ($icon !== null && $icon !== '') {
            $action->icon($icon);
        }

        return $action;
    }

    /**
     * Crée une action "Supprimer" standardisée
     */
    protected function createDeleteAction(
        string $routeName,
        callable $routeParameters,
        string $label = '',
        Icon|string|null $icon = Icon::Trash2,
        ?string $confirm = 'Êtes-vous sûr de vouloir supprimer cet élément ?',
        string|callable|null $csrfToken = null
    ): Action {
        $action = Action::delete($label, self::BTN_DELETE_CLASS)
            ->askConfirmation($confirm)
            ->linkToRoute($routeName, $routeParameters)
            ->htmlAttributes([
                'data-turbo-prefetch' => 'false',
                'data-turbo-preload' => 'false',
            ]);

        if (null !== $csrfToken) {
            $action->asAjaxRequest($csrfToken, 'DELETE');
        }

        if ($icon !== null && $icon !== '') {
            $action->icon($icon);
        }

        return $action;
    }
}
