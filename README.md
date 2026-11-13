# SIRH — Université Dakar Bourguiba (UDB)

Système d'information des ressources humaines de l'UDB : gestion des enseignants, des UFR, des présences, des congés et de la paie.

## Fonctionnalités

- **UFR et structure** : facultés, années académiques, classes et matières.
- **Enseignants** : profils, types d'enseignement et emplois du temps.
- **Pointage** : présences avec code de pointage et fenêtre de validation de 15 minutes.
- **Activités pédagogiques** : notes, devoirs et documents.
- **Congés** : types de congés, soldes et demandes.
- **Paie** : bulletins de paie et détails.
- **Notifications** et journal d'audit.
- **Droits par rôle** : chaque utilisateur accède uniquement à ce qui le concerne.

## Technologies

- **Backend** : Laravel 12, Laravel Sanctum (authentification), Spatie Permission (rôles et droits).
- **Frontend** : React et Vite.
- **Base de données** : MySQL (SQLite en développement).
- **Déploiement** : Docker Compose, avec Nginx.

## Structure

```
backend/    API Laravel
frontend/   Interface React (Vite)
nginx/      Configuration du serveur web
docs/       Documentation technique
```

## Installation

### Avec Docker

```bash
docker compose up --build
```

### Sans Docker

Backend :

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Frontend :

```bash
cd frontend
npm install
```

Créez un fichier `.env` :

```bash
VITE_API_URL=http://localhost:8000/api
```

Puis lancez :

```bash
npm run dev
```

## Documentation

Les guides de mise en place et de démonstration se trouvent à la racine du dépôt (`COMPLETE_SETUP_GUIDE.md`, `GUIDE_DEMO_SOUTENANCE.md`).
