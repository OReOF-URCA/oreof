# Module Assistance (suivi des demandes reçues par mail) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** relever une boîte IMAP (ou des `.eml`), regrouper les mails en demandes avec statut/responsable, répondre
depuis ORéOF et afficher le contexte ORéOF de l'usager, sous `/administration/assistance`.

**Architecture:** une source de mails (`SourceMailInterface` : IMAP ou fichiers `.eml`) produit des DTO `MailEntrant` ;
`IntegrationMailAssistance` les déduplique, les rattache à une `DemandeAssistance` (fil → sujet normalisé → nouvelle)
et les persiste. Contrôleur admin + Twig Components pour l'UI ; `ReponseAssistance` envoie via Symfony Mailer avec les
en-têtes de fil.

**Tech Stack:** Symfony 8 / PHP 8.4, Doctrine, Twig Components, Tailwind, `webklex/php-imap` (pur PHP),
`symfony/html-sanitizer`, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-13-assistance-mails-design.md`

## Global Constraints

- Toutes les commandes passent par Docker : `make cli APP=v2` puis la commande, ou
  `docker exec oreof-web-v2 <commande>`. Jamais `APP=v1`.
- Routes sous `/administration/assistance` (préfixe de nom `app_assistance_`), accès `ROLE_ADMIN` uniquement.
- Identifiants IMAP uniquement en variables d'environnement `ASSISTANCE_IMAP_HOST`, `ASSISTANCE_IMAP_PORT`
  (défaut `993`), `ASSISTANCE_IMAP_ENCRYPTION` (défaut `ssl`), `ASSISTANCE_IMAP_USER`, `ASSISTANCE_IMAP_PASSWORD`,
  `ASSISTANCE_IMAP_FOLDER` (défaut `INBOX`) ; valeurs vides dans `.env`, jamais de secret commité.
- La boîte n'est jamais modifiée (pas de flag, pas de déplacement, pas de suppression).
- Statuts : `nouvelle`, `en_cours`, `en_attente_usager`, `resolue`. Sens : `entrant`, `sortant`, `note`.
- Regroupement par sujet : fenêtre de **30 jours** sur `dateDernierMessage`. Relève par défaut : **7 jours**.
- HTML de mail nettoyé à l'enregistrement, jamais rendu brut (`|raw` interdit sur du contenu non nettoyé).
- Pièces jointes stockées sous `%kernel.project_dir%/var/assistance/`, nom généré, servies par contrôleur admin.
- Adresse de l'équipe : `App\Classes\Mailer::MAIL_GENERIC` (`oreof@univ-reims.fr`).
- Vocabulaire et libellés en français ; diffs minimaux ailleurs dans le code.

## Review Focus

1. Mail sans en-tête `Message-ID` → un identifiant stable est calculé (`sha1` de expéditeur + date + sujet), une
   deuxième relève ne crée pas de doublon. (Task 4)
2. Sujet encodé MIME (`=?UTF-8?B?…?=`) et préfixes empilés (`RE: TR: Re:`) → même sujet normalisé que l'original. (Tasks 2, 3)
3. Réponse automatique / rebond (`Auto-Submitted` ≠ `no`, ou expéditeur `MAILER-DAEMON`/`postmaster`) → rattachée au
   fil mais ne rouvre pas une demande résolue. (Task 4)
4. Mail envoyé par l'équipe depuis Roundcube avec `oreof@` en copie (expéditeur = membre ou `oreof@`) → enregistré
   en `sortant`, ne change pas le demandeur. (Task 4)
5. Mail HTML seul (pas de partie texte) → `corpsTexte` dérivé du HTML (balises retirées) pour la recherche et l'aperçu. (Task 3)

---

### Task 1: Dépendances, enums, entités, migration

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require webklex/php-imap symfony/html-sanitizer`)
- Create: `src/Enums/StatutDemandeAssistanceEnum.php`, `src/Enums/SensMessageAssistanceEnum.php`
- Create: `src/Entity/DemandeAssistance.php`, `src/Entity/MessageAssistance.php`, `src/Entity/PieceJointeAssistance.php`
- Create: `src/Repository/DemandeAssistanceRepository.php`, `src/Repository/MessageAssistanceRepository.php`,
  `src/Repository/PieceJointeAssistanceRepository.php`
- Create: `migrations/Version20261113100000.php` (généré par `doctrine:migrations:diff`, relu)
- Test: `tests/Integration/Doctrine/AssistancePersistenceTest.php`

**Interfaces:**
- Produces:
  - `StatutDemandeAssistanceEnum: string` (cases ci-dessus) avec `getLabel(): string` et `getBadge(): string`
    (implémente `BadgeEnumInterface` comme les enums existants).
  - `SensMessageAssistanceEnum: string`.
  - `DemandeAssistance` : `sujet` (255), `sujetNormalise` (255, index), `statut` (enum, défaut `nouvelle`),
    `demandeurEmail` (180), `demandeurNom` (255, nullable), `demandeur` (ManyToOne User, nullable, onDelete SET NULL),
    `responsable` (idem), `parcours` / `formation` (ManyToOne nullable, SET NULL), `created` (datetime immutable),
    `dateDernierMessage` (datetime immutable), `messages` (OneToMany ordonné `dateEnvoi ASC`, cascade persist,
    orphanRemoval). Méthode `addMessage(MessageAssistance $m): static` met à jour `dateDernierMessage` si plus récent.
  - `MessageAssistance` : `demande`, `sens`, `messageId` (255, nullable, **unique**), `inReplyTo` (255, nullable),
    `references` (text, nullable), `expediteurEmail`, `expediteurNom`, `destinataires` (json : liste d'emails),
    `sujet`, `corpsTexte` (text), `corpsHtml` (text, nullable), `dateEnvoi`, `auteur` (User nullable),
    `automatique` (bool, défaut false), `piecesJointes` (OneToMany, cascade persist).
  - `PieceJointeAssistance` : `message`, `nomOriginal`, `mimeType`, `taille` (int), `chemin` (relatif à `var/assistance/`).
  - `MessageAssistanceRepository::findOneByMessageIds(array $ids): ?MessageAssistance`
  - `DemandeAssistanceRepository::findRecenteParSujet(string $sujetNormalise, DateTimeImmutable $depuis): ?DemandeAssistance`
    (la plus récente par `dateDernierMessage`).

- [ ] **Step 1:** `composer require webklex/php-imap symfony/html-sanitizer` dans le conteneur ; vérifier que le
  conteneur démarre (`php bin/console about`).
- [ ] **Step 2: Write the failing test** `AssistancePersistenceTest` (base `App\Tests\Support\TestCase` + `DatabaseTrait`) :
  - `testDemandeAvecMessagesEstPersistee` : demande + 2 messages, après `clearEntityManager()` → `assertCount(2, $demande->getMessages())`,
    `dateDernierMessage` = date du message le plus récent.
  - `testFindOneByMessageIdsTrouveParReference` : message `messageId = '<a@x>'` → `findOneByMessageIds(['<z@x>', '<a@x>'])` le renvoie.
  - `testFindRecenteParSujetIgnoreLesDemandesAnciennes` : demande à J-31 → `null` avec `$depuis = J-30`.
- [ ] **Step 3:** lancer `php bin/phpunit tests/Integration/Doctrine/AssistancePersistenceTest.php` → FAIL (classes absentes).
- [ ] **Step 4:** créer enums, entités, repositories ; `php bin/console doctrine:migrations:diff`, relire la migration
  (ne doit toucher que les 3 tables), `doctrine:migrations:migrate -n`, et le schéma de test
  (`APP_ENV=test php bin/console doctrine:schema:update --force` si la base de test n'est pas migrée).
- [ ] **Step 5:** relancer le test → PASS.
- [ ] **Step 6: Commit** `feat(assistance): entités demandes, messages et pièces jointes`

### Task 2: Normalisation du sujet

**Files:**
- Create: `src/Service/Assistance/SujetNormaliseur.php`
- Test: `tests/Unit/Service/Assistance/SujetNormaliseurTest.php`

**Interfaces:**
- Produces: `SujetNormaliseur::normaliser(string $sujet): string` — retire en boucle, en début de chaîne et sans
  casse, les préfixes `re`, `tr`, `fw`, `fwd`, `aw`, `réf` suivis de `:` (avec éventuel `[n]`), les balises
  `[ORéOF]`/`[OREOF]`, `***IMPORTANT***`, puis espaces multiples → un espace, trim, minuscules (`mb_strtolower`),
  tronqué à 255. Sujet vide → `''`.

- [ ] **Step 1: Write the failing test** avec `#[DataProvider]` :
  - `'Re: Demande de mise à jour des informations du parcours LP GEOTP'` → `'demande de mise à jour des informations du parcours lp geotp'`
  - `'RE: TR: Re:  Demande de mise à jour des informations du parcours LP GEOTP'` → même valeur
  - `'Fwd: Fwd: URGENT : OREOF - Validation offre de formation Odontologie'` → `'urgent : oreof - validation offre de formation odontologie'`
  - `'[ORéOF] Accès à l\'application'` → `'accès à l\'application'`
  - `'Re[2]: Accès DPE'` → `'accès dpe'`
  - `''` → `''`
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter. **Step 4:** lancer → PASS.
- [ ] **Step 5: Commit** `feat(assistance): normalisation des sujets de mail`

### Task 3: DTO `MailEntrant` et sources (`.eml`, IMAP)

**Files:**
- Create: `src/DTO/Assistance/MailEntrant.php`, `src/DTO/Assistance/PieceJointeEntrante.php`
- Create: `src/Service/Assistance/Source/SourceMailInterface.php`, `src/Service/Assistance/Source/MailEntrantFactory.php`,
  `src/Service/Assistance/Source/EmlSource.php`, `src/Service/Assistance/Source/ImapSource.php`
- Modify: `.env` (variables `ASSISTANCE_IMAP_*` vides), `config/services.yaml` (bind des paramètres IMAP sur `ImapSource`)
- Create: `tests/Fixtures/assistance/` : `01-demande.eml` (texte + PJ PDF), `02-reponse.eml` (`In-Reply-To` de 01,
  sujet `RE: …`), `03-nouveau-mail-meme-sujet.eml` (pas d'en-tête de fil, autre expéditeur), `04-html-seul.eml`
  (HTML avec `<script>` et `<img src="http://…">`, sujet encodé `=?UTF-8?B?…?=`), `05-sans-message-id.eml`,
  `06-rebond.eml` (`From: MAILER-DAEMON@…`, `Auto-Submitted: auto-replied`)
- Test: `tests/Unit/Service/Assistance/EmlSourceTest.php`

**Interfaces:**
- Produces:
  - `final readonly class MailEntrant` : `?string $messageId`, `?string $inReplyTo`, `list<string> $references`,
    `string $expediteurEmail` (minuscules), `?string $expediteurNom`, `list<string> $destinataires` (to + cc,
    minuscules), `string $sujet` (décodé), `string $corpsTexte`, `?string $corpsHtml` (brut, non nettoyé),
    `DateTimeImmutable $date`, `bool $automatique`, `list<PieceJointeEntrante> $piecesJointes`.
  - `final readonly class PieceJointeEntrante` : `string $nom`, `string $mimeType`, `string $contenu`.
  - `interface SourceMailInterface { /** @return iterable<MailEntrant> */ public function lire(DateTimeImmutable $depuis): iterable; }`
  - `MailEntrantFactory::depuisMessage(\Webklex\PHPIMAP\Message $message): MailEntrant` — utilisée par les deux
    sources ; `corpsTexte` = partie texte, sinon HTML sans balises (`strip_tags` + `html_entity_decode`) ;
    `automatique` = `Auto-Submitted` présent et ≠ `no`, ou expéditeur commençant par `mailer-daemon@` / `postmaster@`.
  - `EmlSource::__construct(string $chemin)` (fichier ou dossier, `*.eml` triés par nom) ; ignore `$depuis`.
  - `ImapSource` : connexion `Webklex\PHPIMAP\ClientManager` (`protocol: 'imap'`, sans extension), dossier
    configuré, `->query()->since($depuis)->leaveUnread()->get()`. Pas de test automatisé (pas de boîte) ; erreur de
    connexion → exception `RuntimeException` au message lisible (« Connexion IMAP impossible : … »).

- [ ] **Step 1:** écrire les 6 fixtures `.eml`.
- [ ] **Step 2: Write the failing test** `EmlSourceTest` :
  - `testLitTousLesFichiersDuDossier` → 6 `MailEntrant`.
  - `testReponseExposeInReplyTo` : 02 → `inReplyTo` = `messageId` de 01.
  - `testSujetEncodeEstDecode` : 04 → `sujet` lisible avec accents.
  - `testHtmlSeulProduitUnCorpsTexte` : 04 → `corpsTexte` non vide, sans `<`.
  - `testPieceJointeEstLue` : 01 → 1 PJ, `mimeType` `application/pdf`, contenu non vide.
  - `testRebondEstAutomatique` : 06 → `automatique === true` ; 01 → `false`.
  - `testSansMessageIdDonneNull` : 05 → `messageId === null`.
- [ ] **Step 3:** lancer → FAIL. **Step 4:** implémenter DTO, factory, sources, config. **Step 5:** lancer → PASS.
- [ ] **Step 6: Commit** `feat(assistance): lecture des mails depuis IMAP ou fichiers .eml`

### Task 4: Intégration et regroupement des mails

**Files:**
- Create: `src/Service/Assistance/IntegrationMailAssistance.php`, `src/Service/Assistance/StockagePieceJointe.php`
- Modify: `config/packages/html_sanitizer.yaml` (sanitizer `assistance` : éléments sûrs, `allow_relative_medias: false`,
  médias distants bloqués, liens forcés en `https`/`mailto`)
- Test: `tests/Integration/Assistance/IntegrationMailAssistanceTest.php`

**Interfaces:**
- Consumes: Task 1 (entités, repositories), Task 2 (`normaliser`), Task 3 (`MailEntrant`).
- Produces:
  - `IntegrationMailAssistance::integrer(MailEntrant $mail): ?MessageAssistance` — `null` si déjà intégré.
    Ordre : (1) id effectif = `messageId` ?? `'<'.sha1(expediteur|date ISO|sujet).'@assistance.oreof>'` ; existe → `null`.
    (2) `findOneByMessageIds([inReplyTo, ...references])` → sa demande. (3) sinon `findRecenteParSujet(normalise, now -30 j)`.
    (4) sinon nouvelle demande (`sujet` = sujet du mail, demandeur = expéditeur, `demandeur` = User par email).
    Sens `sortant` si l'expéditeur est `Mailer::MAIL_GENERIC` ou un User `ROLE_ADMIN`, sinon `entrant`.
    Un `entrant` non `automatique` sur une demande `resolue`/`en_attente_usager` → `en_cours`.
    HTML nettoyé via le sanitizer `assistance`. Flush à la fin ; `UniqueConstraintViolationException` → `null`
    (relève concurrente).
  - `IntegrationMailAssistance::integrerTout(SourceMailInterface $source, DateTimeImmutable $depuis): int` (nombre intégrés).
  - `StockagePieceJointe::enregistrer(PieceJointeEntrante $pj): string` (chemin relatif `AAAA/MM/<uuid>`),
    `StockagePieceJointe::cheminAbsolu(PieceJointeAssistance $pj): string`.

- [ ] **Step 1: Write the failing test** (base `TestCase` + `DatabaseTrait`, `EmlSource` sur les fixtures, répertoire
  de PJ redirigé vers un dossier temporaire) :
  - `testReponseRejointLaDemandeDOrigine` : 01 puis 02 → 1 demande, 2 messages.
  - `testMemeSujetSansFilRejointLaDemande` : 01 puis 03 → 1 demande.
  - `testMemeSujetApres30JoursCreeUneNouvelleDemande` : demande existante à J-31 + 03 → 2 demandes.
  - `testDeuxiemeIntegrationNeCreePasDeDoublon` : `integrerTout` deux fois → second appel renvoie `0` ; idem pour 05.
  - `testEntrantRouvreUneDemandeResolue` : demande `resolue` + 02 → `en_cours`.
  - `testRebondNeRouvrePas` : demande `resolue` du fil de 06 → reste `resolue`.
  - `testMailDeLEquipeEstSortant` : expéditeur `oreof@univ-reims.fr` → sens `sortant`, `demandeurEmail` inchangé.
  - `testHtmlEstNettoye` : 04 → `corpsHtml` sans `<script` ni `http://`.
  - `testPieceJointeEstStockee` : 01 → fichier présent à `cheminAbsolu`.
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter. **Step 4:** lancer → PASS.
- [ ] **Step 5: Commit** `feat(assistance): regroupement des mails en demandes`

### Task 5: Commandes `app:assistance:importer-eml` et `app:assistance:relever`

**Files:**
- Create: `src/Command/AssistanceImporterEmlCommand.php`, `src/Command/AssistanceReleverCommand.php`
- Modify: `docs/ops/command.md` (les deux commandes), `docs/ops/install.md` (cron `*/5 * * * *` + variables `ASSISTANCE_IMAP_*`)
- Test: `tests/Integration/Assistance/AssistanceCommandesTest.php`

**Interfaces:**
- Consumes: Task 3 (`EmlSource`, `ImapSource`), Task 4 (`integrerTout`).
- Produces: `app:assistance:importer-eml <chemin>` ; `app:assistance:relever [--depuis=7]` (jours). Sortie :
  `N mail(s) intégré(s).` ; `relever` sans `ASSISTANCE_IMAP_HOST` → code `1` et « Boîte IMAP non configurée ».

- [ ] **Step 1: Write the failing test** (`CommandTester`) : `importer-eml tests/Fixtures/assistance` → code 0,
  sortie contient `6 mail(s) intégré(s).` ; deuxième exécution → `0 mail(s) intégré(s).` ;
  `relever` sans configuration → code 1.
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter + doc. **Step 4:** lancer → PASS.
- [ ] **Step 5: Commit** `feat(assistance): commandes de relève IMAP et d'import .eml`

### Task 6: Interface admin (liste, détail, actions, menu)

**Files:**
- Create: `src/Controller/AssistanceController.php` (`#[Route('/administration/assistance', name: 'app_assistance_')]`,
  comme `TutorielAdminController`)
- Create: `src/Service/Assistance/FusionDemandesAssistance.php`
- Create: `src/Form/Assistance/NoteAssistanceType.php`, `src/Form/Assistance/RattachementAssistanceType.php`
  (autocomplete parcours/formation via les champs autocomplete existants)
- Create: `templates/assistance/index.html.twig`, `templates/assistance/show.html.twig`,
  `src/Twig/Components/Assistance/MessageAssistanceCard.php` + `templates/components/Assistance/MessageAssistanceCard.html.twig`
- Modify: `src/Navigation/Provider/AdministrationMenuProvider.php` (lien `administration.assistance`, icône
  `mdi:lifebuoy`, route `app_assistance_index`), `translations/` (clé `menu.config.assistance` → « Assistance »)
- Test: `tests/Functional/Controller/AssistanceControllerTest.php`, `tests/Integration/Assistance/FusionDemandesAssistanceTest.php`

**Interfaces:**
- Consumes: Task 1, Task 4 (`integrerTout` pour le bouton « Relever maintenant », via `ImapSource`).
- Produces routes : `app_assistance_index` GET `/administration/assistance` (query `statut`, `responsable` = `moi`|id,
  `q`) ; `app_assistance_show` GET `/{id}` ; POST (CSRF) `/{id}/statut`, `/{id}/responsable`, `/{id}/note`,
  `/{id}/rattacher`, `/{id}/fusionner/{source}`, `/relever` ; `app_assistance_piece_jointe` GET `/piece-jointe/{id}`
  (`BinaryFileResponse`, `Content-Disposition: attachment`).
  `FusionDemandesAssistance::fusionner(DemandeAssistance $cible, DemandeAssistance $source): void` — déplace les
  messages, supprime la source, recalcule `dateDernierMessage`.
- Liste : compteur des demandes ouvertes en en-tête de page (le menu ne gère pas de badge), par défaut demandes non résolues, tri `dateDernierMessage DESC`, pagination 30. Détail : messages
  chronologiques ; entrant à gauche, sortant à droite, note interne sur fond jaune ; `corpsHtml` (déjà nettoyé) rendu
  dans le composant, sinon `corpsTexte|nl2br`.

- [ ] **Step 1: Write the failing tests** :
  - Fonctionnel : `testUtilisateurNonAdminEstRefuse` (ROLE_USER → 403) ; `testListeAfficheLesDemandesOuvertes`
    (demande `nouvelle` visible, `resolue` absente) ; `testChangerLeStatut` (POST avec CSRF → statut `resolue`) ;
    `testAjouterUneNoteCreeUnMessageNote` ; `testPieceJointeTelechargeable`.
  - Intégration : `testFusionDeplaceLesMessagesEtSupprimeLaSource`.
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter (templates selon `docs/ui-conventions/ui-conventions.md`,
  icônes selon `docs/ui-conventions/icones.md`). **Step 4:** lancer → PASS ; `tests/Smoke` toujours vert.
- [ ] **Step 5: Commit** `feat(assistance): liste et fiche des demandes dans l'administration`

### Task 7: Réponse depuis ORéOF

**Files:**
- Create: `src/Service/Assistance/ReponseAssistance.php`, `src/Form/Assistance/ReponseAssistanceType.php`
- Modify: `src/Controller/AssistanceController.php` (POST `/{id}/repondre`), `templates/assistance/show.html.twig`
- Test: `tests/Integration/Assistance/ReponseAssistanceTest.php`

**Interfaces:**
- Consumes: Task 1, Task 6 (page détail).
- Produces: `ReponseAssistance::repondre(DemandeAssistance $demande, User $auteur, string $corpsHtml, list<string> $destinataires, bool $resoudre = false): MessageAssistance`.
  Construit un `Email` : `From` et `Reply-To` = `ORéOF <Mailer::MAIL_GENERIC>`, sujet `Re: <sujet>` (sans doubler
  `Re:`), en-tête `Message-ID` généré (`<uuid@univ-reims.fr>`) et posé explicitement, `In-Reply-To` = dernier
  `messageId` connu, `References` = ses références + ce `messageId`. Corps nettoyé (sanitizer `assistance`) + version
  texte. Envoi via `MailerInterface` (routé sur `async_email` comme les autres mails). Enregistre un message `sortant`
  avec ce `messageId` ; statut → `en_attente_usager`, ou `resolue` si `$resoudre`.
  Destinataires préremplis : demandeur + expéditeurs/destinataires des messages entrants, hors `oreof@`.

- [ ] **Step 1: Write the failing test** (`MailerAssertionsTrait`) : `testReponseEnvoieUnMailDansLeFil` → 1 mail
  envoyé, `In-Reply-To` = messageId du dernier entrant, sujet `Re: Demande…` ; `testReponseEstEnregistreeEtChangeLeStatut`
  → message `sortant` avec le même `Message-ID`, statut `en_attente_usager` ; `testReponseDeLUsagerSeRattache` :
  `MailEntrant` dont `inReplyTo` = ce `Message-ID` → `integrer` le place dans la même demande.
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter. **Step 4:** lancer → PASS.
- [ ] **Step 5: Commit** `feat(assistance): réponse aux demandes depuis ORéOF`

### Task 8: Panneau contexte usager

**Files:**
- Create: `src/Service/Assistance/ContexteUsagerAssistance.php`,
  `src/Twig/Components/Assistance/ContexteUsager.php` + `templates/components/Assistance/ContexteUsager.html.twig`
- Modify: `templates/assistance/show.html.twig`
- Test: `tests/Integration/Assistance/ContexteUsagerAssistanceTest.php`

**Interfaces:**
- Consumes: Task 1 ; `CampagneAccessibilityService::getModuleStatus(CampagneModuleEnum, ?CampagneCollecte)`,
  `CampagneCollecteRepository` (campagne `defaut = true`), `User::getUserProfils()`, `User::getDpeDemandes()`.
- Produces: `ContexteUsagerAssistance::pour(DemandeAssistance $demande): ?array` — `null` si aucun User ne correspond
  à `demandeurEmail` ; sinon `['user' => User, 'roles' => list<string>, 'compteValide' => bool, 'profils' => list<UserProfil>,
  'campagnes' => list<array{campagne: CampagneCollecte, modules: list<array>}>, 'demandesAcces' => list<DpeDemande> (5 dernières)]`.
  Campagnes = campagne par défaut + campagnes des profils, sans doublon ; `modules` = `getModuleStatus` pour chaque
  `CampagneModuleEnum::cases()`. Le composant affiche aussi les liens fiche utilisateur et « se connecter en tant
  que » (`?_switch_user=<username>`).

- [ ] **Step 1: Write the failing test** : `testSansUtilisateurConnuRenvoieNull` ; `testCampagneSansEtapeAfficheAucunePeriode`
  (campagne par défaut sans `TimelineDate` → un module a `message` = `Aucune période de saisie configurée pour ce module.`) ;
  `testCampagnesDesProfilsSontIncluses` (profil sur une 2ᵉ campagne → 2 campagnes).
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter. **Step 4:** lancer → PASS.
- [ ] **Step 5: Commit** `feat(assistance): contexte ORéOF de l'usager sur la fiche demande`

### Task 9: Documentation et validation finale

**Files:**
- Create: `docs/composants/assistance.md` (en tête « Quand lire » / « À mettre à jour si : `src/Service/Assistance/`,
  `src/Entity/*Assistance*`, `AssistanceController`, commandes `app:assistance:*` ») : fonctionnement, regroupement,
  configuration IMAP, import `.eml` depuis Roundcube, limites (réponses Roundcube sans `oreof@` en copie).
- Modify: `docs/README.md`, `AGENTS.md` (ligne de la table « Routage » : « Module Assistance (demandes par mail) »).

- [ ] **Step 1:** écrire la doc.
- [ ] **Step 2:** `make phpstan APP=v2` → aucune nouvelle erreur ; `make test APP=v2` → vert ; `npm run dev` et
  `npm run lint` si des assets ont bougé.
- [ ] **Step 3:** vérification manuelle : `php bin/console app:assistance:importer-eml tests/Fixtures/assistance`
  dans la base de dev, ouvrir http://localhost:8821/administration/assistance, parcourir liste, fiche, note, fusion.
- [ ] **Step 4: Commit** `docs(assistance): documentation du module Assistance`
