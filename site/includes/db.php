<?php
// Conexão com o banco e criação automática das tabelas (roda uma vez, na
// primeira requisição — depois disso as tabelas já existem e o comando
// "CREATE TABLE IF NOT EXISTS" simplesmente não faz nada).

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $configPath = __DIR__ . '/../config.php';
        if (!file_exists($configPath)) {
            http_response_code(500);
            die('Arquivo config.php não encontrado. Copie config.example.php para config.php e preencha os dados do banco.');
        }
        $config = require $configPath;

        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Não foi possível conectar ao banco de dados. Confira os dados em config.php.');
        }
        criarTabelas($pdo);
    }
    return $pdo;
}

function criarTabelas(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dupla (
            id VARCHAR(36) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            consultor_id VARCHAR(36) NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuario (
            id VARCHAR(36) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('GESTOR','CONSULTOR','OPERACIONAL') NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            dupla_id VARCHAR(36) NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demanda (
            id VARCHAR(36) PRIMARY KEY,
            abreviacao VARCHAR(255) NOT NULL,
            data DATE NOT NULL,
            prioridade ENUM('BAIXA','MEDIA','ALTA') NOT NULL DEFAULT 'MEDIA',
            status ENUM('A_FAZER','EM_ANDAMENTO','CONCLUIDA','ATRASADA') NOT NULL DEFAULT 'A_FAZER',
            observacoes TEXT NULL,
            dupla_id VARCHAR(36) NOT NULL,
            criado_por_id VARCHAR(36) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS planejamento (
            id VARCHAR(36) PRIMARY KEY,
            titulo VARCHAR(255) NOT NULL,
            data_inicio DATE NOT NULL,
            data_fim DATE NOT NULL,
            observacoes TEXT NULL,
            dupla_id VARCHAR(36) NOT NULL,
            criado_por_id VARCHAR(36) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily (
            id VARCHAR(36) PRIMARY KEY,
            data DATE NOT NULL,
            planejamento_do_dia TEXT NOT NULL,
            impedimentos TEXT NOT NULL,
            como_ajudar TEXT NOT NULL,
            dupla_id VARCHAR(36) NOT NULL,
            criado_por_id VARCHAR(36) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    criarTabelasDecimo($pdo);
    migrarColunasNovas($pdo);
}

// Adiciona colunas novas em bancos já existentes, sem apagar nada.
// Funciona mesmo em versões antigas do MySQL/MariaDB que não suportam
// "ADD COLUMN IF NOT EXISTS" (ex: MySQL 5.7), checando manualmente antes.
function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tabela, $coluna]);
    return (int)$stmt->fetch()['total'] > 0;
}

function migrarColunasNovas(PDO $pdo): void
{
    if (!colunaExiste($pdo, 'usuario', 'email')) {
        $pdo->exec("ALTER TABLE usuario ADD COLUMN email VARCHAR(255) NULL");
    }
    if (!colunaExiste($pdo, 'usuario', 'cpf')) {
        $pdo->exec("ALTER TABLE usuario ADD COLUMN cpf VARCHAR(14) NULL");
    }
    if (!colunaExiste($pdo, 'planejamento', 'status')) {
        $pdo->exec("ALTER TABLE planejamento ADD COLUMN status ENUM('ATIVO','CONCLUIDO') NOT NULL DEFAULT 'ATIVO'");
    }
}

// Controle do 13º salário (2026): empresas, executores e marcações por etapa.
// status de cada etapa: 0 = pendente, 1 = feito, 2 = não se aplica.
function criarTabelasDecimo(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS decimo_executor (
            id VARCHAR(36) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL UNIQUE,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS decimo_empresa (
            id VARCHAR(36) PRIMARY KEY,
            codigo VARCHAR(50) NULL,
            nome VARCHAR(255) NOT NULL,
            cnpj VARCHAR(20) NULL,
            ad_status TINYINT NOT NULL DEFAULT 0,
            ad_executor_id VARCHAR(36) NULL,
            int_status TINYINT NOT NULL DEFAULT 0,
            int_executor_id VARCHAR(36) NULL,
            enc_status TINYINT NOT NULL DEFAULT 0,
            enc_executor_id VARCHAR(36) NULL,
            observacao TEXT NULL,
            atualizado_por VARCHAR(255) NULL,
            atualizado_em DATETIME NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function gerarId(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
