# Module « Assistance » : suivi des demandes reçues par mail — conception

Date : 2026-11-13 · Branche : `feat/assistance-mails`

## Intention

Les demandes d'assistance des usagers arrivent sur l'alias `oreof@univ-reims.fr`, qui redistribue dans les boîtes
personnelles de l'équipe. Dans Roundcube, les échanges d'un même dossier sont éclatés (ex. « LP GEOTP » : 4 mails de
3 expéditeurs différents), les fils se coupent dès qu'un usager écrit un nouveau mail, et rien n'indique ce qui reste
à traiter. Le but : regrouper les échanges en **demandes**, leur donner un **statut** et un **responsable**, et afficher
le **contexte ORéOF** de l'usager (profils, campagne, ouverture des modules) pour répondre sans fouiller l'appli.

Une vraie boîte fonctionnelle `oreof@` a été demandée à la DSI. Le module est construit maintenant et sera branché
dessus quand elle existera ; d'ici là il se teste avec une boîte de test ou des fichiers `.eml`.

## Objectifs

- Relever une boîte IMAP et transformer chaque mail en message rattaché à une demande.
- Regrouper automatiquement les réponses d'un même échange, avec correction manuelle (fusion).
- Statut, responsable, notes internes, rattachement à un parcours/formation.
- Répondre depuis ORéOF : la réponse part de `oreof@` et reste dans le fil.
- Panneau « contexte usager » sur chaque demande.

## Non-objectifs (v1)

- Portail usager (l'usager reste sur le mail).
- Réponses types, SLA, relances automatiques, notifications des nouvelles demandes.
- Détection automatique du parcours concerné dans le texte du mail.
- Modifier la boîte mail (pas de suppression, pas de déplacement).

## Anti-objectifs

- Perdre un mail ou en créer deux fois (doublons à la relève).
- Afficher du HTML de mail non nettoyé (risque XSS dans l'admin).
- Stocker des identifiants de boîte personnelle : la relève n'utilise que les identifiants d'une boîte dédiée.

## Contraintes

- Pas de boîte réelle aujourd'hui (alias) → tout doit pouvoir tourner sur une boîte de test et sur des `.eml`.
- Extension PHP `imap` absente du conteneur → bibliothèque IMAP en PHP pur.
- Pas de Symfony Scheduler dans le projet → relève par commande console + cron serveur.
- Accès réservé à `ROLE_ADMIN` (routes sous `/administration/`, déjà protégées par `access_control`).
- UI en Tailwind + Twig Components (conventions `docs/ui-conventions/ui-conventions.md`).

## Approches envisagées

| Approche | Avantages | Inconvénients |
|---|---|---|
| Roundcube seul (fils + tags) | immédiat, gratuit | fils fragiles, pas de statut partagé, pas de contexte |
| Userscript sur Roundcube | contexte usager sans serveur | statuts stockés dans un seul navigateur |
| FreeScout (outil de tickets) | complet, prêt à l'emploi | nouveau serveur à faire valider, pas de contexte ORéOF |
| **Module dans ORéOF** (retenu) | un seul outil, contexte et actions métier, pas de serveur | plusieurs jours de dev, relève/fils/envoi à notre charge |

Choix de l'utilisatrice : module dans ORéOF. En attendant la boîte, filtre Roundcube + mode « Fils ».

## Conception

### Données

- `DemandeAssistance` : `sujet`, `statut` (enum `StatutDemandeAssistanceEnum` : `nouvelle`, `en_cours`,
  `en_attente_usager`, `resolue`), `demandeurEmail`, `demandeurNom`, `demandeur` (User, nullable, résolu par email),
  `responsable` (User, nullable), `parcours` / `formation` (nullable, rattachement manuel), `created`,
  `dateDernierMessage`, `sujetNormalise` (index).
- `MessageAssistance` : `demande`, `sens` (enum : `entrant`, `sortant`, `note`), `messageId` (unique, nullable pour les
  notes), `inReplyTo`, `references` (texte), `expediteurEmail`, `expediteurNom`, `destinataires` (JSON), `sujet`,
  `corpsTexte`, `corpsHtml` (nettoyé à l'enregistrement), `dateEnvoi`, `auteur` (User, pour sortant/note).
- `PieceJointeAssistance` : `message`, `nomOriginal`, `mimeType`, `taille`, `chemin` (stockage privé
  `var/assistance/`, téléchargement via contrôleur admin).

### Relève (`app:assistance:relever`)

- Connexion IMAP via `webklex/php-imap` (pur PHP), paramètres `ASSISTANCE_IMAP_HOST|PORT|ENCRYPTION|USER|PASSWORD|FOLDER`
  dans `.env.local` (jamais commités).
- Lit les messages des N derniers jours (option `--depuis`, défaut 7 jours), ignore ceux dont le `Message-ID` est déjà
  en base (idempotent, la boîte n'est jamais modifiée).
- Cron proposé : toutes les 5 minutes. Bouton « Relever maintenant » dans l'admin (appelle le même service).
- Commande sœur `app:assistance:importer-eml <fichier|dossier>` : même pipeline à partir de fichiers `.eml`
  (dev, tests, reprise d'historique exporté de Roundcube).

### Regroupement (service `RegroupementAssistance`)

Pour chaque mail entrant, dans l'ordre :
1. `In-Reply-To` ou un `References` correspond à un `messageId` connu → même demande.
2. Sinon, même `sujetNormalise` (sans `Re:`, `RE:`, `TR:`, `Fwd:`, `[ORéOF]`, espaces/casse) qu'une demande dont le
   dernier message date de moins de 30 jours → même demande.
3. Sinon → nouvelle demande, statut `nouvelle`.

Un nouveau message entrant sur une demande `resolue` ou `en_attente_usager` la repasse en `en_cours`.
L'admin peut **fusionner** deux demandes (les messages passent sur la plus ancienne).

### Réponse depuis ORéOF

- Formulaire sur la demande : texte (Trix), destinataires préremplis (demandeur + participants), envoi via
  Symfony Mailer avec `From: ORéOF <oreof@…>`, `In-Reply-To`/`References` du dernier message, sujet `Re: …`.
- Le message est enregistré en `sortant` avec le `Message-ID` généré → la réponse de l'usager se rattache au fil.
- Répondre depuis Roundcube reste possible, mais la réponse n'apparaît dans ORéOF que si `oreof@` est en copie.
- Envoi par la file `async_email` existante.

### Interface (`/administration/assistance`)

- Liste : filtres statut / responsable / « mes demandes », recherche (sujet, email), tri par dernier message,
  compteur des demandes ouvertes dans le menu.
- Détail : fil chronologique (entrants, sortants, notes internes visuellement distincts), pièces jointes, actions
  (statut, responsable, rattacher parcours/formation en autocomplete, fusionner, note interne, répondre).
- Panneau contexte usager : identité, rôles, validation du compte, `UserProfil` (profil, formation, parcours,
  composante, campagne), statut de chaque module (`CampagneAccessibilityService::getModuleStatus`) pour la campagne
  par défaut et celles des profils, dernières `DpeDemande`, liens fiche utilisateur et « se connecter en tant que ».
- Entrée de menu dans la navigation admin (`src/Navigation/`).

### Sécurité

- HTML entrant nettoyé à l'enregistrement (`symfony/html-sanitizer`, à ajouter), jamais rendu brut ; images distantes
  bloquées.
- Pièces jointes hors `public/`, servies par contrôleur `ROLE_ADMIN`, nom de fichier généré.
- Identifiants IMAP uniquement en variables d'environnement.

### Tests

- Unitaires : normalisation du sujet, regroupement (fil, sujet, nouvelle demande, réouverture).
- Fonctionnels : import `.eml` de fixtures → demandes attendues ; accès admin refusé aux non-admins.

### Documentation

- `docs/composants/assistance.md` (+ entrées `docs/README.md` et table « Routage » d'`AGENTS.md`).
- Commandes dans `docs/ops/command.md`, cron dans `docs/ops/install.md`.

## Laissé au constructeur

Noms exacts des composants Twig, découpage des contrôleurs, mise en page fine, pagination.
