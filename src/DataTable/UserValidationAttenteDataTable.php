<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
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
final class UserValidationAttenteDataTable extends AbstractAppDataTable
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
            ->andWhere(sprintf('%s.dateDemande IS NOT NULL', $rootAlias))
            ->andWhere(sprintf('%s.isDeleted = :isDeleted', $rootAlias))
            ->setParameter('isEnable', false)
            ->setParameter('isDeleted', false);

        $user = $this->security->getUser();
        $isDpe = false;
        $composanteId = null;

        if ($this->security->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $user instanceof User ? $user->getComposanteResponsableDpe()->first() : null,
        ]) && $user instanceof User) {
            $isDpe = true;
            foreach ($user->getUserProfils() as $userProfil) {
                if ($userProfil->getComposante() !== null) {
                    $composanteId = $userProfil->getComposante()->getId();
                    break;
                }
            }
        }

        if ($isDpe && $composanteId !== null) {
            $qb->andWhere(sprintf('IDENTITY(%s.composanteDemande) = :composanteId', $rootAlias))
                ->setParameter('composanteId', $composanteId);
        } elseif ($isDpe) {
            $qb->andWhere('1 = 0');
        } else {
            $qb->andWhere(sprintf('%s.isValideAdministration = :isValideAdmin', $rootAlias))
                ->setParameter('isValideAdmin', false);
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
            TemplateColumn::new('userProfils', 'Centre(s) / Droits')
                ->setTemplate('config/user_profil/_datatable_attente_centres.html.twig'),
            DateColumn::new('dateDemande', 'Date demande')
                ->setFormat('d/m/Y H:i'),
            TextColumn::new('serviceDemande', 'Service/fonction'),
            TemplateColumn::new('composanteDemande', 'Validé DPE ?')
                ->setTemplate('config/user_profil/_datatable_attente_dpe.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $showAttenteAction = Action::new('show_attente', 'Voir et gérer')
            ->linkToRoute('app_user_show_attente', static fn(User $u): array => ['id' => $u->getId()])
            ->htmlAttributes([
                'data-turbo' => 'true',
                'data-action' => 'click->modalturbo#open',
                'title' => 'Voir les détails de l\'utilisateur',
            ])
            ->icon(Icon::Eye)
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-cyan-300 bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-100');

        return $actions
            ->add($showAttenteAction)
            ->add($this->createDeleteAction(
                'app_user_profil_delete_demande',
                static fn(User $u): array => ['id' => $u->getId()],
                csrfToken: static fn(User $u): string => 'delete-user-' . $u->getId()
            )->setPermission('ROLE_ADMIN'))
            ->alignment(ActionsAlignment::Right);
    }
}
