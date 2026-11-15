# EDGVM Plateforme

Plateforme de gestion de l'École Doctorale Génie du Vivant et Modélisation (Université de Mahajanga) : doctorants, inscriptions et dérogations, paiements, actes administratifs, notifications et suivi des thèses.

| Dossier | Contenu |
|---|---|
| `backend/` | API Laravel 13 (MySQL, Sanctum, Pest) |
| `frontend/` | Interface Nuxt 4 en SPA (Tailwind CSS v4, shadcn-vue) |

## Démarrer

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve

cd ../frontend
npm install
npm run dev        # http://localhost:3000
```

Construction pour la production : `scripts/construire.sh` (l'interface est servie par Laravel, sur le même domaine que l'API).

Les consignes pour les agents de code (Codex, Claude Code) sont dans `AGENTS.md`, `CLAUDE.md` et `.agents/skills/`.
