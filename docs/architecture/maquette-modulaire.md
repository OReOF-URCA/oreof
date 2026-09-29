# Architecture cible — maquette pédagogique modulaire

Quand lire : avant toute refonte de structure, validation, résolution d'attributs, rendu ou export de maquette.
Statut : **proposition, non implémentée** (`src/Maquette/` et `src/DiplomaProfile/` n'existent pas). Ne pas créer ces
briques sans demande explicite ; en revanche ne pas ajouter de nouveaux `if diplôme == …` qui iraient à l'encontre.
À mettre à jour si : début d'implémentation, ou modification des fichiers listés en « Fichiers prioritaires » / handlers de `src/TypeDiplome/`.

## Constat (rigidités actuelles)

- Hiérarchie implicite `Parcours > Annee > SemestreParcours/Semestre > Ue > ElementConstitutif > FicheMatiere`, variantes
  par champs (`ueParent`, `ecParent`, `natureUeEc`, `isLibre`, `isChoix`).
- Règles codées en dur : `Ue::display()` (cas `BUT`/`M2E`), `ParcoursExportController` (choix limités à 2 niveaux),
  `StructureSemestre::getAnnee()` (1..6), `GenereStructureParcours` (« 2 semestres = 1 année »).
- Priorité/héritage heures/ECTS/MCCC (parent EC, fiche matière, enfants identiques, éléments rattachés) concentrés dans
  `src/Classes/GetElementConstitutif.php`.
- Handlers de diplôme trop larges (structure + validation + MCCC + export + rendu) : `src/TypeDiplome/Handler/`
  (`University`, `But`, `M2E`, `Daeu`, `Du`) et `NonClassique/NonClassiqueHandler`.
- `TypeDiplome` porte des contraintes structurelles (bornes de semestres, nb UE/EC, obligations MCCC/ECTS, stage/projet/
  mémoire) insuffisantes pour des profils divergents. (Le métamodèle `NodeType`/`NodeTypeEnum` évoqué initialement a été
  retiré du code.)

## Principe

Le socle sait **orchestrer** une maquette (arbre de nœuds, attributs, résolveurs, moteur de règles, rendu) ; chaque
**profil de diplôme** déclare sa structure, ses règles d'héritage, de validation, d'affichage et ses exports. Le socle
n'impose ni « 1 année = 2 semestres », ni « semestre ⊃ UE ⊃ EC », ni le niveau des ECTS.

## Briques cibles

| Brique | Rôle |
|---|---|
| `MaquetteTree` / `MaquetteNode` | read model canonique construit depuis les entités existantes (type, label, code, parent/enfants, position, path, contexte, entité source) |
| Capacités de nœud | ce qu'un nœud peut porter : MCCC, heures, ECTS, coeff, compétences, quitus, enfants, choix, mutualisation, fiche |
| `NodeAttributeBag` | attributs normalisés (`hours.cm.pres`, `mccc`, `ects`, `coeff`, `choice.min/max`…), locaux/hérités/calculés |
| `AttributeResolverInterface` | `Hours/Ects/Mccc/Coefficient/Competency/ValidationRulesResolver` → valeur + source + état (`provided`, `inherited`, `computed`, `missing`, `invalid`) |
| `DiplomaProfileInterface` | `getStructureDefinition()`, `getAttributePolicy()`, `getValidationRuleset()`, `getDisplaySchema()`, `getExportStrategies()`, `getEditingPolicy()` |
| `RuleInterface` + `RuleEngine` | règles composables avec scope (parcours, année, semestre, nœud, relation) ; paquets socle + university + BUT + M2E remplaçant `ValideParcours*` |
| `DisplaySchema` | hiérarchie, profondeur, attributs visibles, composants Twig, labels ; les templates consomment `tree/node/node.attributes/node.validation/schema` |

Arborescence proposée : `src/Maquette/{Model,Definition,Resolver,Validation}/`,
`src/DiplomaProfile/{Profiles/Licence,But,M2E}/` (StructureDefinition, AttributePolicy, Ruleset, DisplaySchema,
ExportStrategy par profil).

## Conserver / faire évoluer

- Conserver : `TypeDiplomeResolver` comme point d'entrée (il renverra un profil riche), DTO de validation
  (`ValidationResult`, `ValidationIssueDto`), exports réellement spécifiques.
- Faire évoluer : `TypeDiplomeHandlerInterface` → façade légère ; `StructureParcours*` → builder de `MaquetteTree` ;
  `GetElementConstitutif` → résolveurs ; `ValideParcours*` → composition de règles ; contrôleurs/templates → modèle
  générique.

## Migration incrémentale

1. Read model `MaquetteTree` + builder depuis les entités ; DTO actuels gardés comme adaptateurs (aucune migration de
   données).
2. Extraire les politiques d'attributs de `GetElementConstitutif`, priorité heures/MCCC/ECTS configurable par profil.
3. Moteur de règles : licence d'abord, puis BUT et M2E.
4. Rendu par `DisplaySchema` ; supprimer les tests de type de diplôme dans entités/contrôleurs.
5. Édition pilotée par profil (création possible par niveau, boutons/formulaires générés).
6. Persistance générique native seulement si le read model est stabilisé.

Premier incrément recommandé : 1 + traversée récursive générique des choix dans les exports + extraction des résolveurs
+ `DiplomaProfileInterface` sans supprimer les handlers.

Risques : coexistence modèle/calcul/affichage/validation, exports très couplés aux DTO (prévoir des adaptateurs), règles
implicites dispersées (inventaire des règles par diplôme indispensable avant les étapes 3–4).

## Fichiers prioritaires

`src/Service/Parcours/GenereStructureParcours.php`, `src/DTO/Structure{Semestre,Ue,Ec}.php`,
`src/Classes/GetElementConstitutif.php`, `src/Entity/Ue.php`, `src/Controller/ParcoursExportController.php`,
`src/TypeDiplome/Handler/*Handler.php`, `src/TypeDiplome/Diplomes/*/ValideParcours*.php`,
`src/Service/Validation/SemesterValidationRefresher.php`.
