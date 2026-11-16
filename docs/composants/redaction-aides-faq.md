# Rédiger une aide ou une question de FAQ (guide pour IA et rédacteurs)

Quand lire : écrire ou réécrire le contenu d'une aide contextuelle ou d'une question de FAQ, notamment en le faisant
produire par une IA. Pour le code (entités, éditeur, import/export), voir `aides-faq.md`.
À mettre à jour si : `src/Twig/HelpExtension.php` (rendu Markdown), `assets/styles/app.css` (`.app-help-markdown`),
`public/js/editor_helpers.js` (snippets de la toolbar), `src/Form/HelpType.php`, `src/Form/FaqType.php`,
`src/Service/HelpTransferService.php` (format du manifest), `config/services.yaml` (contexte `help_images`),
`src/Command/Migration/V2HelpSeedData.php` (aides de référence), `src/Enums/CentreGestionEnum.php`.

## 1. Ce qu'on rédige

| | Aide contextuelle (`Help`) | Question de FAQ (`Faq`) |
|---|---|---|
| Rôle | explique **une page précise** de l'application | répond à **une question transverse** |
| Clé | `routeSlug` = nom de la route Symfony de la page (ou `erreur`) | `question` (texte, 500 car. max) |
| Champs | `title` (255), `content` (Markdown), `isActive`, `centresShow` | `question`, `reponse` (Markdown), `isActive`, `centresShow`, `ordre` |
| Affichage | panneau latéral étroit de la page, ou `/aide` (catalogue) | `/faq` : accordéon filtrable par mot-clé |
| Titre affiché | `title` ; le contenu commence par un `# Titre` | `question` en gras ; la réponse n'a **pas** de titre |

Trouver le `routeSlug` d'une page : sur la page, ouvrir le panneau d'aide puis « Créer une aide » (slug pré-rempli), ou
`php bin/console debug:router` (dans le conteneur : `make cli APP=v2`). Une route dont le chemin contient `admin` est
automatiquement réservée aux `ROLE_ADMIN` (`HelpGrantService`). Les routes `_*` et `api_*` ne sont pas proposées.

Aides de référence (style à imiter) : `app_login` (« Première connexion à l'ORéOF »), `app_register`, `erreur` (tableau),
`app_help_new`, `app_help_edit` (guide Markdown) dans `src/Command/Migration/V2HelpSeedData.php`.

## 2. Ton et vocabulaire

- Français, **vouvoiement**, phrases courtes, verbes d'action à l'infinitif ou à l'impératif (« Cliquez sur… »). Public :
  gestionnaires et enseignants de l'université, pas des informaticiens.
- Nommer les éléments d'interface **exactement** comme à l'écran : bouton en gras entre guillemets français
  **« Se connecter avec votre compte URCA »**. Relire le template Twig de la page pour copier les libellés.
- Vocabulaire métier existant : parcours, fiche matière, UE, élément constitutif, MCCC, centre de gestion, DPE…
  Ne pas inventer de synonymes ni de fonctionnalités : ne documenter que ce que le code de la page fait réellement.
- Nom de l'application : **ORéOF** (avec accents). Contact support : `oreof@univ-reims.fr` en lien `mailto:`.
- Termes techniques (`ROLE_ADMIN`, slug, route) en `code` ; ne les utiliser que dans les aides destinées aux admins.
- Pas de promesse de délai ni de procédure interne non vérifiée. Si un fait manque : le signaler au relecteur, ne pas
  le deviner.

## 3. Structure type d'une aide

```markdown
# Titre court de la page

Une ou deux phrases : à quoi sert la page, ce qu'on peut y faire.

---

<details>
<summary><strong>Première section (tâche ou question)</strong></summary>

1. Étape une, avec le **« Bouton »** concerné.
2. Étape deux.

> Remarque ou avertissement important.

</details>

<details>
<summary><strong>Ça ne fonctionne pas</strong></summary>

- Cause fréquente et remède.
- Toujours bloqué ? Écrivez à [oreof@univ-reims.fr](mailto:oreof@univ-reims.fr).

</details>
```

Règles observées dans les aides livrées :

- **Un seul `# H1`**, première ligne, puis une courte intro et `---`. Sous-titres en `##`/`###`/`####` ; jamais un second H1.
- Le détail se range dans des accordéons `<details>` (fermés par défaut) : un par tâche ou par question. Une aide courte
  (≤ 10 lignes) peut s'en passer.
- Ordre des sections : **parcours nominal** d'abord, cas particuliers, puis « Ça ne fonctionne pas / Questions
  fréquentes » en dernier.
- Une idée par puce ; listes numérotées pour les étapes ordonnées, à puces sinon.
- Taille cible : 150 à 500 mots. Au-delà, scinder en sections ou déplacer une partie en FAQ.
- Une aide décrit **sa page** ; une procédure qui traverse plusieurs pages va en FAQ (avec liens).

## 4. Structure type d'une question de FAQ

- `question` : formulée comme l'usager la poserait, à la première personne ou à l'infinitif : « Comment demander un accès
  à l'ORéOF ? », « Pourquoi ne vois-je pas mon parcours ? ». Unique (clé de rapprochement à l'import), 500 car. max.
- `reponse` : réponse directe dès la première phrase, puis les étapes. **Pas de titre `#`**, pas d'accordéon
  (la question en est déjà un). 50 à 250 mots. Renvoyer vers l'aide de la page si elle existe.
- `ordre` : entier croissant, regrouper les questions d'un même thème.

## 5. Syntaxe Markdown autorisée

Rendu serveur : CommonMark + tableaux + `~~barré~~`, `html_input: allow`, `allow_unsafe_links: false`
(`HelpExtension`). Aperçu de l'éditeur : `marked` (mêmes réglages, sans `breaks`).

| Besoin | Syntaxe |
|---|---|
| Gras / italique / barré | `**g**` `*i*` `~~b~~` |
| Nom de bouton ou de champ | `**« Libellé »**` ; touche/valeur/code : `` `valeur` `` |
| Titres | `##`, `###`, `####` (H1 une seule fois en tête d'aide) |
| Séparateur | `---` seul sur sa ligne, lignes vides autour |
| Listes | ligne vide **avant** la liste ; `-` ou `1.` ; sous-niveau indenté de 4 espaces |
| Encadré / avertissement | `> Texte` (un seul style de citation, pas de variantes info/danger) |
| Tableau | syntaxe GFM `\| a \| b \|` ; **3 colonnes max** (panneau étroit) |
| Lien externe | `[texte](https://…)` ; `mailto:` accepté ; `javascript:` refusé |
| Accordéon | bloc `<details>` ci-dessous |
| Image | `![texte alternatif](/uploads/help_images/<fichier>)` |
| Vidéo | `[embed](https://www.youtube.com/watch?v=ID)` seul sur sa ligne (YouTube, Vimeo → lecteur 16:9) |

Accordéon exact (même snippet que le bouton « Menu » de la toolbar) :

```markdown
<details>
<summary><strong>Titre</strong></summary>

Contenu en Markdown.

</details>
```

Pièges de rendu :

- Ligne vide obligatoire après `</summary>` et avant `</details>`, sinon le Markdown interne n'est pas interprété.
- Un retour à la ligne simple ne crée pas de saut : laisser une ligne vide entre paragraphes.
- Pas d'autre HTML que `<details>`/`<summary>`/`<strong>` : le reste n'est pas stylé ni testé. Pas de `<style>`, de
  `<script>`, d'`<iframe>` écrit à la main (utiliser `[embed](…)`).
- Pas d'emoji décoratifs ; les icônes de la toolbar (🔗 🔽 🖼️ 🎥) ne sont citées que dans l'aide de l'éditeur.
- Ne pas mettre de lien vers une URL interne absolue de pré-prod/prod ; utiliser des chemins relatifs (`/faq`, `/aide`).
- Ne pas citer de données personnelles réelles (noms, mails d'usagers) : exemples fictifs (`prenom.nom@univ-reims.fr`).

## 6. Images

- **Jamais de nom de fichier inventé** : une image doit d'abord exister dans la galerie (bouton « Image » de la toolbar,
  `/administration/help`). Format PNG/JPEG uniquement, 10 Mo max (`help_images`).
- Si l'image n'existe pas encore, l'IA laisse un marqueur explicite à traiter par un humain :
  `<!-- IMAGE À AJOUTER : capture du tableau des aides, filtre « Actives » -->` (commentaire HTML, invisible à l'écran),
  et le liste dans son compte rendu.
- **Capture automatisée par l'IA** (navigateur intégré de Claude Code, session admin locale déjà ouverte sur
  `http://localhost:8821`, aucune dépendance ajoutée au dépôt). Marqueur attendu, assez précis pour être rejoué :
  `<!-- IMAGE À AJOUTER : nom=Gestion_aide_table ; route=/administration/help/ ; zone=main ; état=filtre Actives -->`.
  Procédure :
  1. `resize_window` en 1280x800 (un panneau masqué donne `innerWidth` = 0 et une capture vide), ouvrir la page,
     préparer l'état voulu.
  2. Charger `https://cdn.jsdelivr.net/npm/modern-screenshot@4.4.39/dist/index.js` par `<script>` (autorisation
     utilisateur obtenue pour cette procédure), puis `modernScreenshot.domToBlob(element, {scale: 1})`.
     Vérifier `blob.size > 0` et le rendu (affichage temporaire de l'image par-dessus la page + capture d'écran).
  3. Téléverser depuis la page : `fetch('/admin/help/images/upload', {method:'POST', body: FormData})` avec les champs
     `nom` et `image` (le blob en `nom.png`). La réponse JSON donne `fichier` (nom stocké).
  4. Remplacer le marqueur par `![nom](/uploads/help_images/<fichier>)`, puis `resize_window` preset `desktop`.
  Une capture = une entrée de galerie réelle : ne téléverser que les images retenues, jamais d'essai. Contrôler qu'aucune
  donnée personnelle réelle n'apparaît avant de téléverser. Limite : rendu approché (cette librairie redessine le DOM) ;
  en cas d'écart visible, retomber sur la capture manuelle.
- Texte alternatif = nom parlant de la capture (`Gestion_aide_table`), pas « image ».
- Les captures datent vite : n'illustrer que les écrans stables, et ne pas décrire dans le texte ce que seule l'image montre.

## 7. Visibilité

- `isActive` : `false` = brouillon invisible. **Une aide produite par IA est livrée inactive tant qu'elle n'est pas
  relue par un humain.**
- `centresShow` : liste vide = visible pour tous ceux qui ont accès à la page. Sinon valeurs parmi
  `cg_etablissement`, `cg_composante`, `cg_formation`, `cg_parcours`.
- Pages admin : restriction `ROLE_ADMIN` automatique, rien à faire.

## 8. Livrer le contenu

Deux voies, de la plus sûre à la moins sûre.

1. **Archive d'import (recommandée pour de la production en volume)** : produire le ZIP lu par la page
   `/administration/help/transfert` (cf. `aides-faq.md` pour les garde-fous) :

   ```
   manifest.json
   helps/001-app_login.md
   faqs/001.md
   ```

   ```json
   {
     "format": "oreof-help-export",
     "version": 1,
     "helps": [
       {"routeSlug": "app_login", "title": "Première connexion à l'ORéOF", "isActive": false,
        "centresShow": [], "file": "helps/001-app_login.md"}
     ],
     "faqs": [
       {"question": "Comment demander un accès à l'ORéOF ?", "isActive": false,
        "centresShow": [], "ordre": 10, "file": "faqs/001.md"}
     ],
     "images": []
   }
   ```

   Les `.md` contiennent le Markdown brut (vrais retours à la ligne, UTF-8). Sans « Remplacer les éléments déjà
   présents », seuls les éléments absents (par `routeSlug` / `question`) sont ajoutés ; avec, ils sont écrasés : ne
   cocher qu'après avoir comparé. Le bilan signale les routes inconnues sur l'instance.
2. **Copier-coller** dans l'admin (`/administration/help/new`, `/administration/faq/new`) : un contenu à la fois, aperçu
   en direct à droite. Ne pas générer de dump SQL (les `\n` deviennent littéraux).

Ne pas ajouter d'aide dans `V2HelpSeedData` pour un simple contenu éditorial : ce fichier n'est que le jeu de départ
livré avec la V2 (chaînes PHP à quotes simples, `\n` littéral).

## 9. Prompt type pour une IA

```text
Tu rédiges une aide ORéOF pour la page de route `<routeSlug>` (template : `<chemin du .twig>`, contrôleur : `<chemin>`).
Lis d'abord docs/composants/redaction-aides-faq.md et l'aide de référence app_login dans V2HelpSeedData.
1. Lis le template et le contrôleur : liste les boutons, champs, filtres, états et droits réellement présents.
2. Rédige uniquement ce que la page permet. Libellés copiés mot pour mot. Vouvoiement. Structure du §3.
3. Aucune image inventée : marqueurs `<!-- IMAGE À AJOUTER : … -->`.
4. Rends : title, Markdown complet, isActive=false, centresShow=[], et la liste des points à vérifier par un humain
   (fait incertain, image manquante, droit particulier).
```

Variante FAQ : remplacer par « une question + sa réponse (§4) », demander 3 à 5 questions issues des cas d'erreur ou
de blocage visibles dans le code (messages `addFlash`, `denyAccessUnlessGranted`, validations de formulaire).

## 10. Checklist de relecture

- [ ] La page décrite existe et les libellés sont ceux de l'écran.
- [ ] Un seul `#`, accordéons bien fermés, lignes vides après `</summary>` / avant `</details>`, listes précédées d'une
      ligne vide.
- [ ] Tableaux ≤ 3 colonnes ; aucune image sans fichier réel ; aucun lien interne absolu.
- [ ] Aucun fait non vérifié (délai, personne, règle de gestion) ; contact `oreof@univ-reims.fr` correct.
- [ ] Aperçu de l'éditeur et rendu final (panneau d'aide + `/faq`) identiques ; test en thème sombre si tableau/encadré.
- [ ] `isActive` basculé à `true` seulement après relecture humaine.
