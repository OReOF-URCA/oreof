<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/ChangementMutualisation/ChangesetDetector.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/12/2025 12:23
 */

// src/Service/ChangesetDetector.php
namespace App\Service\ChangementMutualisation;

use Doctrine\ORM\EntityManagerInterface;

class ChangesetDetector
{
    // Définir vos champs critiques ici
    private const array CHAMPS_IMPACTANTS = [
        'heures' => 'Nombre d\'heures',
        'mccc' => 'Modalités de contrôle',
        'ects' => 'Crédits ECTS',
        'coefficient' => 'Coefficient'
    ];

    public function __construct(
        private readonly EntityManagerInterface $em
    )
    {
    }

    /**
     * Détecte les changements sur une entité
     * Retourne uniquement les champs qui ont vraiment changé
     */
    public function detecterChangements(object $entity): array
    {
        // Forcer le calcul des changements
        $this->em->getUnitOfWork()->computeChangeSets();

        // Récupérer le changeset
        $changeset = $this->em->getUnitOfWork()->getEntityChangeSet($entity);

        // Filtrer les valeurs nulles identiques et nettoyer
        $changementsReels = [];
        foreach ($changeset as $champ => $valeurs) {
            $ancienne = $valeurs[0] ?? null;
            $nouvelle = $valeurs[1] ?? null;

            // Ignorer si les deux valeurs sont identiques (même null)
            if ($this->sontValeursDifferentes($ancienne, $nouvelle)) {
                $changementsReels[$champ] = [
                    'ancienne' => $ancienne,
                    'nouvelle' => $nouvelle,
                    'label' => self::CHAMPS_IMPACTANTS[$champ] ?? ucfirst($champ)
                ];
            }
        }

        return $changementsReels;
    }

    /**
     * Compare deux valeurs en gérant les cas particuliers
     */
    private function sontValeursDifferentes(mixed $ancienne, mixed $nouvelle): bool
    {
        // Cas 1: Les deux sont null
        if ($ancienne === null && $nouvelle === null) {
            return false;
        }

        // Cas 2: Une seule est null
        if ($ancienne === null || $nouvelle === null) {
            return true;
        }

        // Cas 3: Objets DateTime
        if ($ancienne instanceof \DateTimeInterface && $nouvelle instanceof \DateTimeInterface) {
            return $ancienne->getTimestamp() !== $nouvelle->getTimestamp();
        }

        // Cas 4: Comparaison simple
        return $ancienne !== $nouvelle;
    }

    /**
     * Vérifie si un changeset contient des champs impactants
     */
    public function contientChangementsImpactants(array $changements): bool
    {
        return !empty($this->filtrerChangementsImpactants($changements));
    }

    /**
     * Filtre uniquement les changements impactants
     */
    public function filtrerChangementsImpactants(array $changements): array
    {
        return array_filter(
            $changements,
            fn($champ) => array_key_exists($champ, self::CHAMPS_IMPACTANTS),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Crée un hash unique basé sur les champs modifiés
     * Permet de détecter si deux modifications touchent les mêmes champs
     */
    public function creerHashChangements(array $changements): string
    {
        // Extraire uniquement les noms de champs (pas les valeurs)
        $champs = array_keys($changements);
        sort($champs); // Trier pour garantir le même hash peu importe l'ordre

        return md5(implode('|', $champs));
    }

    /**
     * Formatte les changements pour l'affichage
     */
    public function formatterChangementsPourAffichage(array $changements): array
    {
        $formatted = [];

        foreach ($changements as $champ => $valeurs) {
            $label = $valeurs['label'] ?? ucfirst($champ);
            $ancienne = $this->formatterValeur($valeurs['ancienne']);
            $nouvelle = $this->formatterValeur($valeurs['nouvelle']);

            $formatted[] = [
                'champ' => $champ,
                'label' => $label,
                'ancienne' => $ancienne,
                'nouvelle' => $nouvelle,
                'description' => sprintf('%s : %s → %s', $label, $ancienne, $nouvelle)
            ];
        }

        return $formatted;
    }

    /**
     * Formatte une valeur pour l'affichage
     */
    private function formatterValeur(mixed $valeur): string
    {
        if ($valeur === null) {
            return '(vide)';
        }

        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('d/m/Y H:i');
        }

        if (is_bool($valeur)) {
            return $valeur ? 'Oui' : 'Non';
        }

        if (is_array($valeur)) {
            return implode(', ', $valeur);
        }

        return (string)$valeur;
    }

    /**
     * Retourne la liste des champs impactants configurés
     */
    public function getChampsImpactants(): array
    {
        return self::CHAMPS_IMPACTANTS;
    }
}
