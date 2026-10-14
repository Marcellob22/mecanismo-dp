<?php
require_once __DIR__ . '/db.php';

// ---------- Hierarquia ----------
// GESTOR ve todas as duplas; CONSULTOR ve as duplas que supervisiona;
// OPERACIONAL ve só a própria dupla.
function duplasVisiveis(array $user): array
{
    $pdo = getDB();
    if ($user['role'] === 'GESTOR') {
        return $pdo->query("SELECT * FROM dupla ORDER BY nome ASC")->fetchAll();
    }
    if ($user['role'] === 'CONSULTOR') {
        $stmt = $pdo->prepare("SELECT * FROM dupla WHERE consultor_id = ? ORDER BY nome ASC");
        $stmt->execute([$user['id']]);
        return $stmt->fetchAll();
    }
    if (empty($user['dupla_id'])) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM dupla WHERE id = ?");
    $stmt->execute([$user['dupla_id']]);
    $d = $stmt->fetch();
    return $d ? [$d] : [];
}

function idsDasDuplas(array $duplas): array
{
    return array_map(fn($d) => $d['id'], $duplas);
}

function placeholders(array $itens): string
{
    return implode(',', array_fill(0, count($itens), '?'));
}

// ---------- Duplas ----------
function listarDuplas(): array
{
    return getDB()->query("SELECT * FROM dupla ORDER BY nome ASC")->fetchAll();
}

function criarDupla(string $nome, ?string $consultorId): array
{
    $pdo = getDB();
    $id = gerarId();
    $stmt = $pdo->prepare("INSERT INTO dupla (id, nome, consultor_id, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$id, $nome, $consultorId ?: null]);
    $stmt = $pdo->prepare("SELECT * FROM dupla WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function buscarDuplaPorId(string $id): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM dupla WHERE id = ?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    return $d ?: null;
}

function atualizarDupla(string $id, array $campos): array
{
    $atual = buscarDuplaPorId($id);
    if (!$atual) {
        throw new RuntimeException('Célula não encontrada');
    }
    $nome = $campos['nome'] ?? $atual['nome'];
    $consultorId = array_key_exists('consultor_id', $campos) ? $campos['consultor_id'] : $atual['consultor_id'];

    $stmt = getDB()->prepare("UPDATE dupla SET nome = ?, consultor_id = ? WHERE id = ?");
    $stmt->execute([$nome, $consultorId ?: null, $id]);
    return buscarDuplaPorId($id);
}

// ---------- Usuários ----------
function buscarUsuarioPorUsername(string $username): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM usuario WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function buscarUsuarioPorId(string $id): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM usuario WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function buscarUsuarioPorEmail(string $email): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM usuario WHERE email = ?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function buscarUsuarioPorCpf(string $cpf): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM usuario WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function listarUsuarios(): array
{
    return getDB()->query("SELECT id, nome, username, email, cpf, role, ativo, dupla_id, created_at FROM usuario ORDER BY nome ASC")->fetchAll();
}

function criarUsuario(string $nome, string $username, string $passwordHash, string $role, ?string $duplaId, ?string $email = null, ?string $cpf = null): array
{
    $pdo = getDB();
    $id = gerarId();
    $stmt = $pdo->prepare(
        "INSERT INTO usuario (id, nome, username, password_hash, role, ativo, dupla_id, email, cpf, created_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())"
    );
    $stmt->execute([$id, $nome, $username, $passwordHash, $role, $duplaId ?: null, $email ?: null, $cpf ?: null]);
    return buscarUsuarioPorId($id);
}

// Cadastro público (tela inicial): sempre cria como OPERACIONAL, sem dupla.
// O gestor associa a uma dupla e ajusta o nível de acesso depois, se precisar.
function registrarUsuarioPublico(string $username, string $passwordHash, string $email, string $cpf): array
{
    return criarUsuario($username, $username, $passwordHash, 'OPERACIONAL', null, $email, $cpf);
}

function atualizarUsuario(string $id, array $campos): array
{
    $atual = buscarUsuarioPorId($id);
    if (!$atual) {
        throw new RuntimeException('Usuário não encontrado');
    }
    $nome = $campos['nome'] ?? $atual['nome'];
    $role = $campos['role'] ?? $atual['role'];
    $duplaId = array_key_exists('dupla_id', $campos) ? $campos['dupla_id'] : $atual['dupla_id'];
    $ativo = array_key_exists('ativo', $campos) ? $campos['ativo'] : $atual['ativo'];
    $passwordHash = $campos['password_hash'] ?? $atual['password_hash'];
    $email = array_key_exists('email', $campos) ? $campos['email'] : $atual['email'];
    $cpf = array_key_exists('cpf', $campos) ? $campos['cpf'] : $atual['cpf'];

    $stmt = getDB()->prepare(
        "UPDATE usuario SET nome = ?, role = ?, dupla_id = ?, ativo = ?, password_hash = ?, email = ?, cpf = ? WHERE id = ?"
    );
    $stmt->execute([$nome, $role, $duplaId ?: null, $ativo, $passwordHash, $email ?: null, $cpf ?: null, $id]);
    return buscarUsuarioPorId($id);
}

function existeAlgumUsuario(): bool
{
    $count = getDB()->query("SELECT COUNT(*) AS total FROM usuario")->fetch();
    return (int)$count['total'] > 0;
}

// ---------- Demandas ----------
function listarDemandasPorDuplas(array $duplaIds, ?string $inicio = null, ?string $fim = null): array
{
    if (empty($duplaIds)) return [];
    $pdo = getDB();
    $sql = "SELECT * FROM demanda WHERE dupla_id IN (" . placeholders($duplaIds) . ")";
    $params = $duplaIds;
    if ($inicio && $fim) {
        $sql .= " AND data BETWEEN ? AND ?";
        $params[] = $inicio;
        $params[] = $fim;
    }
    $sql .= " ORDER BY data ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function criarDemanda(array $dados): array
{
    $pdo = getDB();
    $id = gerarId();
    $stmt = $pdo->prepare(
        "INSERT INTO demanda (id, abreviacao, data, prioridade, status, observacoes, dupla_id, criado_por_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $id,
        $dados['abreviacao'],
        $dados['data'],
        $dados['prioridade'] ?: 'MEDIA',
        $dados['status'] ?: 'A_FAZER',
        $dados['observacoes'] ?: null,
        $dados['dupla_id'],
        $dados['criado_por_id'],
    ]);
    $stmt = $pdo->prepare("SELECT * FROM demanda WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function atualizarStatusDemanda(string $id, string $status): void
{
    $stmt = getDB()->prepare("UPDATE demanda SET status = ?, concluida_em = ? WHERE id = ?");
    $stmt->execute([$status, $status === 'CONCLUIDA' ? date('Y-m-d H:i:s') : null, $id]);
}

function buscarDemandaPorId(string $id): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM demanda WHERE id = ?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    return $d ?: null;
}

// ---------- Planejamentos ----------
function listarPlanejamentosPorDuplas(array $duplaIds): array
{
    if (empty($duplaIds)) return [];
    $pdo = getDB();
    $sql = "SELECT * FROM planejamento WHERE dupla_id IN (" . placeholders($duplaIds) . ") ORDER BY data_inicio ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($duplaIds);
    return $stmt->fetchAll();
}

function criarPlanejamento(array $dados): array
{
    $pdo = getDB();
    $id = gerarId();
    $stmt = $pdo->prepare(
        "INSERT INTO planejamento (id, titulo, data_inicio, data_fim, observacoes, dupla_id, criado_por_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'ATIVO', NOW())"
    );
    $stmt->execute([
        $id,
        $dados['titulo'],
        $dados['data_inicio'],
        $dados['data_fim'],
        $dados['observacoes'] ?: null,
        $dados['dupla_id'],
        $dados['criado_por_id'],
    ]);
    $stmt = $pdo->prepare("SELECT * FROM planejamento WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function buscarPlanejamentoPorId(string $id): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM planejamento WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    return $p ?: null;
}

function atualizarStatusPlanejamento(string $id, string $status): void
{
    $stmt = getDB()->prepare("UPDATE planejamento SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

// ---------- Dailies ----------
function listarDailiesPorDuplas(array $duplaIds): array
{
    if (empty($duplaIds)) return [];
    $pdo = getDB();
    $sql = "SELECT * FROM daily WHERE dupla_id IN (" . placeholders($duplaIds) . ") ORDER BY data DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($duplaIds);
    return $stmt->fetchAll();
}

function criarDaily(array $dados): array
{
    $pdo = getDB();
    $id = gerarId();
    $stmt = $pdo->prepare(
        "INSERT INTO daily (id, data, planejamento_do_dia, impedimentos, como_ajudar, dupla_id, criado_por_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $id,
        $dados['data'] ?: date('Y-m-d'),
        $dados['planejamento_do_dia'],
        $dados['impedimentos'],
        $dados['como_ajudar'],
        $dados['dupla_id'],
        $dados['criado_por_id'],
    ]);
    $stmt = $pdo->prepare("SELECT * FROM daily WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
