# Mecanismo DP (versão PHP)

Site interno de planejamento e controle de demandas do Departamento Pessoal,
com login, hierarquia de acesso (Gestor / Consultor / Operacional) e
calendário de demandas, planejamentos e dailies.

Feito em PHP puro (sem frameworks) + MySQL, para rodar em hospedagem
compartilhada com cPanel — como a Hospedagem GO Linux da Locaweb.

## Requisitos

- PHP 7.4 ou mais recente (a Locaweb já vem com PHP 8.5)
- Um banco de dados MySQL (criado pelo cPanel)

## Como publicar

Veja o guia completo em `guia-deploy-locaweb-cpanel.md`. Resumo:

1. Crie um banco de dados MySQL no cPanel (Banco de dados)
2. Suba todos os arquivos deste pacote para a pasta do seu site (via
   Gerenciador de Arquivos ou FTP)
3. Copie `config.example.php` para `config.php` e preencha com os dados do
   banco que você criou
4. Acesse `seudominio.com.br/setup.php` para criar o primeiro usuário
   (gestor)
5. Pronto — acesse `seudominio.com.br/login.php` para entrar

## Estrutura

```
index.php          → redireciona para login ou dashboard
login.php           → tela de login
logout.php          → encerra a sessão
setup.php           → cria o primeiro usuário (só funciona uma vez)
dashboard.php        → calendário principal
admin.php           → administração de usuários e duplas (só gestor)
config.example.php  → modelo do arquivo de configuração do banco
includes/            → conexão com banco, autenticação, funções de dados
api/                 → endpoints que o site chama para salvar/ler dados
assets/               → CSS e JavaScript
```

## Backup

Como os dados ficam no banco MySQL, o backup deve ser feito por lá: no
cPanel, procure a opção de backup do banco de dados (ou use o phpMyAdmin
para exportar um arquivo .sql periodicamente).
