# Fenor

**Plataforma open source para criar e publicar apps PHP com IA na sua própria VPS.**

1 comando instala Nginx, PHP 8.2, PostgreSQL, SSL e Claude Code na sua VPS Ubuntu 24.04. Crie e gerencie apps pelo Studio web — sem mensalidade, sem lock-in, com total controle sobre o código e os dados.

[![MIT License](https://img.shields.io/badge/license-MIT-D9633A)](LICENSE)
[![Ubuntu 24.04](https://img.shields.io/badge/ubuntu-24.04_LTS-8C7C6B)](https://ubuntu.com)
[![PHP 8.2](https://img.shields.io/badge/php-8.2-8C7C6B)](https://www.php.net)

---

## Instalação

```bash
curl -fsSL https://fenor.ia.br/install.sh | bash
```

> Requer Ubuntu 24.04 LTS com acesso root. Leva cerca de 15 minutos.

---

## O que é instalado

| Componente | Função |
|---|---|
| **Nginx** | Servidor web com virtual hosts automáticos |
| **PHP 8.2** | Runtime com extensões pgsql, curl, mbstring |
| **PostgreSQL** | Banco de dados com schema isolado por app |
| **ttyd** | Terminal web acessível pelo browser |
| **Claude Code** | IA para desenvolvimento diretamente no terminal |
| **Cloudflare Tunnel** | SSL e DNS automáticos sem expor portas |
| **Studio** | Painel web para criar e gerenciar apps |

---

## Como funciona

### 1. Instale na VPS
O script configura toda a infraestrutura automaticamente e exibe o progresso em tempo real.

### 2. Abra o Studio
Acesse o Studio pelo browser usando o IP da VPS, um domínio próprio ou um subdomínio. Configure as integrações com GitHub e Cloudflare e cadastre seus apps — sem linha de comando.

### 3. Crie e desenvolva com IA
O Studio provisiona diretórios, banco PostgreSQL isolado, subdomínio com SSL e boilerplate PHP. O terminal web abre com Claude Code direto no projeto. Descreva o que quer construir e o Claude escreve o código.

---

## Pré-requisitos

### Obrigatório
- **VPS com Ubuntu 24.04 LTS** — mínimo 1 GB RAM (DigitalOcean, Vultr, Hostinger, Contabo etc.)
- **Chave de API da Anthropic** — [anthropic.com](https://anthropic.com) · pay-as-you-use

### Recomendado
- **Conta na Cloudflare** — [cloudflare.com](https://cloudflare.com) · gratuita · para SSL e DNS automáticos
- **Conta no GitHub** — [github.com](https://github.com) · gratuita · para versionamento dos apps

> O Fenor funciona sem Cloudflare e GitHub, mas com recursos reduzidos (sem subdomínios automáticos e sem sincronização de código).

---

## Ambientes

Cada app criado no Studio é provisionado nos três ambientes:

| Ambiente | Finalidade |
|---|---|
| `dev` | Desenvolvimento ativo |
| `hml` | Homologação e testes com o cliente |
| `prd` | Produção — versão publicada |

A promoção entre ambientes é feita pelo Studio com 1 clique.

---

## Reinstalar

Para remover a instalação e recomeçar do zero sem formatar a VPS:

```bash
curl -fsSL https://fenor.ia.br/uninstall.sh | bash
```

Remove apenas os arquivos do Fenor. Nginx, PHP, PostgreSQL e outros projetos existentes na VPS ficam intactos.

---

## Estrutura do repositório

```
fenor.ia/
├── install.sh          # Script de instalação
├── uninstall.sh        # Script de desinstalação
├── bin/                # Scripts CLI (newapp, fenor-git, fenor-promote...)
├── boilerplate/
│   ├── pt/             # Template PHP em português
│   └── en/             # Template PHP em inglês
└── studio/             # Código do painel web (Studio)
```

---

## Contribuindo

Contribuições são bem-vindas — de templates a melhorias de código. Veja o [guia de contribuição](CONTRIBUTING.md) para começar.

---

## Licença

[MIT](LICENSE) — use, modifique e distribua livremente.

---

*English summary: Fenor is an open source platform that installs a full PHP + AI infrastructure stack on any Ubuntu 24.04 VPS with a single command. It includes Nginx, PHP 8.2, PostgreSQL, Claude Code web terminal, Cloudflare Tunnel for SSL/DNS, and a web Studio dashboard to create and manage apps without any command line. MIT licensed. See [fenor.ia.br](https://fenor.ia.br) for full documentation.*
