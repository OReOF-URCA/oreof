# Assistance : fils de messages en arbre (façon Thunderbird) — conception courte

## Intention

Il a été demandé de s'inspirer de l'arborescence des fils de Thunderbird. Aujourd'hui, la liste des demandes est un
tableau à plat et la page d'une demande empile les messages par date, sans montrer qui répond à qui. On veut voir
d'un coup d'œil la structure d'un échange (ex. la demande de Clara Bretaudeau où l'équipe met Sébastien Laurence en
copie, qui poursuit le fil), sans perdre le suivi propre à ORéOF (statut, responsable, contexte).

## Approches considérées

- **A. Trois panneaux (liste | fil + mail lu | suivi) sur une seule page** — le plus proche de Thunderbird, mais
  refonte complète de la navigation. Non retenue.
- **B. Liste dépliable + page de la demande en arbre** — **retenue** par l'utilisateur.
- **C. Liste inchangée, page de la demande avec arbre compact + mail sélectionné** — non retenue.

Dépliage de la liste :
- **1. Tout rendu avec la page, `<details>` natif** — **retenu** : pas de JS, ouverture instantanée, même mécanisme
  que « Afficher l'historique » ; 30 demandes par page, messages chargés en une requête.
- 2. Chargement au clic par `turbo-frame` — non retenu (route + template en plus, délai à chaque dépliage, utile
  seulement pour des volumes bien supérieurs).

## Conception

- **Arbre** : construit depuis `MessageAssistance::inReplyTo`, au sein d'une demande. Un message dont le parent est
  absent de la demande (ou sans `In-Reply-To`, notes internes comprises) est une racine. Racines et enfants triés par
  date d'envoi. Calcul côté PHP (service), pas en Twig.
- **Liste** (`app_assistance_index`) : tableau et filtres inchangés. La cellule Sujet devient un `<details>` :
  `summary` = ▸ sujet + « N messages » ; contenu = une ligne par message, indentée selon la profondeur :
  expéditeur (nom ou email), extrait du texte sans l'historique cité (≈ 100 caractères), date/heure, badge pour une
  note interne ou un message automatique. Clic sur une ligne → page de la demande, ancre `#message-<id>`.
  Les messages des demandes affichées sont chargés en une seule requête (pas de N+1).
- **Page de la demande** (`app_assistance_show`) : les cartes `MessageAssistanceCard` sont rendues dans l'ordre de
  l'arbre (parcours en profondeur), décalées selon la profondeur avec un trait de liaison à gauche.
- **Profondeur affichée plafonnée à 3** (choix de Claude) : au-delà, le décalage n'augmente plus, pour qu'un long
  échange ne déborde pas.

## Hors périmètre

Pas de panneau de lecture sur la page de liste, pas de JS nouveau, pas de changement du regroupement des mails en
demandes ni du modèle de données.

## Laissé au constructeur

Composant ou macro pour la ligne de message de la liste, style exact du trait de liaison, libellé « 1 message » /
« N messages ».
