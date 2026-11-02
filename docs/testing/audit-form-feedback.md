# Audit formulaires et retours utilisateur — v2

Quand lire : vérifier la couverture des validations et retours utilisateur avant de généraliser une correction.
À mettre à jour si : helpers de sauvegarde, contrôleurs/formulaires cités ou traitement des points FORM-AUDIT.

Audit statique du 18 octobre 2026, base `d7659d3c2e07d20966a9d02c5e2e2f3f52df47e0`. Les PR #250 à #253 ne sont pas dans cette base et sont
mentionnées comme corrections proposées, pas comme fonctionnalités déjà déployées. Aucun test navigateur effectué.
Les inventaires ci-dessous sont des pistes de lecture, pas une preuve d’exécution ou de conformité de chaque route.

## Résultats et corrections de cette PR

| Référence | Emplacement | Constat | Statut |
|---|---|---|---|
| FIX-01 | `assets/js/saveData.js` | Un 500 produisait une erreur puis un succès dans le `.then` suivant ; autres statuts non vérifiés. | Corrigé : pas de succès sur échec HTTP, JSON false/success:false, réseau ou JSON inexploitable. Rapport `error[]` conservé pour les consommateurs existants. |
| FIX-02 | Consommateurs `saveData` formation, fiche matière, EC, parcours | Les indicateurs d’étape pouvaient être recalculés après un échec ; le résultat des wrappers était perdu. | Corrigé sur les appels directs inventoriés : garde avant recalcul, propagation de la réponse, protection du reload multi-parcours et de la génération/recopie BCC. Les callbacks indirects restent à vérifier. |
| FIX-03 | `Config/ComposanteController::new/edit` | `isValid()` commenté, sauvegarde et création de comptes/rôles possibles sur formulaire invalide. | Corrigé : validation Symfony avant les effets de bord. Tester la création manuelle des utilisateurs et les erreurs du formulaire. |
| FIX-04 | `notification_liste_controller.js::change` | Pas de catch ni de contrôle HTTP ; état visuel optimiste conservé après refus. | Corrigé : contrôle HTTP/JSON, blocage temporaire du contrôle, restauration et message d’incertitude réseau. |
| FORM-AUDIT-S01 | `FormationController::editModal` | Rétablissement du garde `isValid()` avant persistance et événements rôle, avec retour HTTP 422 en cas d'erreur. | Corrigé |
| FORM-AUDIT-S02 | `ElementConstitutifController::structureEc` | Rétablissement du garde `isValid()` avant flush/événements MCCC, avec réponse HTTP 422 sur formulaire invalide. | Corrigé |
| FORM-AUDIT-S03 | `ElementConstitutifController::structureBut` | Contrôle `isValid()` avant enregistrement et retour HTTP 422 en cas d'échec de validation. | Corrigé |

## Points serveur restant à traiter

| Référence | Emplacement | Risque / action attendue |
|---|---|---|
| FORM-AUDIT-S04 | `PvConseilController` | isValid présent, mais une validation invalide devient une erreur JSON 500 générique via JsonReponse::error. Préférer 422 avec erreurs exploitables après revue du client. |
| FORM-AUDIT-S05 | `FormationResponsableController`, `ParcoursProcessController`, `FicheMatiereProcessController`, `CampagneDuplicationController` | Les blocs isSubmitted seuls ne sont pas des preuves de défaut : des gardes isValid imbriqués existent. Tester séparément erreurs métier, formulaires invalides et maintien de la modale. |

## Points front restant à traiter

| Référence | Emplacement | Action attendue |
|---|---|---|
| FORM-AUDIT-J01 | `parcours/step3_controller.js` : delete, déplacer BCC/CC, duplicate | Plusieurs fetch directs confirment puis rechargent sans lire la réponse ; distincts du chemin saveData corrigé. |
| FORM-AUDIT-J02 | `formation/step3_controller.js` : dupliquer/supprimer parcours | Même risque de succès malgré refus HTTP. Vérifier CSRF, résultat métier et erreur réseau. |
| FORM-AUDIT-J03 | `crud_controller.js::duplicate`, `profil/user_controller.js::delete` | Rafraîchissement et succès inconditionnels après fetch. Tester aussi les écouteurs cumulés des confirmations de suppression. |
| FORM-AUDIT-J04 | `user_controller.js`, `droits_controller.js` | Succès après fetch sans examen du résultat. Vérifier d’abord leur montage effectif : pas de référence explicite retrouvée lors de cette recherche dans les templates. Ne pas les qualifier automatiquement de routes actives. |
| FORM-AUDIT-J05 | `parcours/bcc_controller.js::sauvegardeFormModal` | Ferme après décodage JSON sans vérifier valeur ou statut ; méthode distincte du gestionnaire legacy corrigé en #253. |
| FORM-AUDIT-J06 | `semestre/raccrocher`, `ue/raccrocher`, `semestre/mutualise`, `ec/manage` | Parcours personnalisés à migrer vers le contrat commun après lecture des endpoints ; pas de modification automatique des règles métier. |
| FORM-AUDIT-J07 | `_save` et leurs callbacks indirects | La présence d’un garde sur saveData ne prouve pas qu’un appelant de `_save` examine son résultat. Revoir chaque fermeture, redirection et recalcul en aval. |

## Couverture du thème et limites de généralisation

`config/packages/twig.yaml` ne définit pas `form_themes` global. Le thème v2 doit être sélectionné explicitement
ou hérité d’un template/include (par exemple `communs/_form.html.twig`). L’absence d’une déclaration dans un
fichier isolé ne prouve donc pas que le thème est absent ; suivre la chaîne de rendu. Les formulaires HTML
manuels n’en bénéficient pas. Ne pas activer globalement le thème sur tout le legacy sans recette.

## callOut.js est-il encore utilisé ?

Oui. `assets/app.js` l’importe et rejoue les flashes toast. Les contrôleurs de capacités et de rubriques de plaquette
l’importent aussi. `JsonResponse.js` utilise son export `handleToastResponse`. Ce module est une façade vers
`assets/components/Toast.js`, pas l’ancienne implémentation visuelle Bootstrap.

Comptage statique : **39 fichiers importent directement callOut**, **50 fichiers importent callOut
ou JsonResponse** (union, sans double comptage). Cela ne prouve pas que tous ces fichiers sont montés en production.
Exemples visibles dans les templates : préférences notifications (`notication_liste/index.html.twig`) et BCC
(`parcours_bcc/but.html.twig`, `_tableauCroise.html.twig`). Conserver la façade ; harmoniser ses consommateurs.

## Inventaire des soumissions Symfony

98 occurrences de isSubmitted trouvées (recherche textuelle, commentaires éventuels inclus).
Le garde de validation peut être imbriqué ; ce tableau ne classe pas chaque occurrence comme un défaut.

| Fichier | Méthode | Ligne auditée | Condition / usage |
|---|---|---:|---|
| `src/Controller/Admin/CampagneDuplicationController.php` | `index` | 74 | `if ($form->isSubmitted()) {` |
| `src/Controller/BlocCompetenceController.php` | `newParcours` | 187 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/BlocCompetenceController.php` | `edit` | 253 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/BugReportController.php` | `modal` | 53 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/CodificationController.php` | `modifier` | 253 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/CompetenceController.php` | `new` | 52 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/CompetenceController.php` | `edit` | 107 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/ActualiteController.php` | `new` | 54 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/ActualiteController.php` | `edit` | 115 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/AnneeUniversitaireController.php` | `new` | 56 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/AnneeUniversitaireController.php` | `edit` | 121 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/CampagneCollecteController.php` | `new` | 73 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/CampagneCollecteController.php` | `edit` | 125 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/CampagneCollecteController.php` | `configurePublication` | 201 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/ComposanteController.php` | `new` | 57 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/ComposanteController.php` | `edit` | 163 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/DomaineController.php` | `new` | 52 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/DomaineController.php` | `edit` | 117 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/EtablissementController.php` | `new` | 53 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/EtablissementController.php` | `edit` | 152 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/EtablissementController.php` | `apiKeys` | 184 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/MentionController.php` | `new` | 106 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/MentionController.php` | `edit` | 205 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/NatureUeEcController.php` | `new` | 52 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/NatureUeEcController.php` | `edit` | 122 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/PlateformAdmissionController.php` | `new` | 57 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/PlateformAdmissionController.php` | `edit` | 144 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/ProfilsController.php` | `new` | 91 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/ProfilsController.php` | `editer` | 117 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/RamificationParcoursController.php` | `newTypeRamification` | 42 | `if($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/RamificationParcoursController.php` | `editTypeRamification` | 98 | `if($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/RythmeFormationController.php` | `new` | 54 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/RythmeFormationController.php` | `edit` | 107 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeDiplomeController.php` | `new` | 75 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeDiplomeController.php` | `edit` | 251 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeEcController.php` | `new` | 55 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeEcController.php` | `edit` | 136 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeEpreuveController.php` | `new` | 56 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeEpreuveController.php` | `edit` | 131 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeUeController.php` | `new` | 57 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/TypeUeController.php` | `edit` | 128 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/UserController.php` | `newLdap` | 66 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/UserController.php` | `newLdap` | 157 | `$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK` |
| `src/Controller/Config/UserController.php` | `horsUrca` | 176 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/UserController.php` | `horsUrca` | 228 | `$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK` |
| `src/Controller/Config/UserController.php` | `edit` | 311 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/VilleController.php` | `new` | 57 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Config/VilleController.php` | `edit` | 125 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ConfigEmailController.php` | `new` | 61 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ConfigEmailController.php` | `edit` | 88 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/DemandeDpeController.php` | `edit` | 236 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ElementConstitutifController.php` | `new` | 130 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ElementConstitutifController.php` | `newEnfant` | 252 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ElementConstitutifController.php` | `edit` | 345 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ElementConstitutifController.php` | `structureEc` | 552 | `if ($form->isSubmitted()) {` |
| `src/Controller/ElementConstitutifController.php` | `structureBut` | 648 | `if ($form->isSubmitted()) {` |
| `src/Controller/FaqAdminController.php` | `new` | 39 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/FaqAdminController.php` | `edit` | 58 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/FicheMatiere/FicheMatiereProcessController.php` | `applyProcess` | 77 | `if ($form->isSubmitted()) {` |
| `src/Controller/FicheMatiereController.php` | `new` | 78 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/FormationController.php` | `new` | 338 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/FormationController.php` | `editModal` | 386 | `if ($form->isSubmitted()) {` |
| `src/Controller/FormationResponsableController.php` | `index` | 71 | `if ($form->isSubmitted()) {` |
| `src/Controller/FormationResponsableController.php` | `validationChangeRf` | 358 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/FormationResponsableController.php` | `validationChangeRf` | 428 | `$status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;` |
| `src/Controller/FormationResponsableController.php` | `validationChangeRf` | 429 | `if ($form->isSubmitted()) {` |
| `src/Controller/FormationResponsableController.php` | `reserverChangeRf` | 490 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/FormationResponsableController.php` | `reserverChangeRf` | 540 | `$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,` |
| `src/Controller/FormulaireGeneriqueController.php` | `new` | 69 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/HelpAdminController.php` | `new` | 83 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/HelpAdminController.php` | `edit` | 102 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Parcours/ParcoursBccController.php` | `ecPost` | 69 | `//        if (!$form->isSubmitted()  /  /  !$form->isValid()) {` |
| `src/Controller/Parcours/ParcoursConfigController.php` | `saisirPost` | 69 | `//        if (!$form->isSubmitted()  /  /  !$form->isValid()) {` |
| `src/Controller/Parcours/ParcoursController.php` | `dupliquerPost` | 685 | `if (!$form->isSubmitted()  /  /  !$form->isValid()) {` |
| `src/Controller/Parcours/ParcoursEcController.php` | `new` | 79 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Parcours/ParcoursEcController.php` | `modifier` | 165 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Parcours/ParcoursEcController.php` | `saisirHeures` | 246 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Parcours/ParcoursMcccController.php` | `saisirPost` | 125 | `//        if (!$form->isSubmitted()  /  /  !$form->isValid()) {` |
| `src/Controller/Parcours/ParcoursProcessController.php` | `applyProcess` | 220 | `if ($form->isSubmitted()) {` |
| `src/Controller/Parcours/ParcoursProcessController.php` | `applyProcess` | 348 | `//        if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Parcours/ParcoursUeController.php` | `addUe` | 66 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Parcours/ParcoursUeController.php` | `edit` | 151 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ParcoursContactController.php` | `new` | 54 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ParcoursContactController.php` | `edit` | 81 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ParcoursController.php` | `new` | 116 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/ParcoursController.php` | `editModal` | 222 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Prototype/TypeDiplomePrototypeController.php` | `new` | 71 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Prototype/TypeDiplomePrototypeController.php` | `edit` | 99 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/PvConseilController.php` | `index` | 46 | `if ($form->isSubmitted()) {` |
| `src/Controller/PvConseilController.php` | `changeRfPv` | 134 | `if ($form->isSubmitted()) {` |
| `src/Controller/RegisterController.php` | `index` | 43 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Structure/UeController.php` | `addUe` | 116 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/Structure/UeController.php` | `edit` | 272 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/UserGestionController.php` | `ajoutUtilisateur` | 63 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/WorkflowBulkController.php` | `apply` | 58 | `if ($form->isSubmitted() && $form->isValid()) {` |
| `src/Controller/WorkflowBulkController.php` | `apply` | 107 | `$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,` |

## Inventaire des accès réseau JavaScript

Comptage des appels fetch par fichier (y compris lectures, commentaires et legacy éventuels).
Les helpers importés ne sont pas comptés comme des fetch directs dans leurs consommateurs.

| Fichier | Appels fetch repérés |
|---|---:|
| `assets/controllers/autosave_capacites_controller.js` | 1 |
| `assets/controllers/autosave_controller.js` | 1 |
| `assets/controllers/base_controller.js` | 4 |
| `assets/controllers/commentaire_controller.js` | 3 |
| `assets/controllers/crud_controller.js` | 3 |
| `assets/controllers/droits_controller.js` | 1 |
| `assets/controllers/ec/liste_controller.js` | 2 |
| `assets/controllers/ec/manage_controller.js` | 4 |
| `assets/controllers/ec/mccc_controller.js` | 5 |
| `assets/controllers/ec/structure_controller.js` | 2 |
| `assets/controllers/email_template_controller.js` | 1 |
| `assets/controllers/fiche_matiere_wizard_controller.js` | 3 |
| `assets/controllers/fichematiere/liste_controller.js` | 1 |
| `assets/controllers/fichematiere/mutualise_controller.js` | 2 |
| `assets/controllers/fichematiere/parcours_porteur_controller.js` | 2 |
| `assets/controllers/fichematiere/step1_controller.js` | 4 |
| `assets/controllers/fichematiere/step3_controller.js` | 1 |
| `assets/controllers/fichematiere/step4_controller.js` | 1 |
| `assets/controllers/formation/step3_controller.js` | 3 |
| `assets/controllers/formation_controller.js` | 1 |
| `assets/controllers/formation_wizard_controller.js` | 2 |
| `assets/controllers/formulaire_generique/creation_controller.js` | 3 |
| `assets/controllers/frame_loader_controller.js` | 1 |
| `assets/controllers/historique_controller.js` | 1 |
| `assets/controllers/linkjs_controller.js` | 1 |
| `assets/controllers/load_content_controller.js` | 1 |
| `assets/controllers/logos_controller.js` | 2 |
| `assets/controllers/maquette_controller.js` | 1 |
| `assets/controllers/mccc/licence_controller.js` | 1 |
| `assets/controllers/modal_confirm_controller.js` | 1 |
| `assets/controllers/modal_controller.js` | 1 |
| `assets/controllers/modalturbo_controller.js` | 1 |
| `assets/controllers/mutualise_wizard_controller.js` | 1 |
| `assets/controllers/navigation_palette_controller.js` | 1 |
| `assets/controllers/notification_center_controller.js` | 2 |
| `assets/controllers/notification_controller.js` | 3 |
| `assets/controllers/notification_liste_controller.js` | 1 |
| `assets/controllers/offre_controller.js` | 7 |
| `assets/controllers/offre_table_controller.js` | 1 |
| `assets/controllers/page_translations_drawer_controller.js` | 1 |
| `assets/controllers/parcours/bcc_controller.js` | 2 |
| `assets/controllers/parcours/contact_controller.js` | 1 |
| `assets/controllers/parcours/ec_controller.js` | 4 |
| `assets/controllers/parcours/manage_controller.js` | 1 |
| `assets/controllers/parcours/quick_hours_grid_controller.js` | 1 |
| `assets/controllers/parcours/rome_controller.js` | 5 |
| `assets/controllers/parcours/step3_controller.js` | 5 |
| `assets/controllers/parcours_wizard_controller.js` | 1 |
| `assets/controllers/plaquette-rubriques_controller.js` | 4 |
| `assets/controllers/plaquette_controller.js` | 6 |
| `assets/controllers/profil/index_controller.js` | 2 |
| `assets/controllers/profil/user_controller.js` | 4 |
| `assets/controllers/ramification_parcours_manage_controller.js` | 1 |
| `assets/controllers/register_controller.js` | 5 |
| `assets/controllers/semestre/manage_controller.js` | 5 |
| `assets/controllers/semestre/mutualise_controller.js` | 4 |
| `assets/controllers/semestre/raccrocher_controller.js` | 2 |
| `assets/controllers/semestre_controller.js` | 1 |
| `assets/controllers/sortable_controller.js` | 1 |
| `assets/controllers/structure/composante_controller.js` | 1 |
| `assets/controllers/structure/formation_controller.js` | 2 |
| `assets/controllers/structure/parcours_controller.js` | 2 |
| `assets/controllers/structure/semestre_controller.js` | 6 |
| `assets/controllers/structure/ue_controller.js` | 5 |
| `assets/controllers/tab_done_controller.js` | 1 |
| `assets/controllers/toast_controller.js` | 1 |
| `assets/controllers/translation/recherche_controller.js` | 1 |
| `assets/controllers/translations_controller.js` | 1 |
| `assets/controllers/type_diplome_logos_controller.js` | 2 |
| `assets/controllers/type_diplome_prototype_controller.js` | 4 |
| `assets/controllers/ue/manage_controller.js` | 3 |
| `assets/controllers/ue/mutualise_controller.js` | 4 |
| `assets/controllers/ue/raccrocher_controller.js` | 2 |
| `assets/controllers/user_controller.js` | 1 |
| `assets/controllers/validation/bulk_actions_controller.js` | 2 |
| `assets/controllers/validation/fichematiere_controller.js` | 1 |
| `assets/controllers/validation/index_controller.js` | 8 |
| `assets/controllers/versioning_generate_controller.js` | 1 |
| `assets/controllers/wizard_controller.js` | 1 |
| `assets/js/fetch.js` | 1 |
| `assets/js/saveData.js` | 1 |
| `assets/js/updateEtatOnglet.js` | 1 |

## Callbacks sans paramètre à relire

Un `.then(() => ...)` peut volontairement ignorer un résultat de lecture. Ces occurrences sont des candidats,
pas des erreurs démontrées. Les helpers importés et les chaînes indirectes nécessitent une lecture manuelle.

| Fichier | Ligne auditée |
|---|---:|
| `assets/controllers/base_controller.js` | 83 |
| `assets/controllers/crud_controller.js` | 156 |
| `assets/controllers/droits_controller.js` | 27 |
| `assets/controllers/ec/liste_controller.js` | 17 |
| `assets/controllers/ec/manage_controller.js` | 188 |
| `assets/controllers/ec/manage_controller.js` | 203 |
| `assets/controllers/ec/mccc_controller.js` | 92 |
| `assets/controllers/fichematiere/liste_controller.js` | 16 |
| `assets/controllers/fichematiere/mutualise_controller.js` | 36 |
| `assets/controllers/formation/step3_controller.js` | 48 |
| `assets/controllers/formation/step3_controller.js` | 66 |
| `assets/controllers/formation_controller.js` | 25 |
| `assets/controllers/mccc/licence_controller.js` | 25 |
| `assets/controllers/page_translations_drawer_controller.js` | 367 |
| `assets/controllers/parcours/bcc_controller.js` | 38 |
| `assets/controllers/parcours/step3_controller.js` | 51 |
| `assets/controllers/parcours/step3_controller.js` | 62 |
| `assets/controllers/parcours/step3_controller.js` | 71 |
| `assets/controllers/parcours/step3_controller.js` | 80 |
| `assets/controllers/profil/user_controller.js` | 50 |
| `assets/controllers/semestre/mutualise_controller.js` | 54 |
| `assets/controllers/semestre/mutualise_controller.js` | 70 |
| `assets/controllers/semestre/raccrocher_controller.js` | 29 |
| `assets/controllers/semestre/raccrocher_controller.js` | 50 |
| `assets/controllers/structure/semestre_controller.js` | 58 |
| `assets/controllers/structure/ue_controller.js` | 41 |
| `assets/controllers/structure/ue_controller.js` | 107 |
| `assets/controllers/ue/mutualise_controller.js` | 53 |
| `assets/controllers/ue/mutualise_controller.js` | 69 |
| `assets/controllers/ue/raccrocher_controller.js` | 29 |
| `assets/controllers/ue/raccrocher_controller.js` | 50 |
| `assets/controllers/user_controller.js` | 26 |

## Validation et limites

Sept tests JavaScript du helper et du garde d’indicateur passent ; ESLint ciblé passe sur les onze fichiers JS modifiés. git diff --check passe. npm run dev/lint tentés mais dépendances vite/eslint absentes. PHP, Docker et
les dépendances applicatives ne sont pas disponibles ici : aucune validation Symfony, PHPStan ou navigateur.
Priorité de recette : Composante (invalide/CSRF/création utilisateur), sauvegarde lente/refusée, indicateurs d’étape,
préférences notifications. Les migrations de contrats HTTP doivent être testées avec leurs clients avant diffusion.
