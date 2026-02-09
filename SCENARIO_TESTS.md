# Scénario de validation et données de test – PIDEV

## Objectif

Présenter l’application avec un enchaînement logique et des données de test cohérentes, et être capable de répondre aux questions sur le code.

---

## 1. Données de test suggérées

### Utilisateurs

| Rôle   | Email              | Username  | Mot de passe (ex.) | Usage                    |
|--------|--------------------|-----------|--------------------|---------------------------|
| Admin  | admin@wellbalance.com | admin     | Admin123!          | Back office, messagerie   |
| User   | user@test.com      | user1     | User123!           | Front : RDV, plans, sport |

Créer au moins un admin et un utilisateur « patient » pour tester front et back.

### Types de rendez-vous (Back Office)

- Consultation générale  
- Suivi nutrition  
- Suivi sportif  
- Bilan  

### Rendez-vous (exemples)

- Titre : « Bilan trimestriel », Date/heure : à venir, Lieu : Cabinet 1, Type : Bilan, Statut : Planifié.  
- Un autre : « Suivi nutrition », Type : Suivi nutrition, Statut : Terminé.

### Objectifs sportifs (avec relation User)

- Libellé : « Courir 5 km », Type : Endurance, Dates début/fin cohérentes, Statut : En cours.  
- Un autre : « 3 séances musculation / semaine », Statut : Atteint.

### Plans nutritionnels (relation User)

- Objectif : « Perte de poids », Description et période renseignées, associé à l’utilisateur de test.  
- Repas : au moins un repas par type (petit-déj, déjeuner, dîner) avec calories et date.

### Messagerie

- Une conversation entre l’admin (médecin) et l’utilisateur de test.  
- Quelques messages échangés pour démontrer recherche/tri et lecture.

---

## 2. Enchaînement type pour la démo (scénario)

1. **Connexion**  
   - Se connecter en **admin** → redirection vers le tableau de bord (back office).  
   - Montrer que les liens du menu (Dashboard, Front Office, Gestion Rendez-vous, Sport, Nutrition, Messagerie) fonctionnent.

2. **Back Office – Rendez-vous**  
   - Types de RDV : liste, création d’un type, édition.  
   - Rendez-vous : liste, recherche (par titre/lieu), tri (date, statut), création d’un RDV avec type, consultation d’un RDV.

3. **Back Office – Sport**  
   - Objectifs sportifs : liste, recherche/tri, création avec utilisateur lié.  
   - Activités physiques : liste, CRUD, relation si présente.

4. **Back Office – Nutrition**  
   - Plans nutritionnels : liste, recherche/filtres, création (avec utilisateur).  
   - Repas : liste par plan, création/édition avec plan lié.

5. **Back Office – Messagerie**  
   - Liste des conversations, recherche, ouverture d’une conversation, envoi de message.  
   - Montrer tri (date, etc.) si présent.

6. **Back Office – Utilisateurs**  
   - Si la page liste des utilisateurs est en place : recherche/tri, édition d’un utilisateur.

7. **Front Office**  
   - Lien « Front Office » ou connexion en **user** → accueil front.  
   - Mes rendez-vous : liste, prise de RDV, annulation ou modification si prévu.  
   - Mes plans nutrition : liste des plans de l’utilisateur, détail, repas.  
   - Mes objectifs sportifs : liste, détail.  
   - Messagerie : conversations, envoi de message.  
   - Profil : consultation / édition si disponible.

8. **Validation des saisies**  
   - Tenter une création avec champs invalides (vide, date incohérente, etc.) et montrer que les **erreurs viennent du serveur** (messages Symfony/Validator), pas uniquement du HTML/JS.

9. **Fonctionnalités avancées**  
   - Recherche et tri sur au moins 2 écrans (ex. RDV, utilisateurs, messagerie).  
   - Si une API est ajoutée (ex. JSON pour les RDV) : un appel rapide (navigateur ou Postman) pour montrer l’intégration.

---

## 3. Points à savoir expliquer (questions possibles)

- **Relations entre entités** : pourquoi ManyToOne/OneToMany, cascade, `mappedBy` / `inversedBy`.  
- **Validation** : où sont les contraintes (entité vs formulaire), pas de confiance au seul HTML/JS.  
- **Sécurité** : `IsGranted('ROLE_ADMIN')`, firewall, authentification.  
- **Recherche / tri** : paramètres GET, requêtes dans le Repository (DQL/QueryBuilder).  
- **Templates** : héritage (base backend / base frontend), `path()`, blocs.  
- **Git** : qui a fait quoi (commits), stratégie de branche si utilisée.

---

*À adapter selon les modules réellement livrés (noms de routes, entités). Vérifier les noms exacts avec `bin/console debug:router`.*
