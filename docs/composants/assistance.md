# Assistance : suivi des demandes reçues par mail (`/administration/assistance`)

Quand lire : modification de la relève des mails d'assistance, du regroupement en demandes, des pages d'administration
Assistance, de la réponse depuis ORéOF ou du panneau « contexte ORéOF du demandeur ».
À mettre à jour si : `src/Entity/{DemandeAssistance,MessageAssistance,PieceJointeAssistance}.php`,
`src/Enums/{StatutDemandeAssistanceEnum,SensMessageAssistanceEnum}.php`, `src/Service/Assistance/`,
`src/DTO/Assistance/`, `src/Controller/AssistanceController.php`, `src/Command/Assistance*Command.php`,
`src/Twig/Components/{MessageAssistanceCard,ContexteUsagerAssistance}.php`, `templates/assistance/`,
`assets/controllers/assistance_{releve,ia}_controller.js`,
`templates/components/{message_assistance_card,contexte_usager_assistance}.html.twig`,
`config/packages/html_sanitizer.yaml`, variables `ASSISTANCE_IMAP_*`.

But : les demandes des usagers arrivent sur `oreof@univ-reims.fr`. Dans un webmail, les échanges d'un même dossier
sont éclatés et rien n'indique ce qui reste à traiter. Le module regroupe les mails en **demandes** avec un statut, un
responsable, des notes internes, et affiche le contexte ORéOF du demandeur. Accès `ROLE_ADMIN`. Conception :
`docs/superpowers/specs/2026-11-13-assistance-mails-design.md`.

## Fonctionnement

1. **Relève** (`app:assistance:relever`, cron toutes les 5 min sur 7 jours ; bouton « Relever maintenant » et ouverture
   de la liste sur 1 jour, via `ReleveWebAssistance`) :
   lecture IMAP via `webklex/php-imap` (pur PHP, pas d'extension `imap`). La boîte n'est jamais modifiée (lecture
   « peek » avec flags : l'état lu/non lu est conservé). Les en-têtes sont lus d'abord : le corps et les pièces jointes
   ne sont téléchargés que pour les `Message-ID` inconnus ; sans `Message-ID`, un identifiant est calculé (expéditeur
   + date + sujet). Un verrou fichier (`var/assistance-releve.lock`, `VerrouReleveAssistance`) empêche deux relèves
   simultanées. Un mail illisible (date invalide → date du jour, contenu introuvable) ou en erreur à l'intégration est
   journalisé et ignoré : la relève continue (le gestionnaire d'entités est réinitialisé si besoin, les pièces jointes
   déjà écrites sont supprimées).
   Mail **automatique** : en-tête `Auto-Submitted`, expéditeur `mailer-daemon@`, `postmaster@`, `no-reply@`/`noreply@`,
   `ne-pas-repondre@`, `robot@`, ou sujet « [ORéOF] … » (hors « [ORéOF][Bug] »). Une demande créée par un mail automatique
   est une **notification système** (`DemandeAssistance::notification`, statut résolue) : onglet à part, jamais comptée
   comme demande ; elle redevient une demande dès qu'un mail non automatique la rejoint (réponse d'un usager ou de l'équipe).
2. **Regroupement** (`IntegrationMailAssistance`), dans l'ordre :
   - `In-Reply-To` / `References` pointant vers un message connu → même demande ;
   - sinon même sujet normalisé (`SujetNormaliseur` : sans `Re:`, `TR:`, `Fwd:`, `[ORéOF]`, `***IMPORTANT***`, casse)
     qu'une demande active dans les 30 jours précédant le mail → même demande ;
   - sinon nouvelle demande (statut « Nouvelle »).
   Un mail reçu non automatique rouvre une demande « Résolue » ou « En attente de l'usager » (→ « En cours »).
   Les rebonds et réponses automatiques (`Auto-Submitted`, `MAILER-DAEMON`, `postmaster`) sont rattachés sans rouvrir ;
   hors de tout échange connu, ils créent une demande directement « Résolue ». Les notifications de l'application
   (sujet commençant par `[ORéOF]`) sont traitées de même, sauf les signalements de bug `[ORéOF][Bug] …` (vraies
   demandes) ; une réponse « Re: [ORéOF] … » d'un usager rouvre la demande.
   Une réponse importée envoyée par un `ROLE_ADMIN` sous sa propre adresse (webmail, `oreof@` en copie) lui attribue
   la demande si elle n'a pas de responsable ; envoyée sous l'adresse générique `oreof@`, elle ne désigne personne.
   Un mail dont l'expéditeur est `oreof@` ou un `ROLE_ADMIN` est classé « Réponse envoyée », sauf s'il porte un
   `Reply-To` extérieur à l'équipe : c'est alors un mail envoyé par l'application pour le compte d'un usager (ex.
   formulaire « Signaler un bug », `BugReportController`), attribué à l'adresse du `Reply-To` (message reçu, demandeur,
   contexte et destinataire de la réponse).
   **Annonces** : un mail de l'équipe à plus de 10 destinataires (`IntegrationMailAssistance::SEUIL_ANNONCE`, To + Cc)
   est une annonce (`DemandeAssistance::$annonce`, statut « Résolue », badge « Annonce », pas de formulaire de
   réponse). Elle ne rejoint qu'une autre annonce du même fil, et le regroupement par fil l'ignore : chaque usager qui
   y répond a sa propre demande (l'usager = l'expéditeur, ou pour un mail de l'équipe le premier destinataire
   extérieur), retrouvée ensuite par fil ou par sujet + usager.
3. **Stockage** : HTML nettoyé à l'enregistrement par le sanitizer `assistance_sanitizer` (pas de script, pas d'image
   ni média, liens `https`/`mailto` seulement, entrée jusqu'à 2 Mo : la limite Symfony par défaut de 20 000 caractères
   couperait les mails Outlook) ; identifiants de mail de plus de 255 caractères remplacés par leur empreinte ; pièces jointes sous `var/assistance/AAAA/MM/<aléatoire>`, servies par
   `app_assistance_piece_jointe` en téléchargement.
4. **Réponse** (`ReponseAssistance`) : envoyée depuis `oreof@univ-reims.fr` (`From` et `Reply-To`), avec
   `In-Reply-To`/`References` du dernier message et un `Message-ID` enregistré : la réponse de l'usager revient dans
   le même fil. Statut → « En attente de l'usager », ou « Résolue » si la case est cochée. Sans responsable, la demande
   est attribuée à l'auteur de la réponse (un responsable déjà désigné est conservé).
5. **Contexte du demandeur** (`ContexteUsagerAssistance`) : compte, rôles, profils (`UserProfil`), périodes de saisie
   résumées en une ligne par campagne (`ResumePeriodesSaisie` sur `CampagneAccessibilityService::getModuleStatus` :
   « toutes ouvertes jusqu'au … », « toutes fermées », ou « ouvertes, sauf : » suivi des seuls modules fermés), pour la
   campagne par défaut et les campagnes des profils encore actives (une saisie ouverte ou à venir ; les campagnes
   passées sont masquées), 5 dernières `DpeDemande`, liens fiche utilisateur et « se connecter en tant que ».
6. **Assistant IA** (`AssistantIaAssistance`, contrôleur Stimulus `assistance-ia`) : sur la fiche d'une demande (hors
   annonce), « Résumer la demande » (Problème / Déjà fait / Reste à faire) et « Proposer une réponse » (brouillon chargé
   dans l'éditeur Trix du formulaire de réponse, jamais envoyé seul ; `[à compléter]` là où l'information manque).
   LLM de l'établissement (Oratio / ROMEO), même configuration que la synthèse IA des fiches matières
   (`docs/composants/synthese-ia.md`) ; sans IA configurée, le panneau n'est pas affiché. Envoyé au modèle : sujet, nom
   de l'usager, formation/parcours rattachés et les 20 derniers messages et notes internes, contenu propre seulement
   (historique cité retiré, adresses mail masquées, 3 000 caractères par message, pas de pièce jointe). Résultat en cache
   7 jours selon le contenu (un nouveau message en produit un nouveau) ; un second clic régénère (`forcer`).

## Pages et actions

| Route | Rôle |
|---|---|
| `app_assistance_index` | onglets `vue` = `demandes` (défaut) / `notifications` / `annonces` avec compteurs (`DemandeAssistanceRepository::compterParVue`) ; liste filtrable (statut, responsable dont « Mes demandes » et « Sans responsable » = `responsable=aucun`, recherche ; appliqués à la saisie par le contrôleur Stimulus `auto-submit`) ; indicateurs cliquables des demandes à traiter (`indicateurs()` : à moi, nouvelles, en cours, en attente, sans responsable, ouvertes ; annonces et notifications exclues) ; bandeau de couleur par statut (rose nouvelle, ambre en cours, cyan en attente, vert résolue), sujet en gras pour une nouvelle, ligne surlignée et « Vous » pour mes demandes ouvertes ; chaque demande se déplie (`<details>`) sur l'arbre de ses messages (une ligne par message : expéditeur, extrait sans historique, date ; lien vers `#message-<id>`) |
| `app_assistance_show` | fil de la demande en arbre, réponse, note interne, statut, responsable, formation/parcours, fusion, contexte |
| `app_assistance_{statut,responsable,note,rattacher,fusionner,repondre}` | POST, jeton CSRF `assistance_<id>` |
| `app_assistance_relever` | POST, jeton `assistance_relever` |
| `app_assistance_ia` | POST `/{id}/ia/{resume\|brouillon}`, jeton `assistance_<id>`, option `forcer` ; réponse JSON `{"texte", "html"}`, ou `{"erreur"}` en 502 (IA absente ou en échec, erreur journalisée) |
| `app_assistance_relever_auto` | POST, jeton `assistance_relever`, réponse JSON `{"nouveaux": n\|null}` ; appelé par le contrôleur Stimulus `assistance-releve` à l'ouverture de la liste, qui recharge la page si de nouveaux mails arrivent. Relève espacée d'au moins `ReleveWebAssistance::INTERVALLE_MINIMUM` = 60 s (date de la dernière relève dans `cache.app`, tous administrateurs confondus) ; boîte non configurée, relève en cours ou erreur IMAP → `null` (erreur journalisée), la page reste utilisable |

Fil en arbre (`FilMessagesAssistance`, façon Thunderbird) : chaque message est placé sous celui auquel il répond
(`In-Reply-To` au sein de la demande) ; parent absent, sans `In-Reply-To` ou note interne → racine ; tri par date ;
décalage plafonné à `PROFONDEUR_MAX` = 3 ; un fil mal formé (réponses mutuelles) affiche chaque message une fois.
Les messages des demandes listées sont chargés avec la liste (jointure dans `DemandeAssistanceRepository::rechercher`).

Affichage d'un message (`MessageAssistanceCard`) : l'historique cité (« Le … a écrit : », en-têtes Outlook
« De : … Envoyé : », `blockquote`, `gmail_quote`, message transféré, lignes `>`) est replié derrière « Afficher
l'historique » (`SeparateurCitation` ; les deux parties du HTML repassent par le sanitizer) ; les adresses `https`
écrites en clair deviennent des liens (`LienAutomatique`, nouvel onglet imposé par le sanitizer) ; au-delà de 5
destinataires, la liste est repliée (« et N autres »).

Fusion : les messages vont sur la demande la plus ancienne, l'autre est supprimée. Assigner un responsable à une
demande « Nouvelle » la passe « En cours ».

## Configuration

Variables (`.env` : valeurs vides ; renseigner dans `.env.local` ou l'environnement serveur, jamais de secret
versionné) : `ASSISTANCE_IMAP_HOST` (vide = relève désactivée), `ASSISTANCE_IMAP_PORT` (993),
`ASSISTANCE_IMAP_ENCRYPTION` (`ssl`), `ASSISTANCE_IMAP_USER`, `ASSISTANCE_IMAP_PASSWORD`, `ASSISTANCE_IMAP_FOLDER`
(`INBOX`). Utiliser une **boîte dédiée** (ex. boîte fonctionnelle `oreof@`), jamais une boîte personnelle.
URCA : hôte `imaps.univ-reims.fr` (le certificat ne couvre pas `mail.univ-reims.fr`), dossiers préfixés `INBOX.`
(ex. `INBOX.OREOF` pour un dossier alimenté par un filtre Roundcube sur `oreof@univ-reims.fr`).

## Import d'historique et tests

`php bin/console app:assistance:importer-eml <fichier|dossier>` intègre des `.eml` (export Roundcube : sélectionner
les mails → « Plus » → « Télécharger (.eml) »). Fixtures : `tests/Fixtures/assistance/` ; tests :
`tests/Unit/Service/Assistance/`, `tests/Integration/Assistance/`, `tests/Functional/Controller/AssistanceControllerTest.php`.

## Limites

- Une réponse envoyée depuis un webmail n'apparaît que si `oreof@` est en copie.
- Le rattachement à une formation/un parcours est manuel.
- Les images intégrées aux mails ne sont pas affichées (supprimées au nettoyage).
- Une annonce envoyée en copie cachée (Cci) n'est pas reconnue : les destinataires cachés ne figurent pas dans le mail.
- Un usager mis en copie d'une réponse de l'équipe et qui poursuit le fil rejoint la demande d'origine ; il n'existe
  pas d'action pour séparer une demande.
