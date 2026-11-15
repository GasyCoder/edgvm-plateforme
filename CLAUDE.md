@AGENTS.md

## Spécifique à Claude Code

- Les skills du projet sont dans `.claude/skills/` (lien vers `.agents/skills/`, partagés avec Codex). Les charger avant de travailler sur le sujet qu'ils couvrent.
- Répondre à l'utilisateur en français, de façon concise.
- Le site vitrine est un autre dépôt (`edgvm`, Laravel + Inertia). Il lit l'API publique de cette plateforme : ne pas en changer le format sans mettre à jour ce contrat (skill `edgvm-api-publique`).
