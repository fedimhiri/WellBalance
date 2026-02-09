# Suivi noté PIDEV – Semaine du 09/02/2026

## Rappel des exigences

| # | Exigence | État actuel | Action à faire |
|---|----------|-------------|----------------|
| 1 | **Templates Front Office et Back Office** pour toutes les pages, avec **liens fonctionnels** entre les pages | ✅ Templates de base : `backend/baseend.html.twig`, `frontend/base_frontend.html.twig`. Sidebar/header avec `path()`. Quelques liens en `#` (Utilisateurs, Paramètres, Aide). | Remplacer les `href="#"` par de vrais liens (ex. liste utilisateurs, paramètres) ou retirer les entrées menu inutiles. |
| 2 | **Entités + CRUD** avec **au moins une relation** par entité | ✅ Entités : User, PlanNutrition, Repas, ObjectifSportif, ActivitePhysique, RendezVous, TypeRendezVous, Conversation, Message. Relations : PlanNutrition→User, Repas→PlanNutrition, RendezVous→TypeRendezVous, Message→Conversation→User, ObjectifSportif→User, etc. CRUD back/front selon modules. | Vérifier que chaque entité a bien au moins une relation et un CRUD complet (index, show, new, edit, delete si applicable). |
| 3 | **Contrôles de saisie côté serveur** – pas de contrôle uniquement HTML/JS | ✅ En place : User, PlanNutrition, ObjectifSportif, ActivitePhysique, MessageType, **Repas, RendezVous, TypeRendezVous, Message, Conversation** (contraintes `Assert` ajoutées côté serveur). | Vérifier en démo que les erreurs s’affichent bien (formulaires Symfony) sans dépendre du HTML/JS. |
| 4 | **Fonctionnalités avancées** (recherche, tri, intégration API…) selon le module | ✅ Recherche + tri : Dashboard (users), Rendez-vous (back + front), Messagerie (back + front), Objectifs sportifs, Activités physiques, Plans nutrition. Pas d’API REST externe visible ; TypingManager (session) pour messagerie. | Optionnel : ajouter une API REST (ex. liste RDV en JSON) ou documenter l’existant (Mercure, etc.) comme « intégration API ». |
| 5 | **Intégration sur une seule machine avec GitHub** | À vérifier par l’équipe. | Dépôt à jour sur GitHub **avant** la séance de validation. Aucune réclamation possible si dépôt absent. |

---

## Détail par point

### 1. Templates et liens

- **Back Office** : `templates/backend/baseend.html.twig` + `partials/_sidebar.html.twig`, `_navbar.html.twig`, `_footer.html.twig`.
- **Front Office** : `templates/frontend/base_frontend.html.twig` + `partials/_header.html.twig`, `_footer.html.twig`.
- **Liens** : « Utilisateurs » pointe vers `admin_dashboard` (liste des utilisateurs avec recherche/tri). « Paramètres » / « Aide & Support » restent en `#` ; créer des routes si besoin ou laisser tel quel.

### 2. Entités et relations

- **User** ↔ PlanNutrition (OneToMany).
- **PlanNutrition** ↔ Repas (OneToMany).
- **RendezVous** → TypeRendezVous (ManyToOne).
- **Conversation** → User (doctor, user), **Message** → Conversation, User (sender).
- **ObjectifSportif** → User (ManyToOne).
- **ActivitePhysique** : vérifier relation (ex. avec User ou ObjectifSportif) si exigence « au moins une relation » par entité.

### 3. Validation côté serveur

- **Déjà en place** : User, PlanNutrition, ObjectifSportif, ActivitePhysique, champs dans RegistrationFormType et MessageType.
- **À ajouter** :
  - **Repas** : typeRepas (NotBlank, Length), calories (Range), description (NotBlank), dateRepas (NotNull), planNutrition (NotNull).
  - **RendezVous** : titre (NotBlank, Length), dateRdv (NotNull), statut (Choice), type (NotNull).
  - **TypeRendezVous** : libelle (NotBlank, Length).
  - **Message** (entité) : content (Length min ou NotBlank selon règles métier), conversation/sender (NotNull).
  - **Conversation** : doctor, user (NotNull). Optionnel : sujet, type (Length, Choice).

### 4. Scénario et données de test

- Voir **SCENARIO_TESTS.md** pour un enchaînement logique et des données de test.

### 5. GitHub

- Cloner / travailler sur **un seul dépôt** partagé.
- Commit + push **avant** la séance.
- Être prêt à expliquer le code et les choix techniques.

---

*Document généré pour la préparation du suivi noté PIDEV – projet intégré.*
