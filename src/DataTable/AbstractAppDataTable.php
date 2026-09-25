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
    public const BTN_SHOW_CLASS = 'inline-flex items-center gap-1 rounded-md border border-cyan-300 bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-100';
    public const BTN_EDIT_CLASS = 'inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-100';
    public const BTN_DUPLICATE_CLASS = 'inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100';
    public const BTN_DELETE_CLASS = 'inline-flex items-center gap-1 rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100';

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
