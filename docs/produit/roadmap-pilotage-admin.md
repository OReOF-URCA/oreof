# Roadmap — pilotage global et administration

Quand lire : cadrage produit uniquement (pistes, pas de spécification). Public visé : administrateurs, scolarité
centrale (SES), responsables DPE, élus/décideurs (VP CFVU, Présidence).
À mettre à jour si : un chantier démarre, avance ou est abandonné (colonne Statut).

| # | Chantier | Objectif | Fonctionnalités | Statut |
|---|---|---|---|---|
| 1a | Temps de séjour / dossiers stagnants | voir où les dossiers s'accumulent | durée moyenne par étape du workflow ; alerte si > 15 j sans mouvement ; vue vs échéances (conseils d'UFR, CFVU) | en cours |
| 1b | Matrice d'avancement par composante | vision multi-composantes de la collecte | tableau composantes × étapes (nb, %) ; progression composante/établissement ; export PDF/Excel/CSV pour CFVU/CAc | en cours |
| 2 | Linter métier des maquettes | bloquer les erreurs avant vote CFVU | ECTS ≠ 30/semestre ou ≠ 60/an ; heures nulles ou CM/TD/TP incohérents ; MCCC incomplètes ou coefficients ≠ 100 % ; EC sans responsable, fiches orphelines ; codes LHEO/ministériels manquants (Parcoursup, MonMaster) ; tableau « zéro anomalie » avec correction directe | idée |
| 3 | Diagnostic « pourquoi ce bouton est bloqué ? » | réduire le support | choisir utilisateur + parcours/fiche ; évaluer `WorkflowOperationAuthorizer`, voters, guards, validateurs ; afficher les prérequis manquants | idée |
| 4 | Actions de masse / déblocage d'urgence | réagir aux ajustements tardifs | réouvertures/validations groupées par filtre avec motif obligatoire ; bypass admin horodaté et journalisé dans l'historique | idée |
| 5 | Comparateur N vs N-1 | faciliter la relecture en conseil/CFVU | vue côte à côte ; ajouts (vert), suppressions (rouge), modifications heures/coefficients (orange) | idée |
| 6 | Centre de relances | automatiser le suivi des retards | détection des responsables en retard à J-15/J-7 ; emails ciblés avec liens directs | idée |
