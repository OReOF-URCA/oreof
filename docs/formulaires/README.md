# Formulaires JSON configurables : `JsonConfigType` + `DynamicFieldsType`

Quand lire : champ de configuration JSON, champs spécifiques par plateforme d'admission, ou nouveau type de formulaire
personnalisé.
À mettre à jour si : `src/Form/Type/{JsonConfigType,DynamicFieldsType}.php`, `JsonToKeyValueTransformer`, widget `json_config_widget`, `json-config_controller.js`, formulaires `PlateformeAdmission*Type`.

## Flux

1. **Définition** — `PlateformeAdmissionType` : `configuration` et `definitionChamps` en `JsonConfigType` →
   stocké dans `PlateformeAdmission::definitionChamps` (JSON).
2. **Génération** — `PlateformeAdmissionParametreType` : sur `FormEvents::PRE_SET_DATA`, ajoute `donneesSpecifiques`
   en `DynamicFieldsType` avec `field_definitions` = `$plateforme->getDefinitionChamps()`.
3. **Saisie** — valeurs stockées dans `PlateformeAdmissionParametre::donneesSpecifiques` (JSON, non typé en base ;
   validation uniquement côté formulaire). Lecture : `getDonneesSpecifiques()['cle'] ?? null`,
   Twig `parametre.donneesSpecifiques.cle`.

Ajouter un champ = modifier la définition JSON, sans migration ni déploiement. Structure complexe/imbriquée → créer un
FormType dédié plutôt que ce mécanisme.

## Fichiers

| Rôle | Fichier |
|---|---|
| Type JSON (parent `TextareaType`, prefix `json_config`) | `src/Form/Type/JsonConfigType.php` |
| Transformer tableau PHP ↔ chaîne JSON | `src/Form/DataTransformer/JsonToKeyValueTransformer.php` |
| Génération de champs | `src/Form/Type/DynamicFieldsType.php` |
| Widget (onglets Assistant / JSON) | bloc `json_config_widget` : `templates/communs/form_theme_v2.html.twig`, `templates/communs/form/_json_config_widget.html.twig` |
| Stimulus | `assets/controllers/json-config_controller.js` |

## `JsonConfigType`

Options : `add_button_text` (« Ajouter une configuration »), `help_text` (null), `required` (false). Deux modes
synchronisés : Assistant (lignes clé/type/valeur, types texte/nombre/booléen/tableau/objet ; type inconnu → texte) et
JSON brut (validation au blur, formatage). JSON vide → `[]` ; JSON invalide → exception du transformer. Initialiser
avec `[]` plutôt que `null` ; si la structure doit respecter un schéma, ajouter un `#[Assert\Callback]` sur l'entité.

Actions Stimulus : `switchToWizard`, `loadWizardFromJson`, `addWizardRow`, `removeWizardRow`, `updateJsonFromWizard`,
`onTypeChange`, `switchToJson`, `validate`, `format`, `clear`.

## `DynamicFieldsType` — schéma de définition

```json
{ "nom_du_champ": { "type": "text", "label": "…", "required": false, "help": "…", "placeholder": "…",
  "min": 0, "max": 100, "max_length": 255, "choices": {"Libellé": "valeur"}, "multiple": false,
  "expanded": false, "default": null, "attr": {} } }
```

| `type` | Type Symfony | Validation générée |
|---|---|---|
| `text`, `textarea` | Text/TextareaType | `Length(max_length)` + `maxlength` |
| `email` | EmailType | `Email` |
| `url` | UrlType | `Url` |
| `integer`, `number` | IntegerType | `GreaterThanOrEqual(min)`, `LessThanOrEqual(max)` + attrs |
| `float`, `decimal` | NumberType | idem |
| `checkbox`, `boolean` | CheckboxType | — |
| `date` | DateType | — |
| `choice`, `select` | ChoiceType | `choices`, `multiple`, `expanded` |
| autre | TextType | — |

`required: true` → `NotBlank`. `default` → option `data`. Non supporté : validation inter-champs, champs conditionnels.

Clés : explicites en snake_case (`url_inscription`, pas `url1`) ; toujours un `help` ; `min`/`max` sur les nombres ;
`required` seulement si réellement obligatoire. Exemples par plateforme : `exemples-plateformes.md`.

## Nouveau type de formulaire personnalisé

Classe dans `src/Form/Type/` (`buildForm`, `buildView` pour passer des options à la vue, `configureOptions`), bloc
`<prefix>_widget` dans le form theme, contrôleur Stimulus `assets/controllers/<nom>_controller.js`, test
`TypeTestCase`, puis ajouter une section ici.
