# Synchronisation ORéBUT (B.U.T.)

Quand lire : modification de la synchronisation avec l'API nationale ORéBUT (`https://orebut.iut.fr/api/`), de la configuration de l'API dans `Etablissement`, ou de la maquette pédagogique BUT.
À mettre à jour si : `src/Service/Api/OrebutApiService.php`, `src/TypeDiplome/Synchronisation/But.php`, `src/Controller/FormationSynchronisationController.php` ou `templates/formation/synchronisation/modal_orebut.html.twig` sont modifiés.

## Principe

Permet aux administrateurs (`ROLE_ADMIN`) de synchroniser une formation de type B.U.T. avec le programme national disponible sur l'API ORéBUT (`https://orebut.iut.fr/api/`).

La synchronisation est sélective et non destructive :
- **Parcours** : création/mise à jour des parcours nationaux et organisation des semestres (tronc commun S1-S2 pour type 1 et 2, semestres spécifiques pour type 3). Chaque nouveau parcours rattaché à une campagne est initialisé avec un `DpeParcours` à l'état `en_cours_redaction`.
- **Référentiel de compétences** : compétences, composantes essentielles, situations professionnelles, niveaux annuels et apprentissages critiques (AC), ainsi que les déclinaisons par parcours (création des UEs correspondantes par semestre).
- **Maquette (Ressources & SAÉ)** : création/mise à jour des fiches matières avec leurs volumes horaires PPN (CM, TD, TP, TE), descriptifs, objectifs, rattachement aux parcours, création des ECs dans les UEs avec coefficients ECTS et rattachement des ACs. Les modalités d'enseignement et régimes d'inscription s'appuient sur les référentiels administrables (`App\Referentiel\ModaliteEnseignement` et `App\Referentiel\RegimeInscription`).

## Configuration dans Établissement

La configuration est gérée au niveau de l'entité `Etablissement` dans le champ `apiKeys` (accessible via `/config/etablissement/{id}/api-keys`) :
- **Plateforme** : `ORéBUT`
- **URL** : URL de l'API (par défaut `https://orebut.iut.fr/api` si non renseignée)
- **Clé API / Token** : Jeton secret si requis par le service

Méthodes utilitaires disponibles sur `Etablissement`, `Formation` et `Parcours` :
- `getOrebutApiConfiguration(): ?array`
- `hasOrebutApi(): bool`
- `getOrebutApiUrl(): string`

## Sélection fine et versions du programme

La modale de synchronisation permet de paramétrer précisément l'importation :
1. **Version du programme national (`annee`)** :
   - Deux versions sont supportées par l'API nationale :
     - **2021** : programme national initial du BUT (première génération).
     - **2027** : programme national réformé (nouvelles compétences, ajustements d'heures et intitulés).
   - Le paramètre est transmis en `?annee=2021` ou `?annee=2027` aux endpoints `/departements/{sigle}`, `/specialite/{sigle}/competences`, `/specialite/{sigle}/ressources` et `/specialite/{sigle}/saes`.
2. **Choix des parcours à intégrer (`parcours_codes[]`)** :
   - La liste des parcours nationaux proposés par la spécialité pour l'année choisie s'affiche sous forme de cases à cocher.
   - Les administrateurs peuvent décocher les parcours non dispensés localement.
   - Si un parcours est déjà configuré dans la formation locale, un badge informatif l'indique.
3. **Interactivité via Stimulus** :
   - Le contrôleur Stimulus `assets/controllers/orebut_modal_controller.js` (`orebut-modal`) écoute les changements de version ou de spécialité pour rafraîchir dynamiquement les données du département et la liste des parcours sans recharger la page complète.

## Architecture des classes

| Classe / Fichier | Rôle |
|---|---|
| `App\Service\Api\OrebutApiService` | Client HTTP vers l'API ORéBUT (départements, compétences, ressources, SAÉ, pagination, filtrage `?annee=`) |
| `App\TypeDiplome\Synchronisation\But` | Logique métier de synchronisation sélective, non destructive et idempotente |
| `App\TypeDiplome\Synchronisation\ButSyncOptions` | DTO des options de synchronisation (`syncParcours`, `syncCompetences`, `syncMaquette`, `sigleDepartement`, `selectedParcoursCodes`, `annee`) |
| `App\TypeDiplome\Synchronisation\ButSyncResult` | DTO des compteurs et résultats de la synchronisation |
| `App\Controller\FormationSynchronisationController` | Contrôleur exposant la modale Turbo Stream (`app_formation_synchronisation_orebut_modal`) |
| `assets/controllers/orebut_modal_controller.js` | Contrôleur Stimulus gérant le rafraîchissement dynamique et le tout cocher/décocher des parcours |
| `templates/formation/synchronisation/modal_orebut.html.twig` | Modale interactive de choix des versions, parcours et éléments à synchroniser |
| `templates/formation/synchronisation/success.stream.html.twig` | Réponse Turbo Stream après soumission (fermeture de la modale, toast et rafraîchissement) |
| `templates/components/FormationHeader.html.twig` | Boutons d'accès direct et dans le volet SES pour les B.U.T. |
