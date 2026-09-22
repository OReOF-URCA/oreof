# Roadmap & Idées de Fonctionnalités : Pilotage Global et Administration ORéOF

Ce document récapitule les pistes d'évolution fonctionnelle et ergonomique destinées aux administrateurs, à la scolarité centrale (SES), aux responsables DPE et aux élus/décideurs universitaires (VP CFVU, Présidence).

---

## 1. 🚦 Tour de Contrôle & Suivi de Campagne (Goulets d'étranglement) — *En cours d'implémentation*

### A. Analyse des temps de séjour & Détection des dossiers stagnants (SLA / Deadlines)
* **Objectif** : Identifier précisément les étapes où les dossiers s'accumulent et mesurer les délais moyens.
* **Fonctionnalités** :
  * Calcul du temps moyen passé à chaque étape du workflow (*ex: "En cours de rédaction RF : 19 jours en moyenne", "En attente PV Conseil : 8 jours"*).
  * Indicateur d'alerte sur les parcours/fiches "stagnants" (ex: plus de 15 jours sans mouvement ou sans mise à jour).
  * Vue chronologique par rapport aux dates limites de la campagne (calendrier des conseils d'UFR et séances CFVU).

### B. Matrice d'avancement par Composante (Heatmap & Avancement global)
* **Objectif** : Avoir une vision synthétique multi-composantes de l'avancement de la collecte.
* **Fonctionnalités** :
  * Tableau croisé : `Composantes (lignes) × Étapes du workflow (colonnes)` avec nombre de parcours et pourcentage d'avancement.
  * Barres de progression globales par composante et au niveau établissement.
  * Export 1-clic (PDF / Excel / CSV) du bilan de synthèse pour les commissions centrales (CFVU, Conseil Académique).

---

## 2. 🛡️ Moteur de Contrôle Qualité & Cohérence des Maquettes (Linter Métier)

* **Objectif** : Détecter et bloquer les erreurs de structure avant les votes en CFVU.
* **Fonctionnalités** :
  * **Vérification automatique de conformité** :
    * Somme des ECTS ≠ 30 par semestre / ≠ 60 par an.
    * Volumes horaires nuls ou incohérences CM / TD / TP.
    * Modalités de Contrôle des Connaissances et des Compétences (MCCC) incomplètes ou somme des coefficients ≠ 100%.
    * Éléments constitutifs (EC) sans responsable désigné ou fiches orphelines.
    * Codes LHEO / identifiants ministériels manquants ou mal formés pour Parcoursup / MonMaster.
  * Dashboard "Zéro Anomalie" avec actions directes de correction.

---

## 3. 🔍 Simulateur de Transition & Diagnostic de Droits ("Pourquoi ce bouton est bloqué ?")

* **Objectif** : Réduire le support utilisateur en expliquant instantanément pourquoi une action est grisée ou refusée.
* **Fonctionnalités** :
  * Sélectionner un utilisateur et un parcours / fiche.
  * Évaluation en direct de la chaîne de décision (`WorkflowOperationAuthorizer`, Voters, Guards, validateurs de contraintes).
  * Affichage pédagogique des prérequis manquants (rôle manquant, fiches obligatoires non complétées, composante non autorisée).

---

## 4. ⚡ Actions de Masse & Déblocage d'Urgence Sécurisé

* **Objectif** : Permettre aux administrateurs de réagir rapidement en cas d'ajustements tardifs de maquettes.
* **Fonctionnalités** :
  * Réouvertures ou validations groupées par filtre (composante, mention) avec obligation de saisir un motif pour l'audit.
  * Bypass administrateur d'urgence avec horodatage et journalisation indélébile dans l'historique de formation.

---

## 5. 🔀 Comparateur Visuel de Maquettes (Diff Inter-versions N vs N-1)

* **Objectif** : Faciliter la relecture des modifications pour les membres du Conseil et de la CFVU.
* **Fonctionnalités** :
  * Comparateur visuel côte à côte entre l'année N (en cours de validation) et l'année N-1 (reconduite).
  * Surlignage intelligent : nouveaux ECs (vert), suppressions (rouge), modifications d'heures ou de coefficients (orange).

---

## 6. 📢 Centre de Relances & Communications Ciblées

* **Objectif** : Automatiser le suivi des retards de saisie auprès des enseignants et responsables.
* **Fonctionnalités** :
  * Détection automatique des responsables de formation en retard à J-15 / J-7 d'une échéance.
  * Envoi d'emails de relance ciblés avec liens directs d'accès aux formulaires à compléter.
