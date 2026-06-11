# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

See also the workspace-level `../CLAUDE.md` for the multi-repo layout, PHP 7.4 target, and git/sync policies.

## No build system, intentionally

Plain procedural PHP — no Composer, no framework, no test suite. Per `CONTRIBUTING.md`: "mantenha a simplicidade — evite adicionar dependências desnecessárias." Don't introduce Composer, a framework, or a test runner without asking first.

## Config / DB access pattern (studio/)

All Studio pages and `studio/api/*` use the global helpers in `studio/config/db.php` and `studio/config/config.php`:

- `fenorEnv()` — reads `key=value` pairs from `/etc/fenor/.env` (production) or `studio/.env` (local), cached statically.
- `fenorDB(): PDO` — cached PDO connection (driver from `DB_DRIVER`, default `pgsql`), `ERRMODE_EXCEPTION` + `FETCH_ASSOC`.
- `fenorSetting($key, $default)` / `fenorSettings()` — read from the `fenor_settings` table, falling back to `.env` if the table doesn't exist yet.
- `saveSetting($key, $value)` — upserts into `fenor_settings` (driver-aware: `ON CONFLICT` for pgsql, `ON DUPLICATE KEY` for mysql).

`studio/.env` is gitignored — copy from `studio/.env.example` for local setup. `studio/adminer.php` is also gitignored (DB admin tool, never commit it).

## boilerplate/ is legacy

`boilerplate/` (pt/en multi-language templates) was replaced by the template system (commit `eef1395`). New apps are provisioned from `/etc/fenor/templates/{template}/`, cloned from the `fenor-ia-templates` repo — see `studio/templates.php` and `bin/newapp`. Don't add new templates under `boilerplate/`.

## bin/ CLI scripts

| Script | Purpose |
|--------|---------|
| `fenor` | Central CLI dispatcher (`fenor <command> [args]`) |
| `newapp` | Creates a new app in DEV from a template |
| `fenor-promote` | Promotes an app DEV → HML → PRD |
| `fenor-git` | Git operations for apps |
| `fenor-terminal` | ttyd entrypoint for each app's terminal |
| `fenor-session` | Opens Claude Code in the right mode (planner/executor/reviewer) |
| `fenor-save-session` / `save-memory` | Persist session context (called from Claude's Stop hook) |
| `fenor-agent` | Headless autonomous agent runner |
| `fenor-learn` | Extracts learnings from a session into memory |

## studio/ structure

- Top-level pages: `dashboard.php`, `apps.php`, `workspace.php`, `templates.php`, `settings.php`, `terminal.php`, `banco.php`, `login.php` / `logout.php`
- `api/` — JSON endpoints (`newapp.php`, `provision.php`, `promote.php`, `session.php`, `memory.php`, `git.php`, `github.php`, `ssh-key.php`, `update-app.php`)
- `partials/` — shared `sidebar.php` / `topbar.php`
- `config/` — `db.php`, `config.php`, `helpers.php`, `setup.sql`
