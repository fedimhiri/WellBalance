# PI_DEV Project - WellBalance

## 📌 Description
Ce projet est réalisé dans le cadre de la séance PIDEV.  
Il s'agit d'une application Symfony permettant la gestion de documents médicaux et de rendez-vous, avec une interface Front Office et Back Office.

---

## 🗂️ Entités principales
- **Document**
  - Titre du document
  - Type de document
  - Catégorie (relation avec `CategorieDocument`)
  - Fichier (upload)
  - Date d’upload
  - Relation avec `User`

- **CategorieDocument**
  - Description (ex. Ordonnance, Analyse, Facture, Certificat médical)

- **RendezVous**
  - Notes
  - Statut (Confirmé, En attente, Annulé)
  - Relation avec `TypeRendezVous`

- **TypeRendezVous**
  - Libellé (Consultation, Suivi, Urgence, Téléconsultation)

---

## ⚙️ Fonctionnalités
- CRUD complet pour **Document** et **RendezVous**
- Relations entre entités (Document ↔ Catégorie, RendezVous ↔ TypeRendezVous)
- Contrôles de saisie côté serveur (validation Symfony)
- Recherche et tri des documents et rendez-vous
- Export PDF des documents
- Interface Front Office et Back Office avec un template commun
- Gestion des données via SQL (catégories, types de rendez-vous)

---

## 🧪 Scénario de test
1. **Connexion utilisateur** (ROLE_USER).
2. **Ajout d’un document** :
   - Titre : *Résultats d’analyse sanguine – Février 2026*
   - Type : *Analyse*
   - Catégorie : *Analyse*
   - Fichier : *analyse.pdf*
3. **Ajout d’un rendez-vous** :
   - Notes : *Consultation générale avec Dr. Dupont*
   - Statut : *Confirmé*
   - Type : *Consultation*
4. **Recherche** :
   - Rechercher “Analyse” → affiche le document.
   - Rechercher “Urgence” → affiche les rendez-vous urgents.
5. **Tri** :
   - Trier les documents par date d’upload.
   - Trier les rendez-vous par statut.
6. **Édition** :
   - Modifier le titre ou le fichier d’un document.
   - Modifier le statut d’un rendez-vous.
7. **Suppression** :
   - Supprimer un document ou un rendez-vous.
8. **Export PDF** :
   - Générer un PDF listant les documents.

---

## 🚀 Déploiement
- Cloner le projet :
  ```bash
  git clone https://github.com/adembej420/wellBalance.git