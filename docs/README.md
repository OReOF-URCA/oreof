# Documentation ORéOF — index

Point d'entrée agents : `AGENTS.md` (racine). Chaque fichier ci-dessous est autonome : ne lire que celui utile.

| Fichier | Contenu | Lire quand |
|---|---|---|
| `ui-conventions/ui-conventions.md` | Composants UI Twig, tokens Tailwind, migration Bootstrap, opacité, checklist PR | toute modif de template/CSS |
| `ui-conventions/icones.md` | Correspondance FontAwesome → alias `icon:*` | remplacement d'icône |
| `composants/navigation.md` | `MenuItem`, providers, droits, pages de section, breadcrumbs | ajout de page/menu |
| `composants/recherche.md` | Recherche approchée (fuzzy) des parcours : moteur, pondération, affichage | page `/recherche/parcours`, réutiliser le moteur fuzzy |
| `composants/aides-faq.md` | Aides contextuelles, FAQ, galerie d'images, éditeur Markdown, import/export entre instances | admin aides/FAQ, transfert vers la pré-prod |
| `formulaires/README.md` | `JsonConfigType` + `DynamicFieldsType` (technique) | champ JSON configurable |
| `formulaires/exemples-plateformes.md` | Définitions de champs par plateforme d'admission | configurer une plateforme |
| `formulaires/guide-mode-assistant.md` | Guide utilisateur final du mode assistant | doc/aide utilisateur |
| `architecture/migration-v2.md` | Commande `app:migrate:v2`, étapes, décisions de reprise | schéma BDD, bascule main → V2 |
| `architecture/maquette-modulaire.md` | Architecture cible maquette par profils de diplôme (non implémentée) | refonte structure/validation/rendu |
| `testing/README.md` | Organisation des tests, helpers, commandes, priorités | écrire/lancer des tests |
| `ops/install.md` | Installation dev (Docker) et prod | installation, déploiement |
| `ops/ci-cd.md` | CI GitHub Actions, baseline PHPStan, modèle de branches, déploiement cible (heures creuses, quasi sans coupure), tâches restantes | modifier la CI, préparer une release ou un hotfix |
| `ops/command.md` | Commandes console `app:*` | exécuter/modifier une commande |
| `ops/recopie-fiche-matiere.md` | Procédure `app:parcours-copy-data` | recopie des données vers fiches matières |
| `produit/roadmap-pilotage-admin.md` | Idées fonctionnelles pilotage/admin | cadrage produit uniquement |
| `archives/` | **Obsolète** (dont SQL historiques repris par `app:migrate:v2`) | jamais pour générer du code |

## Convention de rédaction (pour garder la doc économe en contexte)

- Une ligne « Quand lire » en tête, puis règles en listes/tableaux ; pas d'emojis ni de paragraphes narratifs.
- Pointer vers le code source de vérité (chemin) plutôt que recopier du code ; exemples courts uniquement.
- Un sujet = un fichier ; mettre à jour cet index et la table de routage d'`AGENTS.md` à chaque ajout.
- Chaque fichier porte en tête « À mettre à jour si : … ». Toute modification du code listé impose de mettre la doc à
  jour **dans le même commit/PR** ; une doc fausse est pire qu'une doc absente.
