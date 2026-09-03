# Exemples de définitions de champs par plateforme

Ce document présente des exemples concrets de définitions de champs pour les principales plateformes d'admission.

## 📚 Parcoursup

Configuration complète pour la plateforme Parcoursup :

```json
{
  "code_formation": {
    "type": "text",
    "label": "Code formation Parcoursup",
    "required": true,
    "max_length": 20,
    "placeholder": "FOR_12345",
    "help": "Code unique de la formation sur Parcoursup"
  },
  "url_fiche_formation": {
    "type": "url",
    "label": "URL de la fiche formation",
    "required": true,
    "help": "Lien vers la fiche sur le site Parcoursup"
  },
  "date_ouverture_candidatures": {
    "type": "date",
    "label": "Date d'ouverture des candidatures",
    "required": true
  },
  "date_fermeture_candidatures": {
    "type": "date",
    "label": "Date de fermeture des candidatures",
    "required": true
  },
  "capacite_affichee": {
    "type": "integer",
    "label": "Capacité affichée sur Parcoursup",
    "required": true,
    "min": 1,
    "max": 9999,
    "help": "Nombre de places visibles par les candidats"
  },
  "taux_minimum_boursiers": {
    "type": "integer",
    "label": "Taux minimum de boursiers (%)",
    "required": false,
    "min": 0,
    "max": 100
  },
  "modalites_selection": {
    "type": "choice",
    "label": "Modalités de sélection",
    "required": true,
    "choices": {
      "Dossier uniquement": "dossier",
      "Dossier + Entretien": "dossier_entretien",
      "Dossier + Tests": "dossier_tests",
      "Concours": "concours"
    }
  },
  "attendus_pedagogiques": {
    "type": "textarea",
    "label": "Attendus pédagogiques",
    "required": false,
    "max_length": 2000,
    "help": "Texte présenté aux candidats (max 2000 caractères)"
  },
  "criteres_generaux": {
    "type": "textarea",
    "label": "Critères généraux d'examen des vœux",
    "required": false,
    "max_length": 1500
  },
  "acces_handicap": {
    "type": "checkbox",
    "label": "Formation accessible aux personnes en situation de handicap",
    "required": false,
    "default": true
  },
  "contact_formation": {
    "type": "email",
    "label": "Email de contact de la formation",
    "required": false,
    "help": "Email affiché aux candidats pour poser des questions"
  }
}
```

## 🎓 eCandidat

Configuration pour la plateforme eCandidat :

```json
{
  "code_ecandidat": {
    "type": "text",
    "label": "Code campagne eCandidat",
    "required": true,
    "max_length": 50,
    "help": "Identifiant unique de la campagne sur eCandidat"
  },
  "url_candidature": {
    "type": "url",
    "label": "URL de candidature",
    "required": true,
    "help": "Lien direct vers le formulaire de candidature"
  },
  "type_campagne": {
    "type": "choice",
    "label": "Type de campagne",
    "required": true,
    "choices": {
      "Formation initiale": "FI",
      "Formation continue": "FC",
      "Apprentissage": "APP",
      "VAE": "VAE"
    }
  },
  "nb_voeux_max": {
    "type": "integer",
    "label": "Nombre de vœux maximum par candidat",
    "required": false,
    "min": 1,
    "max": 10,
    "default": 3
  },
  "pieces_obligatoires": {
    "type": "textarea",
    "label": "Liste des pièces obligatoires",
    "required": false,
    "max_length": 1000,
    "placeholder": "CV, Lettre de motivation, Diplômes...",
    "help": "Une pièce par ligne"
  },
  "pieces_complementaires": {
    "type": "textarea",
    "label": "Liste des pièces complémentaires",
    "required": false,
    "max_length": 1000,
    "help": "Pièces optionnelles demandées aux candidats"
  },
  "frais_dossier": {
    "type": "float",
    "label": "Frais de dossier (€)",
    "required": false,
    "min": 0,
    "max": 500
  },
  "autorise_candidatures_multiples": {
    "type": "checkbox",
    "label": "Autoriser les candidatures multiples",
    "required": false,
    "default": false
  }
}
```

## 🎯 MonMaster

Configuration pour la plateforme MonMaster :

```json
{
  "code_monmaster": {
    "type": "text",
    "label": "Code MonMaster",
    "required": true,
    "max_length": 30
  },
  "url_fiche": {
    "type": "url",
    "label": "URL de la fiche formation",
    "required": true
  },
  "domaine_principal": {
    "type": "choice",
    "label": "Domaine principal",
    "required": true,
    "choices": {
      "Arts, Lettres, Langues": "ALL",
      "Droit, Économie, Gestion": "DEG",
      "Sciences Humaines et Sociales": "SHS",
      "Sciences, Technologies, Santé": "STS"
    }
  },
  "mention_master": {
    "type": "text",
    "label": "Mention du Master",
    "required": true,
    "max_length": 200
  },
  "parcours_master": {
    "type": "text",
    "label": "Parcours",
    "required": false,
    "max_length": 200
  },
  "capacite_m1": {
    "type": "integer",
    "label": "Capacité Master 1",
    "required": true,
    "min": 1,
    "max": 500
  },
  "capacite_m2": {
    "type": "integer",
    "label": "Capacité Master 2",
    "required": true,
    "min": 1,
    "max": 500
  },
  "acces_selec": {
    "type": "choice",
    "label": "Accès",
    "required": true,
    "choices": {
      "Sélectif en M1 et M2": "M1_M2",
      "Sélectif en M2 uniquement": "M2",
      "Non sélectif": "NON_SELECT"
    }
  },
  "prerequis_academiques": {
    "type": "textarea",
    "label": "Prérequis académiques",
    "required": false,
    "max_length": 1500,
    "help": "Diplômes ou compétences requises"
  },
  "debouches_professionnels": {
    "type": "textarea",
    "label": "Débouchés professionnels",
    "required": false,
    "max_length": 1500
  }
}
```

## 🌍 Études en France (CEF)

Configuration pour Campus France / Études en France :

```json
{
  "code_cef": {
    "type": "text",
    "label": "Code CEF",
    "required": true,
    "max_length": 20,
    "help": "Code unique de la formation sur la plateforme Études en France"
  },
  "niveau_formation": {
    "type": "choice",
    "label": "Niveau de formation",
    "required": true,
    "choices": {
      "Licence 1": "L1",
      "Licence 2": "L2",
      "Licence 3": "L3",
      "Master 1": "M1",
      "Master 2": "M2",
      "Doctorat": "D"
    }
  },
  "frais_inscription_etudiants_etrangers": {
    "type": "float",
    "label": "Frais d'inscription étudiants internationaux (€)",
    "required": true,
    "min": 0
  },
  "aide_au_logement": {
    "type": "checkbox",
    "label": "Aide au logement disponible",
    "required": false,
    "default": false
  },
  "cours_francais": {
    "type": "checkbox",
    "label": "Cours de français proposés",
    "required": false,
    "default": false
  },
  "niveau_francais_requis": {
    "type": "choice",
    "label": "Niveau de français requis",
    "required": false,
    "choices": {
      "Aucun": "none",
      "A2": "A2",
      "B1": "B1",
      "B2": "B2",
      "C1": "C1",
      "C2": "C2"
    }
  },
  "enseignement_anglais": {
    "type": "checkbox",
    "label": "Enseignement disponible en anglais",
    "required": false,
    "default": false
  },
  "quotas_pays": {
    "type": "textarea",
    "label": "Quotas par pays",
    "required": false,
    "max_length": 500,
    "help": "Format: Pays:nombre (un par ligne)"
  }
}
```

## 🏢 Admission Post-Bac (générique)

Configuration générique pour des admissions internes :

```json
{
  "url_dossier": {
    "type": "url",
    "label": "URL du dossier de candidature",
    "required": false,
    "help": "Lien vers le formulaire ou la page d'information"
  },
  "email_contact": {
    "type": "email",
    "label": "Email de contact",
    "required": true,
    "help": "Email pour les questions des candidats"
  },
  "telephone_contact": {
    "type": "text",
    "label": "Téléphone de contact",
    "required": false,
    "max_length": 20,
    "placeholder": "01 23 45 67 89"
  },
  "modalites_inscription": {
    "type": "choice",
    "label": "Modalités d'inscription",
    "required": true,
    "choices": {
      "En ligne": "online",
      "Par courrier": "mail",
      "Sur place": "onsite",
      "Mixte": "mixed"
    }
  },
  "documents_requis": {
    "type": "textarea",
    "label": "Documents requis",
    "required": false,
    "max_length": 1000,
    "help": "Liste des documents à fournir (un par ligne)"
  },
  "frais_inscription": {
    "type": "float",
    "label": "Frais d'inscription (€)",
    "required": false,
    "min": 0
  },
  "date_limite_inscription": {
    "type": "date",
    "label": "Date limite d'inscription",
    "required": false
  },
  "criteres_admission": {
    "type": "textarea",
    "label": "Critères d'admission",
    "required": false,
    "max_length": 2000,
    "help": "Expliquez les critères de sélection"
  },
  "candidatures_spontanees": {
    "type": "checkbox",
    "label": "Accepte les candidatures spontanées hors période",
    "required": false,
    "default": false
  }
}
```

## Guide d'utilisation

### Comment utiliser ces exemples ?

1. **Copier la définition JSON** correspondant à votre plateforme
2. **Dans le formulaire PlateformeAdmission**, coller dans le champ "Définition des champs"
3. **En mode Assistant** : Vous pouvez aussi construire cette définition ligne par ligne
4. **Sauvegarder** : Le formulaire PlateformeAdmissionParametre générera automatiquement ces champs

### Personnalisation

Vous pouvez :
- **Ajouter** des champs spécifiques à votre établissement
- **Retirer** des champs non utilisés
- **Modifier** les labels, help, valeurs min/max selon vos besoins
- **Adapter** les choix de select selon votre contexte

### Exemples de modifications

#### Ajouter un champ personnalisé
```json
{
  "identifiant_interne": {
    "type": "text",
    "label": "Identifiant interne URCA",
    "required": false,
    "max_length": 50,
    "help": "Code utilisé dans le système d'information de l'université"
  }
}
```

#### Modifier des choix
```json
{
  "modalites_selection": {
    "type": "choice",
    "label": "Modalités de sélection",
    "required": true,
    "choices": {
      "Dossier": "dossier",
      "Entretien": "entretien",
      "Dossier + Entretien + Tests": "complet"
    }
  }
}
```

## Bonnes pratiques

### ✅ À faire
- Utiliser des noms de champs explicites (`code_parcoursup` plutôt que `code1`)
- Toujours fournir un `help` pour expliquer l'usage du champ
- Définir des `min`/`max` pour les champs numériques
- Utiliser le bon type de champ (email pour email, url pour url, etc.)
- Marquer `required: true` uniquement pour les champs vraiment obligatoires

### ❌ À éviter
- Champs avec des noms génériques (`champ1`, `data`, `info`)
- Absence de `help` sur des champs complexes
- Champs de type `text` pour des emails/urls (pas de validation)
- Trop de champs obligatoires (frustration utilisateur)

## Validation et test

Après avoir configuré une définition :

1. **Créer un PlateformeAdmissionParametre de test**
2. **Vérifier que tous les champs s'affichent correctement**
3. **Tester la validation** (champs requis, formats, min/max)
4. **Vérifier la sauvegarde** des données

## Support

Pour toute question sur la configuration :
- Consultez `docs/formulaires/DynamicFieldsType.md`
- Consultez `docs/formulaires/JsonConfigType.md`
- Contactez l'équipe technique
