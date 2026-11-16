# Assistance : fils de messages en arbre — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher les messages d'une demande d'assistance en arbre (qui répond à qui), dépliable dans la liste et décalé sur la page de la demande.

**Architecture:** Un service PHP ordonne les messages d'une demande en arbre (parcours en profondeur, profondeur plafonnée) et fournit un extrait sans historique cité. La liste rend un `<details>` natif par demande ; la page de la demande rend les cartes dans l'ordre de l'arbre avec un décalage.

**Tech Stack:** Symfony 8 / PHP 8.4, Doctrine, Twig + Twig Components, Tailwind, PHPUnit 13 (Docker `oreof-web-v2`, `-w /var/www/oreofv2`).

**Spec:** `docs/superpowers/specs/2026-11-16-assistance-fils-arborescents-design.md`

## Global Constraints

- Pas de nouveau JS ; dépliage par `<details>` natif.
- Arbre construit depuis `MessageAssistance::inReplyTo` au sein d'une demande ; parent absent, pas d'`In-Reply-To` ou note interne → racine ; tri par `dateEnvoi`.
- Profondeur affichée plafonnée à 3.
- Extrait ≈ 100 caractères, sans historique cité (`SeparateurCitation::separerTexte`).
- Lien d'un message de la liste : `app_assistance_show` + ancre `#message-<id>`.
- Pas de N+1 : messages des demandes listées chargés avec la liste.
- Diffs minimaux, hooks existants (`id`, `data-*`) préservés ; doc `docs/composants/assistance.md` mise à jour.

## Review Focus

- Fil mal formé (deux messages qui se répondent mutuellement, message qui se répond à lui-même) : chaque message affiché exactement une fois, pas de boucle infinie.
- `In-Reply-To` pointant vers un message d'une autre demande (après fusion) : le message reste visible, à la racine.
- Corps texte vide (mail HTML seul, pièce jointe seule) : la ligne de la liste affiche l'expéditeur et la date sans planter.
- Pagination avec chargement des messages joint : 30 demandes par page conservées, total correct.
- Demande avec un seul message : `summary` « 1 message », pas d'arbre vide trompeur.

---

### Task 1: Service `FilMessagesAssistance` (arbre + extrait)

**Files:**
- Create: `src/Service/Assistance/FilMessagesAssistance.php`
- Test: `tests/Unit/Service/Assistance/FilMessagesAssistanceTest.php`

**Interfaces:**
- Consumes: `SeparateurCitation::separerTexte(string): array{0: string, 1: ?string}` ; `MessageAssistance` (getters existants).
- Produces:
  - `FilMessagesAssistance::PROFONDEUR_MAX = 3`
  - `arbre(iterable<MessageAssistance> $messages): list<array{message: MessageAssistance, profondeur: int}>` — ordre de parcours en profondeur, profondeur 0 pour les racines, plafonnée à 3.
  - `extrait(MessageAssistance $message, int $longueur = 100): string` — texte visible (sans historique), espaces compactés, tronqué avec « … ».

- [ ] **Step 1: Write the failing tests** (messages construits en mémoire, `setMessageId`/`setInReplyTo`/`setDateEnvoi`/`setSens`)
  - `testReponsesSontPlaceesSousLeurParent` : A(09:00) ; B répond à A (09:30) ; C répond à B (10:00) ; D répond à A (11:00) → ordre `[A,B,C,D]`, profondeurs `[0,1,2,1]`.
  - `testParentAbsentEtNoteSontDesRacinesTrieesParDate` : X (inReplyTo `<inconnu@x>`, 10:00), note N (09:30, sens NOTE), A (09:00) → `[A,N,X]`, profondeurs `[0,0,0]`.
  - `testProfondeurPlafonneeA3` : chaîne de 6 messages → profondeurs `[0,1,2,3,3,3]`.
  - `testFilEnBoucleAfficheChaqueMessageUneFois` : A répond à B, B répond à A, C répond à lui-même → 3 éléments, chaque message une fois.
  - `testExtraitSansHistoriqueEtTronque` : corps `"Merci pour la correction.\n\nLe 12/11/2026 à 17:58, X a écrit :\n> ancien"` → `'Merci pour la correction.'` ; corps de 300 « a » → longueur 101 (100 + « … ») ; corps vide → `''`.

- [ ] **Step 2: Run** `docker exec -w /var/www/oreofv2 oreof-web-v2 php vendor/bin/phpunit tests/Unit/Service/Assistance/FilMessagesAssistanceTest.php` — Expected: FAIL (classe introuvable).

- [ ] **Step 3: Implement** — index par `messageId` ; parent = message de la liste dont `messageId === inReplyTo` et différent de lui-même ; enfants triés par date ; parcours en profondeur depuis les racines (triées par date) avec ensemble « déjà vu » ; messages jamais atteints (cycles) ajoutés ensuite comme racines par date. Extrait : `separerTexte`, `preg_replace('/\s+/u', ' ')`, `mb_substr`.

- [ ] **Step 4: Run** la même commande — Expected: PASS.

- [ ] **Step 5: Commit** `feat(assistance): ordonnancement des messages d'une demande en arbre`

### Task 2: Liste — demandes dépliables

**Files:**
- Modify: `src/Repository/DemandeAssistanceRepository.php` (`rechercher` : `leftJoin('d.messages', 'm')->addSelect('m')`)
- Modify: `src/Controller/AssistanceController.php` (index : passer `fil` = service `FilMessagesAssistance` au template, ou un tableau `arbres` indexé par id de demande)
- Modify: `templates/assistance/index.html.twig` (cellule Sujet)
- Test: `tests/Functional/Controller/AssistanceControllerTest.php`

**Interfaces:**
- Consumes: `FilMessagesAssistance::arbre()`, `::extrait()` (Task 1).
- Produces: dans la liste, `details.assistance-fil` par demande ; lignes `a[href$="#message-<id>"]` avec `data-profondeur="<n>"`.

- [ ] **Step 1: Write the failing tests**
  - `testListeDeplieLesMessagesEnArbre` : demande avec message A + réponse B (inReplyTo A) → `details.assistance-fil` contient `summary` « 2 messages », un lien `a[href$="#message-{idB}"][data-profondeur="1"]`, l'extrait de B.
  - `testListeUnSeulMessage` : demande à 1 message → `summary` contient « 1 message » (pas « 1 messages »).
  - `testListePaginationAvecMessagesJoints` : 31 demandes ouvertes de 2 messages → 30 lignes `tbody tr` page 1, total « 31 demande(s) ».

- [ ] **Step 2: Run** `docker exec -w /var/www/oreofv2 oreof-web-v2 php vendor/bin/phpunit tests/Functional/Controller/AssistanceControllerTest.php` — Expected: FAIL sur les 3 nouveaux tests.

- [ ] **Step 3: Implement** — `summary` : lien du sujet inchangé + « N message(s) » ; contenu : une ligne par nœud, `padding-left` selon `profondeur` (classes Tailwind fixes `pl-0/pl-4/pl-8/pl-12`), expéditeur (`expediteurNom ?? expediteurEmail`), extrait, `dateEnvoi|date('d/m H:i')`, badge « Note » / « Automatique » si besoin.

- [ ] **Step 4: Run** la même commande — Expected: PASS (tous).

- [ ] **Step 5: Commit** `feat(assistance): liste des demandes dépliable en arbre`

### Task 3: Page de la demande — cartes dans l'ordre de l'arbre

**Files:**
- Modify: `src/Controller/AssistanceController.php` (show : passer `arbre` = `FilMessagesAssistance::arbre($demande->getMessages())`)
- Modify: `templates/assistance/show.html.twig` (boucle des messages)
- Test: `tests/Functional/Controller/AssistanceControllerTest.php`

**Interfaces:**
- Consumes: `FilMessagesAssistance::arbre()` (Task 1) ; `<twig:MessageAssistanceCard :message="…" />` (inchangé).
- Produces: enveloppe `div[data-profondeur]` autour de chaque carte, décalée (`ml-0/ml-6/ml-12/ml-18` ou équivalent) avec bordure gauche si profondeur > 0.

- [ ] **Step 1: Write the failing test** `testFicheAfficheLesReponsesSousLeurParent` : A (09:00), C réponse à A (11:00), B nouveau message racine (10:00) → ordre des `article[id^="message-"]` = `[A, C, B]` ; enveloppe de C a `data-profondeur="1"`.

- [ ] **Step 2: Run** la commande de Task 2 — Expected: FAIL.

- [ ] **Step 3: Implement** la boucle sur `arbre`.

- [ ] **Step 4: Run** — Expected: PASS ; puis suite du module : `php vendor/bin/phpunit tests/Unit/Service/Assistance tests/Integration/Assistance tests/Functional/Controller/AssistanceControllerTest.php` — Expected: OK.

- [ ] **Step 5: Mettre à jour** `docs/composants/assistance.md` (section « Pages et actions » : arbre, `FilMessagesAssistance`, profondeur 3) ; `make phpstan`-équivalent sur `src/Service/Assistance src/Controller/AssistanceController.php src/Repository/DemandeAssistanceRepository.php` — Expected: No errors.

- [ ] **Step 6: Commit** `feat(assistance): fil de la demande affiché en arbre`

### Task 4: Vérification visuelle

- [ ] Ouvrir `http://localhost:8821/administration/assistance`, déplier la demande de Clara Bretaudeau : Laurence apparaît sous la réponse de l'équipe. Ouvrir un message depuis la liste : la page s'ouvre sur l'ancre. Vérifier en mode sombre.
