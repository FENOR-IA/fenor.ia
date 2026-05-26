# Fenor

> PHP + AI on your VPS in one command.

Fenor installs the complete infrastructure on any Ubuntu VPS to build, test and publish PHP apps using AI agents. BYOK model — you bring your own Claude key.

## Install

```bash
curl -fsSL https://fenor.ia.br/install.sh | bash
```

Installs: Nginx, PHP 8.2, PostgreSQL, Node.js, Claude Code, ttyd, Cloudflare Tunnel.

## What you get

- **Studio** — web dashboard to create and manage apps
- **Web terminal** — Claude Code right in the browser, isolated per app
- **3 environments** — `dev`, `hml`, `prd` with automatic subdomains
- **Isolated database** — PostgreSQL schema per app/environment
- **Automatic SSL** — via Cloudflare Tunnel

## Requirements

- Ubuntu 24.04 LTS
- Root access
- Domain with Cloudflare (optional — works locally without it)

## License

MIT

---

## Português

> PHP + IA na sua VPS em 1 comando.

Fenor instala em qualquer VPS Ubuntu a infraestrutura completa para criar, testar e publicar apps PHP usando agentes de IA. Modelo BYOK — você usa sua própria chave Claude.

### Instalação

```bash
curl -fsSL https://fenor.ia.br/install.sh | bash
```

### O que você ganha

- **Studio** — painel web para criar e gerenciar apps
- **Terminal web** — Claude Code direto no browser, isolado por app
- **3 ambientes** — `dev`, `hml`, `prd` com subdomínios automáticos
- **Banco isolado** — schema PostgreSQL por app/ambiente
- **SSL automático** — via Cloudflare Tunnel
