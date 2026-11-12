# Tutoriels vidéo (`/tutoriels`)

Quand lire : modification de la page des vidéos tutoriel, de son administration ou du bouton « Copier le lien ».
À mettre à jour si : `src/Entity/Tutoriel.php`, `src/Controller/Tutoriel*Controller.php`, `src/Form/TutorielType.php`,
`templates/tutoriel/`, `templates/tutoriel_admin/`, `src/Twig/Components/{TutorielCard,UI/CopyLinkButton}.php`,
`templates/components/{tutoriel_card,_ui/copy_link_button}.html.twig`, `assets/controllers/copy_link_controller.js`.

But : envoyer par mail un lien lisible vers toutes les vidéos tutoriel ou vers une seule. Pages réservées aux
utilisateurs connectés (règle `^/` de `security.yaml`). Un destinataire non connecté passe par la connexion puis
revient sur le lien. Conséquence : un logiciel de messagerie n'affiche pas d'aperçu riche du lien.

## Où regarder

| Sujet | Code |
|---|---|
| Entité | `Tutoriel` (table `tutoriel`) : `titre`, `slug` (unique), `youtubeUrl`, `description`, `duree`, `ordre`, `isActive` ; `getYoutubeId()` extrait l'identifiant (formats `watch?v=`, `youtu.be/`, `embed/`, `shorts/`, `live/`) |
| Pages | `TutorielController` : `/tutoriels` (`app_tutoriel_index`, galerie) et `/tutoriels/{slug}` (`app_tutoriel_show`, lecteur `youtube-nocookie.com` + autres vidéos) |
| Administration | `TutorielAdminController` (`/administration/tutoriels`, `ROLE_ADMIN`), entrée `administration.tutoriels` du méga-menu Administration |
| Formulaire | `TutorielType` : slug déduit du titre s'il est vide, sinon normalisé (`AsciiSlugger`), avant validation ; lien YouTube validé par `Tutoriel::YOUTUBE_ID_PATTERN` |
| Composants | `<twig:TutorielCard :tutoriel="t" />` (vignette `i.ytimg.com`), `<twig:CopyLinkButton url="{{ url(...) }}" />` (réutilisable ailleurs) |
| Accès | bouton « Tutoriels » du pied de page (`_layout/_footer.html.twig`) |

## Règles

- Le slug est l'adresse envoyée par mail : ne pas le recalculer quand le titre change (les anciens liens casseraient).
- Une vidéo non publiée renvoie une 404, sauf pour un administrateur (aperçu avec bandeau).
- Source distincte des aides contextuelles : les vidéos intégrées dans le Markdown des aides (`[embed](...)`, voir
  `aides-faq.md`) restent en place. La table `tutoriel` n'est pas incluse dans l'import/export des aides : saisir
  les vidéos sur chaque instance.
