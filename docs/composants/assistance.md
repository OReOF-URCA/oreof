# Assistance : suivi des demandes reçues par mail (`/administration/assistance`)

Quand lire : modification de la relève des mails d'assistance, du regroupement en demandes, des pages d'administration
Assistance, de la réponse depuis ORéOF ou du panneau « contexte ORéOF du demandeur ».
À mettre à jour si : `src/Entity/{DemandeAssistance,MessageAssistance,PieceJointeAssistance}.php`,
`src/Enums/{StatutDemandeAssistanceEnum,SensMessageAssistanceEnum}.php`, `src/Service/Assistance/`,
`src/DTO/Assistance/`, `src/Controller/AssistanceController.php`, `src/Command/Assistance*Command.php`,
`src/Twig/Components/{MessageAssistanceCard,ContexteUsagerAssistance}.php`, `templates/assistance/`,
`templates/components/{message_assistance_card,contexte_usager_assistance}.html.twig`,
`config/packages/html_sanitizer.yaml`, variables `ASSISTANCE_IMAP_*`.

But : les demandes des usagers arrivent sur `oreof@univ-reims.fr`. Dans un webmail, les échanges d'un même dossier
sont éclatés et rien n'indique ce qui reste à traiter. Le module regroupe les mails en **demandes** avec un statut, un
responsable, des notes internes, et affiche le contexte ORéOF du demandeur. Accès `ROLE_ADMIN`. Conception :
`docs/superpowers/specs/2026-11-13-assistance-mails-design.md`.

## Fonctionnement

1. **Relève** (`app:assistance:relever`, cron toutes les 5 min sur 7 jours ; bouton « Relever maintenant » sur 1 jour) :
   lecture IMAP via `webklex/php-imap` (pur PHP, pas d'extension `imap`). La boîte n'est jamais modifiée (lecture
   « peek » avec flags : l'état lu/non lu est conservé). Les en-têtes sont lus d'abord : le corps et les pièces jointes
   ne sont téléchargés que pour les `Message-ID` inconnus ; sans `Message-ID`, un identifiant est calculé (expéditeur
   + date + sujet). Un verrou fichier (`var/assistance-releve.lock`, `VerrouReleveAssistance`) empêche deux relèves
   simultanées. Un mail illisible (date invalide → date du jour, contenu introuvable) ou en erreur à l'intégration est
   journalisé et ignoré : la relève continue (le gestionnaire d'entités est réinitialisé si besoin, les pièces jointes
   déjà écrites sont supprimées).
2. **Regroupement** (`IntegrationMailAssistance`), dans l'ordre :
   - `In-Reply-To` / `References` pointant vers un message connu → même demande ;
   - sinon même sujet normalisé (`SujetNormaliseur` : sans `Re:`, `TR:`, `Fwd:`, `[ORéOF]`, `***IMPORTANT***`, casse)
     qu'une demande active dans les 30 jours précédant le mail → même demande ;
   - sinon nouvelle demande (statut « Nouvelle »).
   Un mail reçu non automatique rouvre une demande « Résolue » ou « En attente de l'usager » (→ « En cours »).
   Les rebonds et réponses automatiques (`Auto-Submitted`, `MAILER-DAEMON`, `postmaster`) sont rattachés sans rouvrir ;
   hors de tout échange connu, ils créent une demande directement « Résolue ».
   Un mail dont l'expéditeur est `oreof@` ou un `ROLE_ADMIN` est classé « Réponse envoyée ».
3. **Stockage** : HTML nettoyé à l'enregistrement par le sanitizer `assistance_sanitizer` (pas de script, pas d'image
   ni média, liens `https`/`mailto` seulement, entrée jusqu'à 2 Mo : la limite Symfony par défaut de 20 000 caractères
   couperait les mails Outlook) ; identifiants de mail de plus de 255 caractères remplacés par leur empreinte ; pièces jointes sous `var/assistance/AAAA/MM/<aléatoire>`, servies par
   `app_assistance_piece_jointe` en téléchargement.
4. **Réponse** (`ReponseAssistance`) : envoyée depuis `oreof@univ-reims.fr` (`From` et `Reply-To`), avec
   `In-Reply-To`/`References` du dernier message et un `Message-ID` enregistré : la réponse de l'usager revient dans
   le même fil. Statut → « En attente de l'usager », ou « Résolue » si la case est cochée.
5. **Contexte du demandeur** (`ContexteUsagerAssistance`) : compte, rôles, profils (`UserProfil`), état de chaque
   module (`CampagneAccessibilityService::getModuleStatus`) pour la campagne par défaut et celles des profils,
   5 dernières `DpeDemande`, liens fiche utilisateur et « se connecter en tant que ».

## Pages et actions

| Route | Rôle |
|---|---|
| `app_assistance_index` | liste filtrable (statut, responsable, recherche), compteur des demandes ouvertes |
| `app_assistance_show` | fil de la demande, réponse, note interne, statut, responsable, formation/parcours, fusion, contexte |
| `app_assistance_{statut,responsable,note,rattacher,fusionner,repondre}` | POST, jeton CSRF `assistance_<id>` |
| `app_assistance_relever` | POST, jeton `assistance_relever` |

Fusion : les messages vont sur la demande la plus ancienne, l'autre est supprimée. Assigner un responsable à une
demande « Nouvelle » la passe « En cours ».

## Configuration

Variables (`.env` : valeurs vides ; renseigner dans `.env.local` ou l'environnement serveur, jamais de secret
versionné) : `ASSISTANCE_IMAP_HOST` (vide = relève désactivée), `ASSISTANCE_IMAP_PORT` (993),
`ASSISTANCE_IMAP_ENCRYPTION` (`ssl`), `ASSISTANCE_IMAP_USER`, `ASSISTANCE_IMAP_PASSWORD`, `ASSISTANCE_IMAP_FOLDER`
(`INBOX`). Utiliser une **boîte dédiée** (ex. boîte fonctionnelle `oreof@`), jamais une boîte personnelle.

## Import d'historique et tests

`php bin/console app:assistance:importer-eml <fichier|dossier>` intègre des `.eml` (export Roundcube : sélectionner
les mails → « Plus » → « Télécharger (.eml) »). Fixtures : `tests/Fixtures/assistance/` ; tests :
`tests/Unit/Service/Assistance/`, `tests/Integration/Assistance/`, `tests/Functional/Controller/AssistanceControllerTest.php`.

## Limites

- Une réponse envoyée depuis un webmail n'apparaît que si `oreof@` est en copie.
- Le rattachement à une formation/un parcours est manuel.
- Les images intégrées aux mails ne sont pas affichées (supprimées au nettoyage).
