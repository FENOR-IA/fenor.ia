# Fenor

> PHP + AI na sua VPS em 1 comando.

Fenor instala em qualquer VPS Ubuntu a infraestrutura completa para criar, testar e publicar apps PHP usando agentes de IA. Modelo BYOK — você usa sua própria chave Claude.

## Instalação

```bash
curl -fsSL https://fenor.ia.br/install.sh | bash
```

Instala: Nginx, PHP 8.2, PostgreSQL, Node.js, Claude Code, ttyd, Cloudflare Tunnel.

## O que você ganha

- **Studio** — painel web para criar e gerenciar apps
- **Terminal web** — Claude Code direto no browser, isolado por app
- **3 ambientes** — `dev`, `hml`, `prd` com subdomínios automáticos
- **Banco isolado** — schema PostgreSQL por app/ambiente
- **SSL automático** — via Cloudflare Tunnel

## Requisitos

- Ubuntu 24.04 LTS
- Acesso root
- Domínio com Cloudflare (opcional — funciona local sem)

## Licença

MIT
