# DynamicFieldsType - Génération de formulaires dynamiques

## Vue d'ensemble

Le `DynamicFieldsType` permet de générer automatiquement des champs de formulaire à partir d'une définition JSON. C'est la **deuxième partie** du système JsonConfigType :

1. **JsonConfigType** : Permet de **définir** les champs dans un JSON
2. **DynamicFieldsType** : **Génère** un formulaire à partir de cette définition

## Workflow complet

### Étape 1 : Définir les champs (Admin/Config)

L'administrateur utilise le JsonConfigType pour définir les champs spécifiques à une plateforme :

```json
{
  "url_inscription": {
    "type": "url",
    "label": "URL d'inscription",
    "required": true,
    "help": "Lien vers le formulaire d'inscription en ligne"
  },
  "date_ouverture": {
    "type": "date",
    "label": "Date d'ouverture",
    "required": false
  },
  "capacite_max": {
    "type": "integer",
    "label": "Capacité maximale",
    "required": true,
    "min": 0,
    "max": 9999
  },
  "modalites": {
    "type": "choice",
    "label": "Modalités d'admission",
    "required": true,
    "choices": {
      "Dossier": "dossier",
      "Concours": "concours",
      "Entretien": "entretien"
    }
  }
}
```

### Étape 2 : Génération automatique du formulaire (Runtime)

Le système génère automatiquement un formulaire avec ces champs :

```php
// Dans PlateformeAdmissionParametreType
$form->add('donneesSpecifiques', DynamicFieldsType::class, [
    'field_definitions' => $plateforme->getDefinitionChamps(),
]);
```

**Résultat :** Un formulaire avec 4 champs configurés automatiquement !

## Structure de la définition JSON

### Schéma de base

```json
{
  "nom_du_champ": {
    "type": "type_symfony",
    "label": "Libellé visible",
    "required": true/false,
    "help": "Texte d'aide",
    "options_spécifiques": "..."
  }
}
```

### Types de champs supportés

| Type JSON | Type Symfony | Options spécifiques |
|-----------|--------------|---------------------|
| `text` | TextType | `max_length`, `placeholder` |
| `textarea` | TextareaType | `max_length`, `placeholder` |
| `email` | EmailType | - |
| `url` | UrlType | - |
| `integer`, `number` | IntegerType | `min`, `max` |
| `float`, `decimal` | NumberType | `min`, `max` |
| `checkbox`, `boolean` | CheckboxType | `default` |
| `date` | DateType | - |
| `choice`, `select` | ChoiceType | `choices`, `multiple`, `expanded` |

## Exemples de définitions

### Champ texte simple

```json
{
  "commentaire": {
    "type": "textarea",
    "label": "Commentaire",
    "required": false,
    "max_length": 500,
    "placeholder": "Saisissez vos remarques...",
    "help": "Maximum 500 caractères"
  }
}
```

### Champ numérique avec limites

```json
{
  "capacite": {
    "type": "integer",
    "label": "Capacité d'accueil",
    "required": true,
    "min": 1,
    "max": 1000,
    "help": "Nombre de places disponibles"
  }
}
```

### Champ de choix (select)

```json
{
  "statut": {
    "type": "choice",
    "label": "Statut du candidat",
    "required": true,
    "choices": {
      "En attente": "pending",
      "Accepté": "accepted",
      "Refusé": "rejected"
    }
  }
}
```

### Champ de choix multiple (checkboxes)

```json
{
  "options": {
    "type": "choice",
    "label": "Options souhaitées",
    "required": false,
    "multiple": true,
    "expanded": true,
    "choices": {
      "Hébergement": "hebergement",
      "Restauration": "restauration",
      "Transport": "transport"
    }
  }
}
```

### Champ email avec validation

```json
{
  "email_contact": {
    "type": "email",
    "label": "Email de contact",
    "required": true,
    "help": "Adresse email pour les notifications"
  }
}
```

## Utilisation complète : Plateforme d'admission

### Configuration de la plateforme (une fois)

Dans le formulaire PlateformeAdmission, définir les champs spécifiques :

**Mode Assistant :**
```
Clé: url_inscription
Type: Objet
Valeur: {"type":"url","label":"URL d'inscription","required":true}

Clé: capacite_max
Type: Objet  
Valeur: {"type":"integer","label":"Capacité max","required":true,"min":0}
```

**Ou Mode JSON :**
```json
{
  "url_inscription": {
    "type": "url",
    "label": "URL d'inscription",
    "required": true
  },
  "capacite_max": {
    "type": "integer",
    "label": "Capacité maximale",
    "required": true,
    "min": 0
  }
}
```

### Utilisation dans le formulaire de paramètres

```php
// src/Form/PlateformeAdmissionParametreType.php
public function buildForm(FormBuilderInterface $builder, array $options): void
{
    // Champs standards
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
            ]);
        }
    });
}
```

### Résultat pour l'utilisateur

Le formulaire affiche automatiquement :
- Les champs standards (capaciteGlobale, capaciteFi)
- Les champs spécifiques définis dans la configuration (url_inscription, capacite_max)
- Avec les validations appropriées (required, min, max, format email/url)

## Validation automatique

Le système génère automatiquement les contraintes de validation :

```php
// Depuis la définition
{
  "email": {
    "type": "email",
    "required": true
  }
}

// Génère automatiquement
new Assert\NotBlank()
new Assert\Email()
```

### Types de validation supportés

| Type | Validation automatique |
|------|------------------------|
| `email` | Format email valide |
| `url` | Format URL valide |
| `integer`, `number` | GreaterThanOrEqual (min), LessThanOrEqual (max) |
| `text`, `textarea` | Length (max_length) |
| `required: true` | NotBlank |

## Exemple complet : Parcoursup

### Définition (dans PlateformeAdmission)

```json
{
  "code_parcoursup": {
    "type": "text",
    "label": "Code Parcoursup",
    "required": true,
    "max_length": 20,
    "placeholder": "Ex: FOR_1234"
  },
  "url_fiche": {
    "type": "url",
    "label": "URL de la fiche formation",
    "required": true,
    "help": "Lien vers la fiche sur Parcoursup"
  },
  "date_debut_candidatures": {
    "type": "date",
    "label": "Début des candidatures",
    "required": true
  },
  "date_fin_candidatures": {
    "type": "date",
    "label": "Fin des candidatures",
    "required": true
  },
  "capacite_parcoursup": {
    "type": "integer",
    "label": "Capacité affichée sur Parcoursup",
    "required": true,
    "min": 1,
    "max": 9999
  },
  "modalites_selection": {
    "type": "choice",
    "label": "Modalités de sélection",
    "required": true,
    "choices": {
      "Dossier uniquement": "dossier",
      "Dossier + Entretien": "dossier_entretien",
      "Concours": "concours"
    }
  },
  "attendus_pedagogiques": {
    "type": "textarea",
    "label": "Attendus pédagogiques",
    "required": false,
    "max_length": 2000,
    "help": "Texte affiché aux candidats"
  }
}
```

### Formulaire généré automatiquement

Quand un utilisateur crée ou édite un `PlateformeAdmissionParametre` pour Parcoursup, le formulaire affiche :

1. ✅ Champ texte "Code Parcoursup" (max 20 caractères, requis)
2. ✅ Champ URL "URL de la fiche formation" (validation URL, requis)
3. ✅ Date picker "Début des candidatures" (requis)
4. ✅ Date picker "Fin des candidatures" (requis)
5. ✅ Nombre "Capacité affichée" (min 1, max 9999, requis)
6. ✅ Select "Modalités de sélection" avec 3 choix (requis)
7. ✅ Textarea "Attendus pédagogiques" (max 2000 caractères, optionnel)

**Tout cela sans écrire une seule ligne de code supplémentaire !**

## Gestion des données

### Sauvegarde

```php
// Les données sont automatiquement sérialisées en JSON
$parametre->setDonneesSpecifiques([
    'code_parcoursup' => 'FOR_1234',
    'url_fiche' => 'https://parcoursup.fr/...',
    'capacite_parcoursup' => 100,
    // ...
]);
```

### Lecture

```php
// Récupérer une valeur spécifique
$codeParcoursup = $parametre->getDonneesSpecifiques()['code_parcoursup'] ?? null;

// Ou dans Twig
{{ parametre.donneesSpecifiques.code_parcoursup }}
```

## Avantages

✅ **Flexibilité** : Chaque plateforme peut avoir ses propres champs  
✅ **Pas de migration BDD** : Ajout de champs sans modifier le schéma  
✅ **Validation automatique** : Types et contraintes gérés automatiquement  
✅ **Interface unifiée** : Même UX pour tous les formulaires dynamiques  
✅ **Maintenabilité** : La configuration est centralisée dans la définition JSON  

## Limitations et extensions futures

### Limitations actuelles

- Types de champs limités aux types Symfony de base
- Pas de validation inter-champs (ex: date_fin > date_debut)
- Pas de champs conditionnels (afficher champ B si champ A = X)

### Extensions possibles

1. **Champs conditionnels**
```json
{
  "autre_modalite": {
    "type": "text",
    "label": "Précisez",
    "required": true,
    "show_if": {
      "field": "modalites_selection",
      "value": "autre"
    }
  }
}
```

2. **Validation custom**
```json
{
  "date_fin": {
    "type": "date",
    "label": "Date de fin",
    "validate": "after:date_debut"
  }
}
```

3. **Templates de formulaires**
```php
// Charger un template prédéfini
"parcoursup_standard" => [...],
"ecandidat_master" => [...],
```

## Bonnes pratiques

1. **Nommer clairement les champs** : `url_inscription` plutôt que `url1`
2. **Fournir de l'aide** : Toujours ajouter `help` pour expliquer l'usage
3. **Valider au niveau du schéma** : Définir `min`, `max`, `required` dans la définition
4. **Tester la définition** : Vérifier que le formulaire se génère correctement
5. **Documenter** : Garder une doc des champs disponibles par plateforme

## FAQ

**Q: Puis-je ajouter des champs sans redéployer l'application ?**  
R: Oui ! Il suffit de modifier la définition JSON dans PlateformeAdmission.

**Q: Comment gérer des champs très complexes (ex: formulaire imbriqué) ?**  
R: Pour des cas très complexes, préférez créer un FormType dédié plutôt que d'utiliser le système dynamique.

**Q: Les données sont-elles typées en base ?**  
R: Non, elles sont stockées en JSON (type `array`). La validation se fait au niveau du formulaire.

**Q: Puis-je réutiliser DynamicFieldsType ailleurs ?**  
R: Oui ! Il suffit de passer une définition via `field_definitions`.
