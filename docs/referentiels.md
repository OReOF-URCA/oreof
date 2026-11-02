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
- `ModaliteEnseignementType` : ChoiceType alimenté dynamiquement par le référentiel `modalite_enseignement`, gérant les valeurs actives et la préservation des valeurs inactives existantes.
- `DureeParcoursUniteType` : ChoiceType alimenté dynamiquement par le référentiel `duree_parcours_unite`, gérant les valeurs actives et la préservation des valeurs inactives existantes.
- `NiveauLangueType` : ChoiceType alimenté dynamiquement par le référentiel `niveau_langue` (CECRL), gérant les valeurs actives et la préservation des valeurs inactives existantes.
- `NiveauFormationType` : ChoiceType alimenté dynamiquement par le référentiel `niveau_formation` (entrées/sorties), gérant les valeurs actives et la préservation des valeurs inactives existantes.
- Accesseurs entités (`Parcours`, `FicheMatiere`, `ElementConstitutif`, `Formation`) : `getModaliteEnseignementValue()`, `getDureeParcoursUniteValue()`, `getNiveauFrancaisValue()`, `getNiveauEntreeValue()`, `getNiveauSortieValue()` (les anciens getters Enum sont dépréciés et renvoient `null` pour un code personnalisé).
- Helpers d'adaptation : `ModaliteEnseignement`, `DureeParcoursUnite`, `NiveauLangue` et `NiveauFormation` fournissent `key()` et `legacyCase()` pour convertir ou extraire en toute sécurité les valeurs canoniques.
- Twig : `referentiel_label('modalite_enseignement', entity.modaliteEnseignementValue)`, `referentiel_label('duree_parcours_unite', parcours.dureeParcoursUniteValue)`, `referentiel_label('niveau_langue', parcours.niveauFrancaisValue)`, `referentiel_label('niveau_formation', formation.niveauEntreeValue)`.

## Compatibilité

Les codes stables et valeurs historiques ne sont jamais éditables via le CRUD. Les valeurs système peuvent être désactivées, mais pas supprimées.
Dans l'approche additive (ex. `ModaliteEnseignement`, `DureeParcoursUnite`, `NiveauLangue`, `NiveauFormation`) :
- Une colonne dédiée (`modaliteEnseignement_referentiel`, `duree_parcours_unite_referentiel`, `niveau_francais_referentiel`, `niveau_entree_referentiel`, `niveau_sortie_referentiel`) stocke le code canonique du référentiel.
- L'ancienne colonne enum est maintenue pour rétrocompatibilité tant qu'un équivalent existe.
- Un code personnalisé n'a pas d'équivalent Enum : le getter historique renvoie `null` et les setters historiques ignorent l'effacement accidentel.
