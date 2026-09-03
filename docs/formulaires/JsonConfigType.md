# JsonConfigType - Type de formulaire pour la configuration JSON

## Vue d'ensemble

Le `JsonConfigType` est un type de formulaire personnalisé qui permet de gérer des champs de configuration JSON de manière accessible aux non-développeurs. Il propose **deux modes d'édition** :

1. **Mode Assistant (Wizard)** : Interface graphique intuitive pour créer la configuration ligne par ligne
2. **Mode JSON** : Éditeur de texte brut pour les utilisateurs avancés

## Fonctionnalités

### Mode Assistant
- ✨ **Interface graphique** : Ajout de configurations sans connaître JSON
- 🎯 **Types de données** : Sélection du type (texte, nombre, booléen, tableau, objet)
- ➕ **Ajout/Suppression** : Boutons pour gérer les entrées
- 🔄 **Synchronisation** : Conversion automatique en JSON

### Mode JSON
- ✅ **Validation en temps réel** : Vérification automatique au blur
- 🎨 **Formatage automatique** : Bouton pour formater le JSON
- 📚 **Aide contextuelle** : Exemples et documentation intégrée
- 🚨 **Gestion d'erreurs** : Messages d'erreur clairs

## Utilisation

### Dans un FormType

```php
use App\Form\Type\JsonConfigType;

$builder->add('configuration', JsonConfigType::class, [
    'label' => 'Configuration',
    'required' => false,
    'help_text' => 'Configuration technique au format JSON',
]);
```

### Options disponibles

- `add_button_text` : Texte du bouton d'ajout (défaut: "Ajouter une configuration")
- `help_text` : Texte d'aide affiché au-dessus du champ (défaut: null)
- `required` : Indique si le champ est requis (défaut: false)

## Mode Assistant - Guide utilisateur

### Comment utiliser le wizard ?

1. **Cliquer sur "Mode Assistant"** pour afficher l'interface graphique
2. **Ajouter une configuration** :
   - Saisir la **clé** (ex: `api_url`)
   - Choisir le **type** (Texte, Nombre, Booléen, Tableau, Objet)
   - Saisir la **valeur** selon le type choisi
3. **Répéter** pour chaque configuration
4. **Cliquer sur "Appliquer et voir le JSON"** pour générer le JSON final

### Types de données disponibles

| Type | Description | Exemple de valeur |
|------|-------------|-------------------|
| **Texte** | Chaîne de caractères | `https://example.com` |
| **Nombre** | Nombre entier ou décimal | `30` ou `3.14` |
| **Booléen** | Vrai ou Faux | `true` / `false` |
| **Tableau** | Liste de valeurs | `["item1", "item2"]` |
| **Objet** | Structure JSON complexe | `{"clé": "valeur"}` |

### Exemples visuels

#### Configuration simple (texte et nombre)
```
Clé: api_url          Type: Texte      Valeur: https://api.example.com
Clé: timeout          Type: Nombre     Valeur: 30
Clé: enabled          Type: Booléen    Valeur: Vrai
```

**Résultat JSON :**
```json
{
  "api_url": "https://api.example.com",
  "timeout": 30,
  "enabled": true
}
```

#### Configuration avec tableau
```
Clé: allowed_methods  Type: Tableau    Valeur: ["GET", "POST", "PUT"]
```

**Résultat JSON :**
```json
{
  "allowed_methods": ["GET", "POST", "PUT"]
}
```

## Mode JSON - Pour utilisateurs avancés

### Édition directe

Les utilisateurs à l'aise avec JSON peuvent basculer sur "Mode JSON" pour :
- Éditer directement le JSON
- Utiliser le bouton "Formater JSON" pour embellir le code
- Valider la syntaxe en temps réel

### Exemple de données

```php
// En base de données (JSON)
{
  "api_url": "https://example.com/api",
  "timeout": 30,
  "enabled": true,
  "options": ["option1", "option2"]
}

// En PHP (array)
[
    'api_url' => 'https://example.com/api',
    'timeout' => 30,
    'enabled' => true,
    'options' => ['option1', 'option2']
]
```

## Exemple d'utilisation : PlateformeAdmission

```php
class PlateformeAdmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('configuration', JsonConfigType::class, [
                'label' => 'Configuration de la plateforme',
                'required' => false,
                'help_text' => 'Configuration technique de la plateforme au format JSON',
            ])
            ->add('definitionChamps', JsonConfigType::class, [
                'label' => 'Définition des champs',
                'required' => false,
                'help_text' => 'Définition des champs spécifiques pour cette plateforme',
            ]);
    }
}
```

## Architecture

### Fichiers concernés

- **Type de formulaire** : `src/Form/Type/JsonConfigType.php`
- **Transformer** : `src/Form/DataTransformer/JsonToKeyValueTransformer.php`
- **Template Twig** : `templates/communs/form_theme_v2.html.twig` (bloc `json_config_widget`)
- **Contrôleur Stimulus** : `assets/controllers/json-config_controller.js`

### Fonctionnement

1. **Transformation données** : Le `JsonToKeyValueTransformer` convertit le tableau PHP en chaîne JSON pour l'affichage et inversement
2. **Rendu dual** : Le bloc Twig affiche deux interfaces (wizard + JSON) avec onglets
3. **Synchronisation** : Le contrôleur Stimulus synchronise les deux modes
4. **Validation JS** : Validation, formatage et gestion des interactions

## Contrôleur Stimulus

Le contrôleur `json-config_controller.js` expose les actions suivantes :

### Mode Wizard
- `switchToWizard()` : Bascule vers le mode assistant
- `loadWizardFromJson()` : Charge les données JSON dans le wizard
- `addWizardRow()` : Ajoute une ligne de configuration
- `removeWizardRow()` : Supprime une ligne
- `updateJsonFromWizard()` : Met à jour le JSON depuis le wizard
- `onTypeChange()` : Gère le changement de type de donnée

### Mode JSON
- `switchToJson()` : Bascule vers le mode JSON
- `validate()` : Valide le JSON saisi
- `format()` : Formate le JSON de manière lisible
- `clear()` : Vide le champ (avec confirmation)

## Gestion des erreurs

- **JSON invalide** : Affiche un message d'erreur avec le détail de l'erreur
- **JSON vide** : Accepté et converti en tableau vide
- **Conversion impossible** : Exception levée par le transformer
- **Type inconnu** : Le wizard utilise "texte" par défaut

## Bonnes pratiques

1. **Documentation** : Toujours fournir un `help_text` pour expliquer le format attendu
2. **Validation** : Ajouter une validation Symfony si la structure JSON doit respecter un schéma précis
3. **Valeurs par défaut** : Initialiser avec un tableau vide `[]` plutôt que `null`
4. **Formation utilisateurs** : Montrer le mode wizard aux utilisateurs non techniques

## Workflow utilisateur recommandé

```
┌─────────────────┐
│ Utilisateur     │
│ non technique   │
└────────┬────────┘
         │
         ├──> Mode Assistant
         │    • Ajouter configuration ligne par ligne
         │    • Choisir types de données
         │    • Validation visuelle
         │
         ├──> Voir le JSON généré
         │    • Vérifier le résultat
         │    • Formater si nécessaire
         │
         └──> Sauvegarder

┌─────────────────┐
│ Utilisateur     │
│ technique       │
└────────┬────────┘
         │
         └──> Mode JSON
              • Édition directe
              • Copier/coller depuis doc
              • Validation automatique
```

## Extension future

Pour rendre le système encore plus accessible, on pourrait créer :

1. **Templates prédéfinis** : Bibliothèque de configurations courantes avec bouton "Charger un template"
2. **Import/Export** : Charger depuis un fichier JSON externe
3. **Validation de schéma** : Définir un schéma JSON Schema pour validation avancée
4. **Éditeur visuel avancé** : Interface drag-and-drop pour structures complexes
5. **Historique** : Garder un historique des modifications

## Exemple de validation personnalisée

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PlateformeAdmission
{
    #[Assert\Callback]
    public function validateConfiguration(ExecutionContextInterface $context): void
    {
        $config = $this->getConfiguration();
        
        // Vérifier qu'une clé obligatoire existe
        if (!isset($config['api_url'])) {
            $context->buildViolation('La clé "api_url" est obligatoire dans la configuration')
                ->atPath('configuration')
                ->addViolation();
        }
        
        // Vérifier le type d'une valeur
        if (isset($config['timeout']) && !is_numeric($config['timeout'])) {
            $context->buildViolation('Le timeout doit être un nombre')
                ->atPath('configuration')
                ->addViolation();
        }
    }
}
```

## Captures d'écran (descriptions)

### Mode Assistant
- Onglets en haut pour basculer entre modes
- Tableau avec colonnes : Clé | Type | Valeur | Actions
- Chaque ligne est éditable avec sélecteur de type
- Bouton "+ Ajouter une configuration" en bas
- Bouton "Appliquer et voir le JSON" pour finaliser

### Mode JSON
- Textarea avec coloration syntaxique
- Boutons "Formater JSON" et "Vider"
- Message de validation (vert si valide, rouge si erreur)
- Section d'aide dépliable en bas

## FAQ

**Q: Puis-je passer du mode assistant au mode JSON sans perdre mes données ?**  
R: Oui ! Les deux modes sont synchronisés. Cliquez sur "Appliquer et voir le JSON" pour mettre à jour.

**Q: Que se passe-t-il si je saisis un JSON invalide en mode JSON ?**  
R: Un message d'erreur s'affiche et le champ est entouré en rouge. Le wizard peut ne pas charger correctement.

**Q: Comment saisir un tableau ou un objet complexe en mode assistant ?**  
R: Sélectionnez le type "Tableau" ou "Objet" et saisissez directement le JSON dans la valeur (ex: `["a", "b"]`).

**Q: Est-ce que le mode assistant supporte les structures JSON imbriquées ?**  
R: Pour des structures simples, oui. Pour des structures très complexes, préférez le mode JSON.
