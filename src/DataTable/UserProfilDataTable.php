<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Classes\DataUserSession;
use App\Entity\User;
use App\Entity\UserProfil;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
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

#[AsDataTable(UserProfil::class)]
final class UserProfilDataTable extends AbstractAppDataTable
{
    public function __construct(
        private readonly Security $security,
        private readonly DataUserSession $dataUserSession,
    ) {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'userNom', 'dir' => 'asc']]);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $rootAlias = $qb->getRootAliases()[0];

        $qb->innerJoin(sprintf('%s.user', $rootAlias), 'u')
            ->innerJoin(sprintf('%s.profil', $rootAlias), 'p')
            ->andWhere('u.isEnable = :isEnable')
            ->andWhere('u.isDeleted = :isDeleted')
            ->andWhere(sprintf('(IDENTITY(%s.campagneCollecte) = :campagneId OR %s.campagneCollecte IS NULL)', $rootAlias, $rootAlias))
            ->setParameter('isEnable', true)
            ->setParameter('isDeleted', false)
            ->setParameter('campagneId', $this->dataUserSession->getCampagneCollecte()?->getId());

        $user = $this->security->getUser();
        $composanteId = null;
        if ($this->security->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $user instanceof User ? $user->getComposanteResponsableDpe()->first() : null,
        ]) && $user instanceof User) {
            foreach ($user->getUserProfils() as $centre) {
                if ($centre->getComposante() !== null) {
                    $composanteId = $centre->getComposante()->getId();
                    break;
                }
            }
        }

        if ($composanteId !== null) {
            $qb->leftJoin(sprintf('%s.formation', $rootAlias), 'f')
                ->leftJoin(sprintf('%s.parcours', $rootAlias), 'pa')
                ->leftJoin('pa.formation', 'pf')
                ->andWhere(sprintf('(
                    IDENTITY(%s.composante) = :composanteId
                    OR IDENTITY(f.composantePorteuse) = :composanteId
                    OR IDENTITY(pf.composantePorteuse) = :composanteId
                )', $rootAlias))
                ->setParameter('composanteId', $composanteId);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('user.nom')->label('Nom'))
            ->add(TextFilter::new('user.prenom')->label('Prénom'))
            ->add(TextFilter::new('user.username')->label('Login URCA'))
            ->add(TextFilter::new('profil.libelle')->label('Profil'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('userNom', 'Nom')
                ->setField('user.nom'),
            TextColumn::new('userPrenom', 'Prénom')
                ->setField('user.prenom'),
            TextColumn::new('userUsername', 'Login URCA')
                ->setField('user.username'),
            TextColumn::new('profilLibelle', 'Profil')
                ->setField('profil.libelle'),
            TemplateColumn::new('profilCentre', 'Type centre')
                ->setField('profil.centre')
                ->setTemplate('config/user_profil/_datatable_type_centre.html.twig'),
            TextColumn::new('displayCentre', 'Centre'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $profilsGestionAction = Action::new('profils_gestion', 'Gestion des profils')
            ->linkToRoute('app_user_profils_gestion', static fn(UserProfil $up): array => ['user' => $up->getUser()->getId()])
            ->htmlAttributes([
                'data-turbo' => 'true',
                'data-action' => 'click->modalturbo#open',
                'title' => 'Gestion des centres',
            ])
            ->icon(Icon::Key)
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-100');

        return $actions
            ->add($this->createShowAction('app_user_show', static fn(UserProfil $up): array => ['id' => $up->getUser()->getId()]))
            ->add($profilsGestionAction)
            ->add($this->createEditAction('app_user_edit', static fn(UserProfil $up): array => ['id' => $up->getUser()->getId()])->setPermission('ROLE_ADMIN'))
            ->alignment(ActionsAlignment::Right);
    }
}
