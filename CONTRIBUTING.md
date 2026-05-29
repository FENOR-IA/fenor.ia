# Contribuindo com o Fenor

Obrigado pelo interesse em contribuir! O Fenor é um projeto open source e qualquer contribuição — de uma correção de typo a um novo template — é bem-vinda.

---

## Formas de contribuir

### 🐛 Reportar um problema
Se você encontrou um bug ou comportamento inesperado, [abra uma issue](https://github.com/FENOR-IA/fenor.ia/issues/new) descrevendo:
- O que você tentou fazer
- O que aconteceu
- O que você esperava que acontecesse
- Versão do Ubuntu e saída do terminal (se relevante)

### 💡 Sugerir uma melhoria
Tem uma ideia para um novo recurso ou melhoria? Abra uma issue com a tag `enhancement` e descreva o caso de uso. Discutir antes de implementar ajuda a alinhar expectativas.

### 📦 Contribuir com templates
Os templates (boilerplate) são o coração do Fenor — são eles que permitem criar apps com estrutura pronta. Para adicionar um novo template:

1. Copie a estrutura de `boilerplate/pt/` ou `boilerplate/en/` como base
2. Crie seu template em `boilerplate/pt/` (PT) e/ou `boilerplate/en/` (EN)
3. Certifique-se de que o template inclui:
   - `index.php` funcional
   - `.env.example` com as variáveis necessárias
   - Autenticação básica (se aplicável)
   - `README.md` explicando o que o template faz
4. Abra um Pull Request com uma descrição do template e para que tipo de projeto ele serve

### 🔧 Contribuir com código
Para correções de bugs ou melhorias no `install.sh`, `uninstall.sh`, scripts em `bin/` ou no Studio:

1. Faça um fork do repositório
2. Crie uma branch: `git checkout -b minha-melhoria`
3. Faça suas alterações
4. Teste em uma VPS Ubuntu 24.04 limpa (ou localmente com WSL2)
5. Abra um Pull Request descrevendo o que mudou e por quê

---

## Diretrizes gerais

- **Mantenha a simplicidade** — o Fenor foi projetado para ser fácil de entender e modificar. Evite adicionar dependências desnecessárias.
- **Documente o comportamento** — se seu código faz algo não óbvio, adicione um comentário explicando o *porquê* (não o *o quê*).
- **Teste antes de submeter** — mudanças no `install.sh` devem ser testadas em uma VPS limpa.
- **Seja respeitoso** — este é um espaço aberto para desenvolvedores experientes e iniciantes. Toda contribuição, por menor que seja, tem valor.

---

## Dúvidas

Abra uma issue com a tag `question` ou inicie uma discussão em [GitHub Discussions](https://github.com/FENOR-IA/fenor.ia/discussions).

---

*Inglês / English: Contributions are welcome in any form — bug reports, template submissions, code fixes or documentation improvements. Please open an issue before starting significant work so we can align on the approach. Be respectful and constructive — this project is open to developers of all experience levels.*
