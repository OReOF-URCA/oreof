# Composant & API RNCP (France Compétences / Apprentissage)

> **À mettre à jour si** : Modification de `App\Service\Api\RncpApiService`, de `App\Controller\API\RncpApiController`, du composant `App\Twig\Components\RncpField`, du template `templates/components/rncp_field.html.twig` ou du contrôleur Stimulus `assets/controllers/rncp_controller.js`.

Ce module permet l'interrogation, la vérification et l'autocomplétion des codes RNCP (Répertoire National des Certifications Professionnelles) administrés par France Compétences, via l'**API Apprentissage** officielle (beta.gouv.fr / Mission Apprentissage / DINUM).

---

## 1. Architecture

```
                    ┌──────────────────────────────────────────────┐
                    │            Établissement (apiKeys)           │
                    │   plateforme: 'Apprentissage (RNCP)' / 'RNCP'│
                    │   apiKey: Bearer Token                       │
                    └──────────────────────┬───────────────────────┘
                                           │
                                           ▼
┌──────────────────────┐         ┌───────────────────┐         ┌─────────────────────────────────────┐
│  Composant UI        │ ◄─────► │ RncpApiController │ ◄─────► │           RncpApiService            │
│  <twig:RncpField />  │ Stimulus│  /api/rncp/verify │         │  Appel API Apprentissage            │
│  rncp_controller.js  │  JSON   │  /api/rncp/search │         │  /certification/v1 & fallback search│
└──────────────────────┘         └───────────────────┘         └──────────────────┬──────────────────┘
                                                                                  │
                                                                                  ▼
                                                                 ┌──────────────────────────────────┐
                                                                 │ api.apprentissage.beta.gouv.fr   │
                                                                 │ Cache Symfony 24h                │
                                                                 └──────────────────────────────────┘
```

---

## 2. Configuration dans l'Établissement

Dans la gestion des clés API de l'établissement (`/config/etablissement/{id}/api-keys`) :
- **Plateforme** : `Apprentissage (RNCP)` ou `RNCP`
- **URL** : `https://api.apprentissage.beta.gouv.fr/api` (valeur par défaut si omise)
- **Clé API** : Jeton Bearer fourni par l'API Apprentissage

Si aucune clé n'est renseignée, le composant effectue un contrôle syntaxique local (`RNCP\d{3,5}`) et génère un lien direct vers la fiche officielle sur France Compétences sans bloquer l'utilisateur.

---

## 3. Utilisation du Composant Twig `<twig:RncpField />`

### Dans le formulaire de création de formation (`templates/formation/_new_form.html.twig`)
Couplé avec le champ `inRncp` (Oui/Non) :
```twig
<div class="md:col-span-2">
    <twig:RncpField :form="form.codeRNCP" :inRncpField="form.inRncp" />
</div>
```

### Dans l'onglet configuration du parcours (`templates/parcours_v2/tabs/_configuration.html.twig`)
```twig
<div class="mt-4 p-4 rounded-xl border border-secondary-200 bg-white dark:border-secondary-700 dark:bg-secondary-900 shadow-xs">
    <twig:RncpField :form="form.codeRNCP" :parcoursId="parcours.id" />
</div>
```

### Props disponibles
- `:form` (`FormView|null`) : Vue du champ Symfony `codeRNCP`.
- `:inRncpField` (`FormView|null`) : Vue optionnelle du champ `inRncp` pour activer/désactiver dynamiquement la saisie.
- `:parcoursId` (`int|null`) : ID du parcours pour résolution contextuelle de l'établissement.
- `:formationId` (`int|null`) : ID de la formation pour résolution contextuelle de l'établissement.
- `:label` (`string`, défaut: `'Code RNCP'`) : Libellé du champ.
- `:help` (`string`) : Texte d'aide sous le champ.

---

## 4. Endpoints API internes

- `GET /api/rncp/verify?code=RNCP34436` : Vérifie la validité, l'intitulé officiel, le niveau, les certificateurs, la date de fin de validité et le statut actif/échu.
- `GET /api/rncp/search?q=informatique` : Recherche de certifications par mot-clé ou début de code.

---

## 5. Tests unitaires

Exécuter les tests unitaires du service :
```bash
bin/phpunit tests/Unit/Service/RncpApiServiceTest.php
```
