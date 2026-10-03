# Recherche plein texte approchée (fuzzy)

Quand lire : modification de la page `/recherche/parcours` (`app_search`, `app_search_action`), du modal des fiches
matières associées (`app_fiche_matiere_search`) ou réutilisation du moteur fuzzy pour une autre recherche.
À mettre à jour si : `src/Service/Recherche/`, `src/DTO/Recherche/`, `src/Twig/Components/SearchExcerpt.php`,
`templates/search/search_result_parcours.html.twig`, `ParcoursRepository::findPourRechercheTexte()`,
`FicheMatiereRepository::findPourRechercheParcours()`.

## Périmètre

| Type de recherche | Moteur |
|---|---|
| Parcours (+ compteur et modal des fiches matières associées) | fuzzy en PHP (`RechercheParcours`) |
| Fiche matière (liste paginée, export Excel) | `LIKE` SQL inchangé (`public/search/search_fiche_matiere.js`) — ~43 000 fiches, trop pour un index en mémoire |

## Architecture (`src/Service/Recherche/`)

| Classe | Rôle |
|---|---|
| `FuzzyMatcher` | `texteBrut()` (HTML Trix → texte), `normaliser()` (minuscules, sans accents), `decouper()`, `termesRequete()` (mots vides retirés), `rapprocher()` (terme ↔ vocabulaire) |
| `FuzzySearch` | `rechercher(requete, documents, poids, champsSansExtrait, avecExtraits)` sur des `DocumentRecherche` → `ResultatsFuzzy` (triés, extraits, corrections) |
| `RechercheParcours` | charge les textes des parcours de la campagne, pondère les champs, construit les `ResultatRechercheParcours` ; `fichesMatieresAssociees()` pour le modal |

Pas d'index persistant : tout est calculé à la requête (~490 parcours, 80–250 ms hors rendu). Réutiliser `FuzzySearch`
pour un autre corpus de taille comparable ; au-delà de quelques milliers de documents, filtrer d'abord en SQL.

## Règles de correspondance

- Mots vides (`le`, `de`, `des`…) ignorés, 10 termes au plus ; une saisie faite uniquement de mots vides est refusée
  (toast, comme la règle des 3 caractères).
- Un document est retenu s'il contient **tous** les termes (ET), chacun exact, préfixe ou approché.
- Similarité : exact 1.0 · préfixe (terme ≥ 3 lettres) 0.8 · fautes 0.95 − 0.2 × distance · début de mot fautif 0.7 − 0.15 × distance.
- Fautes tolérées : 0 (< 4 lettres), 1 (4–7), 2 (≥ 8) ; l'inversion de deux lettres compte pour une faute.
- Score = Σ (meilleure similarité × poids du champ) + bonus si les termes sont voisins (fenêtre de n + 4 mots) + léger bonus de fréquence.
- Poids parcours : `intitule` 3 · `objectifsParcours`/`objectifsFormation` 1.5 · `contenuFormation` 1.2 · `resultatsAttendus`/`poursuitesEtudes` 1.
- Parcours par défaut : textes de la formation (+ poursuites d'études du parcours), lien vers la formation.
- Le HTML n'est jamais indexé (balises et entités retirées) ; un parcours n'apparaît qu'une fois même avec plusieurs DPE.

## Affichage

- Résultats triés par pertinence ; badges = champs contenant la recherche ; extrait du champ le plus pertinent
  (hors intitulé) via `<twig:SearchExcerpt :segments="…" label="…"/>` (segments échappés, pas de `|raw`).
- Bandeau `alerte` info : mot saisi remplacé par un mot proche, ou mot sans aucune correspondance.
