<?php

declare(strict_types=1);

namespace App\Service\Recherche;

use App\DTO\Recherche\DocumentRecherche;
use App\DTO\Recherche\ResultatRechercheParcours;
use App\DTO\Recherche\ResultatsRechercheParcours;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Enums\TypeParcoursEnum;
use App\Repository\FicheMatiereRepository;
use App\Repository\ParcoursRepository;

/**
 * Recherche approchée (tolérante aux fautes et aux accents) dans les parcours d'une campagne.
 *
 * Parcours classiques : intitulé + textes du parcours. Parcours par défaut : intitulé de la mention + textes de la
 * formation (et poursuites d'études du parcours).
 */
final readonly class RechercheParcours
{
    private const POIDS = [
        'intitule' => 3.0,
        'objectifsParcours' => 1.5,
        'objectifsFormation' => 1.5,
        'contenuFormation' => 1.2,
        'resultatsAttendus' => 1.0,
        'poursuitesEtudes' => 1.0,
    ];

    public function __construct(
        private FuzzySearch $fuzzySearch,
        private FuzzyMatcher $fuzzyMatcher,
        private ParcoursRepository $parcoursRepository,
        private FicheMatiereRepository $ficheMatiereRepository,
    ) {
    }

    /**
     * Faux si la saisie ne contient que des mots vides (« de la »), qui correspondraient à presque tous les parcours.
     */
    public function estSignificative(string $motCle): bool
    {
        return $this->fuzzyMatcher->termesRequete($motCle) !== [];
    }

    public function rechercher(string $motCle, CampagneCollecte $campagne): ResultatsRechercheParcours
    {
        $lignes = [];
        foreach ($this->parcoursRepository->findPourRechercheTexte($campagne) as $ligne) {
            // Un parcours peut avoir plusieurs DPE sur la campagne.
            $lignes[(int) $ligne['parcours_id']] ??= $ligne;
        }

        $documents = array_map(fn (array $ligne): DocumentRecherche => $this->document($ligne), array_values($lignes));
        $trouves = $this->fuzzySearch->rechercher($motCle, $documents, self::POIDS, ['intitule']);

        $nbFichesParParcours = $this->compterFichesMatieres(array_map('intval', $trouves->ids()), $motCle);

        $resultats = [];
        foreach ($trouves->resultats as $trouve) {
            $ligne = $lignes[(int) $trouve->id];
            $estDefaut = $ligne['parcours_libelle'] === Parcours::PARCOURS_DEFAUT;
            $typeParcours = $ligne['type_parcours'];

            $resultats[] = new ResultatRechercheParcours(
                parcoursId: (int) $trouve->id,
                estParcoursDefaut: $estDefaut,
                formationSlug: $ligne['formation_slug'],
                titre: $this->titre($ligne),
                sigle: $estDefaut ? $ligne['formation_sigle'] : $ligne['parcours_sigle'],
                typeParcoursLibelle: $typeParcours instanceof TypeParcoursEnum ? $typeParcours->getLabel() : null,
                champs: $trouve->champs,
                extrait: $trouve->extrait,
                champExtrait: $trouve->champExtrait,
                nbFichesMatieres: $nbFichesParParcours[(int) $trouve->id] ?? 0,
            );
        }

        return new ResultatsRechercheParcours(
            $resultats,
            $this->motsSaisis($motCle, $trouves->corrections),
            array_map('strval', array_keys($this->motsSaisis($motCle, array_fill_keys($trouves->termesSansCorrespondance, '')))),
        );
    }

    /**
     * Fiches matières du parcours dont la description correspond à la recherche.
     *
     * @return list<array{id: int, slug: ?string, libelle: ?string, description: ?string, parcours_id: int}>
     */
    public function fichesMatieresAssociees(int $parcoursId, string $motCle): array
    {
        $fiches = [];
        foreach ($this->ficheMatiereRepository->findPourRechercheParcours([$parcoursId]) as $fiche) {
            $fiches[(int) $fiche['id']] = $fiche;
        }

        $trouves = $this->fuzzySearch->rechercher($motCle, $this->documentsFiches($fiches), avecExtraits: false);

        return array_map(static fn (int|string $id): array => $fiches[(int) $id], $trouves->ids());
    }

    /**
     * @param list<int> $parcoursIds
     *
     * @return array<int, int> nombre de fiches matières trouvées par parcours
     */
    private function compterFichesMatieres(array $parcoursIds, string $motCle): array
    {
        $fiches = [];
        foreach ($this->ficheMatiereRepository->findPourRechercheParcours($parcoursIds) as $fiche) {
            $fiches[(int) $fiche['id']] = $fiche;
        }

        $nombres = [];
        foreach ($this->fuzzySearch->rechercher($motCle, $this->documentsFiches($fiches), avecExtraits: false)->ids() as $id) {
            $parcoursId = (int) $fiches[(int) $id]['parcours_id'];
            $nombres[$parcoursId] = ($nombres[$parcoursId] ?? 0) + 1;
        }

        return $nombres;
    }

    /**
     * @param array<int, array{description: ?string}> $fiches
     *
     * @return list<DocumentRecherche>
     */
    private function documentsFiches(array $fiches): array
    {
        $documents = [];
        foreach ($fiches as $id => $fiche) {
            $documents[] = new DocumentRecherche($id, ['description' => $fiche['description']]);
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $ligne
     */
    private function document(array $ligne): DocumentRecherche
    {
        if ($ligne['parcours_libelle'] === Parcours::PARCOURS_DEFAUT) {
            return new DocumentRecherche($ligne['parcours_id'], [
                'intitule' => $this->titre($ligne),
                'objectifsFormation' => $ligne['formation_objectifs'],
                'contenuFormation' => $ligne['formation_contenu'],
                'resultatsAttendus' => $ligne['formation_resultats_attendus'],
                'poursuitesEtudes' => $ligne['poursuitesEtudes'],
            ]);
        }

        return new DocumentRecherche($ligne['parcours_id'], [
            'intitule' => $this->titre($ligne),
            'objectifsParcours' => $ligne['objectifsParcours'],
            'contenuFormation' => $ligne['contenuFormation'],
            'resultatsAttendus' => $ligne['resultatsAttendus'],
            'poursuitesEtudes' => $ligne['poursuitesEtudes'],
        ]);
    }

    /**
     * Même libellé que Formation::getDisplayLong(), suivi du parcours s'il n'est pas le parcours par défaut.
     *
     * @param array<string, mixed> $ligne
     */
    private function titre(array $ligne): string
    {
        $titre = $ligne['type_diplome_libelle'] !== null ? $ligne['type_diplome_libelle'] . ' - ' : '';
        $titre .= $ligne['mention_libelle'] ?? $ligne['formation_mention_texte'] ?? '';
        if ($ligne['formation_sigle'] !== null && trim($ligne['formation_sigle']) !== '') {
            $titre .= ' (' . $ligne['formation_sigle'] . ')';
        }

        if ($ligne['parcours_libelle'] !== Parcours::PARCOURS_DEFAUT) {
            $titre .= ' - Parcours ' . $ligne['parcours_libelle'];
        }

        return $titre;
    }

    /**
     * Ré-associe des termes normalisés aux mots tels que saisis (« matématiques » plutôt que « matematiques »).
     *
     * @param array<string, string> $parTerme terme normalisé => valeur
     *
     * @return array<string, string> mot saisi => valeur
     */
    private function motsSaisis(string $motCle, array $parTerme): array
    {
        $resultat = [];
        foreach ($this->fuzzyMatcher->decouper($motCle) as [$mot]) {
            $normalise = $this->fuzzyMatcher->normaliser($mot);
            if (isset($parTerme[$normalise])) {
                $resultat[$mot] = $parTerme[$normalise];
            }
        }

        return $resultat;
    }
}
