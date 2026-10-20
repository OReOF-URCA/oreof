# Référentiels administrables

Cette première étape est additive : aucune colonne métier, Enum, route publique ou export historique n'est modifié.

## Installation

1. Appliquer les migrations Doctrine : `php bin/console doctrine:migrations:migrate`.
2. Importer les valeurs historiques : `php bin/console app:referentiels:import`.
3. Ouvrir `/administration/referentiels` avec un compte `ROLE_ADMIN`.

La commande d'import est relançable : elle ajoute les valeurs absentes sans écraser les libellés modifiés par l'administration.

## Utilisation

- `ReferentielManager::find('modalite_enseignement', 'presentielle')`
- `ReferentielManager::findByLegacyValue('modalite_enseignement', 0)`
- `LegacyEnumMapper::fromEnum('modalite_enseignement', ModaliteEnseignementEnum::PRESENTIELLE)`
- `ReferentielChoiceType` : option obligatoire `referentiel`, résultat de type `ReferentielValeur`. Ne pas brancher directement ce champ sur une propriété historique typée Enum.
- Twig : `referentiel_label('modalite_enseignement', 'presentielle')` et `referentiel_valeurs('regime_inscription')`.

## Compatibilité

Les codes stables et valeurs historiques ne sont jamais éditables via le CRUD. Les valeurs système peuvent être désactivées, mais pas supprimées. Les Enum et champs actuels restent en place. La migration des relations Doctrine et des API sera traitée dans des PR distinctes.
