<?php
// Camada de dados da Página Inicial: atividades pessoais + demandas do Calendário criadas pelo usuário.
require_once __DIR__ . '/db.php';

const INICIO_DIAS_ACUMULO = 7; // pendências de dias anteriores que reaparecem em "hoje"

function inicioDataValida(?string $d): bool
{
    if (!is_string($d) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function inicioNormalizarAtividade(array $a): array
{
    return [
        'id' => $a['id'],
        'origem' => 'atividade',
        'titulo' => $a['titulo'],
        'tipo' => $a['tipo'],
        'data' => $a['data'],
        'prazo' => $a['prazo'],
        'status' => $a['status'],
        'concluida_em' => $a['concluida_em'],
    ];
}

function inicioNormalizarDemanda(array $d): array
{
    return [
        'id' => $d['id'],
        'origem' => 'demanda',
        'titulo' => $d['abreviacao'],
        'tipo' => 'DIA',
        'data' => $d['data'],
        'prazo' => null,
        'status' => $d['status'] === 'CONCLUIDA' ? 'CONCLUIDA' : 'PENDENTE',
        'concluida_em' => $d['concluida_em'] ?? null,
    ];
}

// Tudo o que a página mostra para o dia $dia ("hoje" vem do navegador do usuário).
function inicioDoDia(string $uid, string $dia, string $hoje): array
{
    $pdo = getDB();

    // 1) Executadas no dia
    $exec = [];
    $st = $pdo->prepare("SELECT * FROM atividade WHERE usuario_id = ? AND status = 'CONCLUIDA' AND DATE(concluida_em) = ? ORDER BY concluida_em");
    $st->execute([$uid, $dia]);
    foreach ($st->fetchAll() as $a) $exec[] = inicioNormalizarAtividade($a);
    $st = $pdo->prepare("SELECT * FROM demanda WHERE criado_por_id = ? AND status = 'CONCLUIDA' AND COALESCE(DATE(concluida_em), data) = ? ORDER BY created_at");
    $st->execute([$uid, $dia]);
    foreach ($st->fetchAll() as $d) $exec[] = inicioNormalizarDemanda($d);

    // 2) A executar no dia (+ acúmulo dos últimos dias quando se olha o dia de hoje)
    $ate = $dia;
    $desde = $dia;
    if ($dia === $hoje) {
        $desde = date('Y-m-d', strtotime($hoje . ' -' . INICIO_DIAS_ACUMULO . ' days'));
    }
    $aExec = [];
    $st = $pdo->prepare("SELECT * FROM atividade WHERE usuario_id = ? AND tipo = 'DIA' AND status = 'PENDENTE' AND data BETWEEN ? AND ? ORDER BY data, created_at");
    $st->execute([$uid, $desde, $ate]);
    foreach ($st->fetchAll() as $a) $aExec[] = inicioNormalizarAtividade($a);
    $st = $pdo->prepare("SELECT * FROM demanda WHERE criado_por_id = ? AND status <> 'CONCLUIDA' AND data BETWEEN ? AND ? ORDER BY data, created_at");
    $st->execute([$uid, $desde, $ate]);
    foreach ($st->fetchAll() as $d) $aExec[] = inicioNormalizarDemanda($d);
    usort($aExec, fn($x, $y) => strcmp((string)$x['data'], (string)$y['data']));

    // 3) Grandes pendências (lista corrente, não depende do dia)
    $grandes = [];
    $st = $pdo->prepare("SELECT * FROM atividade WHERE usuario_id = ? AND tipo = 'GRANDE' AND status = 'PENDENTE' ORDER BY prazo, created_at");
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $a) $grandes[] = inicioNormalizarAtividade($a);

    return ['executadas' => $exec, 'a_executar' => $aExec, 'grandes' => $grandes];
}

// Cria uma atividade. $executada = já entra como concluída no dia $data.
function inicioCriarAtividade(string $uid, string $titulo, string $tipo, ?string $data, ?string $prazo, bool $executada): array|string
{
    $titulo = trim(preg_replace('/\s+/', ' ', $titulo));
    if ($titulo === '') return 'Escreva o título da atividade.';
    if (mb_strlen($titulo) > 255) return 'O título é longo demais (máximo 255 caracteres).';
    if (!in_array($tipo, ['DIA', 'GRANDE'], true)) return 'Tipo inválido.';

    if ($tipo === 'DIA') {
        if (!inicioDataValida($data)) return 'Data inválida.';
        $prazo = null;
    } else {
        if (!inicioDataValida($prazo)) return 'Informe o prazo da demanda grande.';
        $data = null;
        $executada = false;
    }

    $id = gerarId();
    $pdo = getDB();
    $st = $pdo->prepare("
        INSERT INTO atividade (id, usuario_id, titulo, tipo, data, prazo, status, concluida_em, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $st->execute([
        $id, $uid, $titulo, $tipo, $data, $prazo,
        $executada ? 'CONCLUIDA' : 'PENDENTE',
        $executada ? ($data . ' ' . date('H:i:s')) : null,
    ]);
    $st = $pdo->prepare("SELECT * FROM atividade WHERE id = ?");
    $st->execute([$id]);
    return inicioNormalizarAtividade($st->fetch());
}

// Marca como concluída ou volta para pendente. $diaConclusao = data de hoje no navegador do usuário.
function inicioAlterarStatus(string $uid, string $origem, string $id, string $novo, string $diaConclusao): ?string
{
    if (!in_array($novo, ['CONCLUIDA', 'PENDENTE'], true)) return 'Status inválido.';
    if (!inicioDataValida($diaConclusao)) return 'Data inválida.';
    $quando = $novo === 'CONCLUIDA' ? ($diaConclusao . ' ' . date('H:i:s')) : null;
    $pdo = getDB();

    if ($origem === 'atividade') {
        $st = $pdo->prepare("UPDATE atividade SET status = ?, concluida_em = ? WHERE id = ? AND usuario_id = ?");
        $st->execute([$novo, $quando, $id, $uid]);
        $st = $pdo->prepare("SELECT COUNT(*) AS t FROM atividade WHERE id = ? AND usuario_id = ?");
        $st->execute([$id, $uid]);
    } elseif ($origem === 'demanda') {
        $st = $pdo->prepare("UPDATE demanda SET status = ?, concluida_em = ? WHERE id = ? AND criado_por_id = ?");
        $st->execute([$novo === 'CONCLUIDA' ? 'CONCLUIDA' : 'A_FAZER', $quando, $id, $uid]);
        $st = $pdo->prepare("SELECT COUNT(*) AS t FROM demanda WHERE id = ? AND criado_por_id = ?");
        $st->execute([$id, $uid]);
    } else {
        return 'Origem inválida.';
    }
    return ((int)$st->fetch()['t'] === 0) ? 'Item não encontrado.' : null;
}

function inicioExcluirAtividade(string $uid, string $id): void
{
    $st = getDB()->prepare("DELETE FROM atividade WHERE id = ? AND usuario_id = ?");
    $st->execute([$id, $uid]);
}

// Atividades do usuário para o Calendário (as do dia pela data, as grandes pelo prazo).
function inicioParaCalendario(string $uid): array
{
    $st = getDB()->prepare("SELECT * FROM atividade WHERE usuario_id = ? ORDER BY COALESCE(data, prazo)");
    $st->execute([$uid]);
    return array_map('inicioNormalizarAtividade', $st->fetchAll());
}
