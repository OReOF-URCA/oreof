<?php
namespace App\Referentiel;

use App\Entity\Referentiel;
use App\Entity\ReferentielValeur;
use Doctrine\ORM\EntityManagerInterface;

final class ReferentielManager
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function getReferentiel(string $code): ?Referentiel
    {
        return $this->em->getRepository(Referentiel::class)->findOneBy(['code' => $code]);
    }

    /** @return list<ReferentielValeur> */
    public function getValeurs(string $referentielCode, bool $includeInactive = false): array
    {
        $referentiel = $this->getReferentiel($referentielCode);
        if ($referentiel === null) { return []; }
        $criteria = ['referentiel' => $referentiel];
        if (!$includeInactive) { $criteria['actif'] = true; }
        return $this->em->getRepository(ReferentielValeur::class)->findBy($criteria, ['ordre' => 'ASC', 'libelle' => 'ASC']);
    }

    public function find(string $referentielCode, string $code): ?ReferentielValeur
    {
        $referentiel = $this->getReferentiel($referentielCode);
        return $referentiel === null ? null : $this->em->getRepository(ReferentielValeur::class)->findOneBy(['referentiel' => $referentiel, 'code' => $code]);
    }

    public function findByLegacyValue(string $referentielCode, string|int $value): ?ReferentielValeur
    {
        $referentiel = $this->getReferentiel($referentielCode);
        return $referentiel === null ? null : $this->em->getRepository(ReferentielValeur::class)->findOneBy(['referentiel' => $referentiel, 'legacyValue' => (string) $value]);
    }

    /**
     * Persisted values for legacy regimes are the original enum backed values.
     * Custom regimes use their stable referential code.
     *
     * @return array<string, string> persisted key => display label
     */
    public function choices(string $referentielCode): array
    {
        $choices = [];
        foreach ($this->getValeurs($referentielCode) as $value) {
            $key = $value->getLegacyValue() ?? $value->getCode();
            $choices[$key] = $value->getLibelle();
        }

        return $choices;
    }

    public function label(string $referentielCode, string|int|null $value): ?string
    {
        if ($value === null) { return null; }
        return ($this->find($referentielCode, (string) $value) ?? $this->findByLegacyValue($referentielCode, $value))?->getLibelle();
    }
}
