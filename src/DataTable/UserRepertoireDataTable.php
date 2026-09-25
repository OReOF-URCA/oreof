<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDataTable(User::class)]
final class UserRepertoireDataTable extends AbstractAppDataTable
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'nom', 'dir' => 'asc']]);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $rootAlias = $qb->getRootAliases()[0];

        $qb->andWhere(sprintf('%s.isEnable = :isEnable', $rootAlias))
            ->andWhere(sprintf('%s.isDeleted = :isDeleted', $rootAlias))
            ->andWhere(sprintf('%s.userProfils IS EMPTY', $rootAlias))
            ->setParameter('isEnable', true)
            ->setParameter('isDeleted', false);

        $user = $this->security->getUser();
        $composanteId = null;
        if ($this->security->isGranted('MANAGE', ['route' => 'app_composante', 'subject' => 'composante']) && $user instanceof User) {
            foreach ($user->getUserProfils() as $centre) {
                if ($centre->getComposante() !== null) {
                    $composanteId = $centre->getComposante()->getId();
                    break;
                }
            }
        }

        if ($composanteId !== null) {
            $qb->leftJoin(sprintf('%s.userProfils', $rootAlias), 'up_filter')
                ->leftJoin('up_filter.formation', 'uf_f')
                ->leftJoin('up_filter.parcours', 'uf_pa')
                ->leftJoin('uf_pa.formation', 'uf_pf')
                ->andWhere('(
                    IDENTITY(up_filter.composante) = :composanteId
                    OR IDENTITY(uf_f.composantePorteuse) = :composanteId
                    OR IDENTITY(uf_pf.composantePorteuse) = :composanteId
                )')
                ->setParameter('composanteId', $composanteId);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('nom')->label('Nom'))
            ->add(TextFilter::new('prenom')->label('Prénom'))
            ->add(TextFilter::new('email')->label('Email'))
            ->add(TextFilter::new('username')->label('Login URCA'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('nom', 'Nom'),
            TextColumn::new('prenom', 'Prénom'),
            TextColumn::new('email', 'Email'),
            TextColumn::new('username', 'Login URCA'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $profilsGestionAction = Action::new('profils_gestion', 'Gestion des accès')
            ->linkToRoute('app_user_profils_gestion', static fn(User $u): array => ['user' => $u->getId()])
            ->htmlAttributes([
                'data-turbo' => 'true',
                'data-action' => 'click->modalturbo#open',
                'title' => 'Gestion des accès',
            ])
            ->icon(Icon::Key)
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-100');

        return $actions
            ->add($this->createShowAction('app_user_show', static fn(User $u): array => ['id' => $u->getId()]))
            ->add($profilsGestionAction)
            ->add($this->createEditAction('app_user_edit', static fn(User $u): array => ['id' => $u->getId()])->setPermission('ROLE_ADMIN'))
            ->add($this->createDeleteAction(
                'app_user_delete',
                static fn(User $u): array => ['id' => $u->getId()],
                csrfToken: static fn(User $u): string => 'delete-user-' . $u->getId()
            )->setPermission('ROLE_ADMIN'))
            ->alignment(ActionsAlignment::Right);
    }
}
