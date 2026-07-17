# Documentation des Formulaires Personnalisés

Ce dossier contient la documentation des types de formulaires personnalisés créés pour ORéOF.

## Types de formulaires disponibles

### 📝 JsonConfigType
Type de formulaire pour gérer des champs de configuration JSON de manière accessible.

**Fichier :** `JsonConfigType.md`

**Fonctionnalités principales :**
- ✨ Mode Assistant : Interface graphique intuitive
- 🔧 Mode JSON : Éditeur de texte brut pour utilisateurs avancés
- ✅ Validation en temps réel
- 🎨 Formatage automatique

**Cas d'usage :**
- Configuration de plateformes d'admission
- Paramètres techniques d'API
- Définition de champs spécifiques
- Toute configuration stockée en JSON

**Pour commencer :**
1. Lire `JsonConfigType.md` pour la documentation technique
2. Lire `Guide-Mode-Assistant.md` pour le guide utilisateur

---

### 🎯 DynamicFieldsType
Type de formulaire qui génère automatiquement des champs à partir d'une définition JSON.

**Fichier :** `DynamicFieldsType.md`

**Fonctionnalités principales :**
- 🚀 Génération automatique de champs
- 🔍 Validation automatique selon les types
- 🎨 Support de 10+ types de champs (text, email, url, date, choice, etc.)
- ⚙️ Configuration sans code

**Workflow complet :**
1. **Définir** les champs avec JsonConfigType (ex: dans PlateformeAdmission)
2. **Générer** automatiquement le formulaire avec DynamicFieldsType
3. **Utiliser** le formulaire généré (ex: dans PlateformeAdmissionParametre)

**Cas d'usage :**
- Champs spécifiques par plateforme d'admission
- Formulaires configurables sans développement
- Données métier variables selon le contexte

**Pour commencer :**
1. Lire `DynamicFieldsType.md` pour la documentation technique
2. Consulter `Exemples-Definitions-Plateformes.md` pour des exemples concrets

---

## Guides utilisateurs

### 🎓 Guide du Mode Assistant
Guide pas à pas pour les utilisateurs non techniques.

**Fichier :** `Guide-Mode-Assistant.md`

**Contenu :**
- Introduction au mode assistant
- Explication des types de données
- Exemples concrets
- Astuces et conseils
- Erreurs courantes à éviter

**Public cible :** Utilisateurs métier, administrateurs fonctionnels

---

### 📚 Exemples de définitions par plateforme
Bibliothèque d'exemples prêts à l'emploi pour les plateformes d'admission.

**Fichier :** `Exemples-Definitions-Plateformes.md`

**Plateformes couvertes :**
- 📚 Parcoursup
- 🎓 eCandidat
- 🎯 MonMaster
- 🌍 Études en France (CEF)
- 🏢 Admission Post-Bac (générique)

**Public cible :** Administrateurs système, configurateurs

---

## Architecture complète du système

### Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYSTÈME DE FORMULAIRES DYNAMIQUES             │
└─────────────────────────────────────────────────────────────────┘

1. DÉFINITION (Admin/Config)
   ┌──────────────────────────┐
   │  JsonConfigType          │  Mode Assistant ou Mode JSON
   │  • Définir les champs    │  ────────────────────────────>
   │  • Spécifier les types   │  {"url": {"type": "url", ...}}
   │  • Configurer validation │
   └──────────────────────────┘
              ↓
   Sauvegarde dans PlateformeAdmission->definitionChamps (JSON)
              ↓
2. GÉNÉRATION (Runtime)
   ┌──────────────────────────┐
   │  DynamicFieldsType       │  Lecture du schéma JSON
   │  • Lire la définition    │  ────────────────────────────>
   │  • Créer les champs      │  Génère FormBuilderInterface
   │  • Appliquer validation  │
   └──────────────────────────┘
              ↓
3. UTILISATION (Formulaire)
   ┌──────────────────────────┐
   │  PlateformeAdmission     │  Formulaire dynamique affiché
   │  ParametreType           │  ────────────────────────────>
   │  • Afficher les champs   │  avec validation automatique
   │  • Sauvegarder données   │
   └──────────────────────────┘
              ↓
   Sauvegarde dans PlateformeAdmissionParametre->donneesSpecifiques (JSON)
```

### Exemple concret : Parcoursup

**Étape 1 : Configuration (une fois)**
```
Admin → PlateformeAdmission "Parcoursup"
     → Champ "Définition des champs" (JsonConfigType)
     → Définit : code_parcoursup (text), url_fiche (url), capacite (integer)
     → Sauvegarde
```

**Étape 2 : Utilisation (à chaque saisie)**
```
Utilisateur → PlateformeAdmissionParametre
           → Le formulaire affiche automatiquement :
              - Code Parcoursup (input text avec max_length)
              - URL fiche (input url avec validation)
              - Capacité (input number avec min/max)
           → Validation automatique
           → Sauvegarde dans donneesSpecifiques
```

### Documentation technique

Les formulaires personnalisés dans ORéOF suivent cette architecture :

```
src/Form/Type/
├── JsonConfigType.php             # Définition de configuration
├── DynamicFieldsType.php          # Génération dynamique
│
src/Form/DataTransformer/
├── JsonToKeyValueTransformer.php  # Transformation PHP↔JSON
│
templates/communs/
├── form_theme_v2.html.twig        # Rendu Twig
│   ├── block json_config_widget   # Widget avec wizard
│   └── block dynamic_fields_widget (standard)
│
assets/controllers/
├── json-config_controller.js      # Logique wizard + validation
│
docs/formulaires/
├── JsonConfigType.md              # Doc technique JsonConfig
├── DynamicFieldsType.md           # Doc technique DynamicFields
├── Guide-Mode-Assistant.md        # Guide utilisateur
├── Exemples-Definitions-Plateformes.md  # Bibliothèque d'exemples
└── README.md                      # Ce fichier
```

### Bonnes pratiques

#### 1. Création d'un nouveau type
```php
<?php
namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Configuration du formulaire
    }
    
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Transmission des options à la vue
        $view->vars['mon_option'] = $options['mon_option'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mon_option' => 'valeur_par_defaut',
        ]);
    }
}
```

#### 2. Utilisation de DynamicFieldsType

```php
// Dans votre FormType
use App\Form\Type\DynamicFieldsType;

$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
    $data = $event->getData();
    $form = $event->getForm();
    
    // Récupérer la définition des champs depuis votre entité
    $fieldDefinitions = $data->getSomeEntity()->getDefinitionChamps() ?? [];
    
    if (!empty($fieldDefinitions)) {
        $form->add('customFields', DynamicFieldsType::class, [
            'field_definitions' => $fieldDefinitions,
        ]);
    }
});
```

#### 3. Structure de la définition JSON

```json
{
  "nom_du_champ": {
    "type": "text|email|url|integer|float|checkbox|date|choice",
    "label": "Libellé affiché",
    "required": true|false,
    "help": "Texte d'aide",
    "placeholder": "Texte indicatif",
    "min": 0,
    "max": 100,
    "max_length": 255,
    "choices": {"Label": "value"},
    "default": "valeur_par_defaut"
  }
}
```

#### 4. Ajout d'un bloc Twig
```twig
{# templates/communs/form_theme_v2.html.twig #}
{% block mon_type_widget %}
    <div data-controller="mon-type">
        {# Rendu personnalisé #}
        {{ block('widget_attributes') }}
    </div>
{% endblock %}
```

#### 5. Contrôleur Stimulus
```javascript
// assets/controllers/mon-type_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['element'];
    
    connect() {
        // Initialisation
    }
}
```

#### 6. Documentation
- Créer `docs/formulaires/MonType.md` (technique)
- Créer `docs/formulaires/Guide-MonType.md` (utilisateur)
- Mettre à jour ce README

---

## Utilisation dans les FormTypes

### Exemple : PlateformeAdmissionType

```php
use App\Form\Type\JsonConfigType;

class PlateformeAdmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('configuration', JsonConfigType::class, [
                'label' => 'Configuration de la plateforme',
                'required' => false,
                'help_text' => 'Configuration technique au format JSON',
            ])
            ->add('definitionChamps', JsonConfigType::class, [
                'label' => 'Définition des champs spécifiques',
                'required' => false,
                'help_text' => 'Schéma des champs personnalisés pour cette plateforme',
            ]);
    }
}
```

### Exemple : PlateformeAdmissionParametreType

```php
use App\Form\Type\DynamicFieldsType;

class PlateformeAdmissionParametreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('capaciteGlobale', IntegerType::class)
            ->add('capaciteFi', IntegerType::class);
        
        // Champs dynamiques selon la plateforme
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            $plateforme = $data->getPlateforme();
            $fieldDefinitions = $plateforme->getDefinitionChamps() ?? [];
            
            if (!empty($fieldDefinitions)) {
                $form->add('donneesSpecifiques', DynamicFieldsType::class, [
                    'field_definitions' => $fieldDefinitions,
                    'label' => 'Données spécifiques à ' . $plateforme->getLibelle(),
                ]);
            }
        });
    }
}
```

---

## Tests

### Tests unitaires recommandés

```php
// tests/Form/Type/MonTypeTest.php
class MonTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        // Test de soumission avec données valides
    }
    
    public function testCustomOptions(): void
    {
        // Test des options personnalisées
    }
}
```

### Tests pour DynamicFieldsType

```php
class DynamicFieldsTypeTest extends TypeTestCase
{
    public function testGeneratesTextFieldFromDefinition(): void
    {
        $definition = [
            'my_field' => [
                'type' => 'text',
                'label' => 'My Field',
                'required' => true,
            ]
        ];
        
        $form = $this->factory->create(DynamicFieldsType::class, null, [
            'field_definitions' => $definition,
        ]);
        
        $this->assertTrue($form->has('my_field'));
    }
}
```

---

## Contribution

Pour ajouter un nouveau type de formulaire personnalisé :

1. **Créer les fichiers** selon l'architecture ci-dessus
2. **Documenter** : Créer la documentation technique et le guide utilisateur
3. **Tester** : Ajouter des tests unitaires
4. **Mettre à jour** ce README avec le nouveau type

---

## Index des fichiers

| Fichier | Description | Public |
|---------|-------------|--------|
| `JsonConfigType.md` | Documentation technique JsonConfigType | Développeurs |
| `DynamicFieldsType.md` | Documentation technique DynamicFieldsType | Développeurs |
| `Guide-Mode-Assistant.md` | Guide utilisateur mode assistant | Utilisateurs |
| `Exemples-Definitions-Plateformes.md` | Bibliothèque d'exemples de définitions | Administrateurs |
| `README.md` | Ce fichier - Index de la documentation | Tous |

---

## Liens utiles

- [Documentation Symfony Forms](https://symfony.com/doc/current/forms.html)
- [Documentation Stimulus](https://stimulus.hotwired.dev/)
- [Documentation Tailwind CSS](https://tailwindcss.com/docs) (pour le styling)
- [Documentation principale ORéOF](../README.md)

---

**Dernière mise à jour :** 2026-06-10
