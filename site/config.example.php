<?php
// Configuração de conexão com o banco de dados MySQL.
// Preencha com os dados que você criar no cPanel (Banco de dados > MySQL).
// Normalmente o nome do banco e do usuário vêm com um prefixo do seu
// usuário de hospedagem, ex: mecanismodp1_dbname / mecanismodp1_dbuser

return [
    'db_host' => 'localhost',
    'db_name' => 'TROQUE_PELO_NOME_DO_BANCO',
    'db_user' => 'TROQUE_PELO_USUARIO_DO_BANCO',
    'db_pass' => 'TROQUE_PELA_SENHA_DO_BANCO',

    // Gere uma chave aleatória própria (pode usar um gerador de senhas) e
    // cole aqui. É usada só para reforçar a segurança das sessões de login.
    'app_key' => 'TROQUE_POR_UMA_CHAVE_ALEATORIA_LONGA',
];
