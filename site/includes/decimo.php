<?php
// Camada de dados do Controle de 13º (2026).
require_once __DIR__ . '/db.php';

const DECIMO_ETAPAS = ['ad', 'int', 'enc'];

function decimoListarExecutores(): array
{
    $stmt = getDB()->query("SELECT id, nome, ativo FROM decimo_executor ORDER BY nome");
    $lista = $stmt->fetchAll();
    foreach ($lista as &$e) {
        $e['ativo'] = (int)$e['ativo'];
    }
    return $lista;
}

function decimoListarEmpresas(): array
{
    $stmt = getDB()->query("
        SELECT id, codigo, nome, cnpj,
               ad_status, ad_executor_id, int_status, int_executor_id,
               enc_status, enc_executor_id, observacao, pro_labore, atualizado_por, atualizado_em
        FROM decimo_empresa ORDER BY nome
    ");
    $lista = $stmt->fetchAll();
    foreach ($lista as &$e) {
        foreach (DECIMO_ETAPAS as $et) {
            $e[$et . '_status'] = (int)$e[$et . '_status'];
        }
        $e['pro_labore'] = (int)$e['pro_labore'];
    }
    return $lista;
}

// Atualiza um único campo de uma empresa. Retorna mensagem de erro ou null.
function decimoAtualizarCampo(string $id, string $campo, $valor, string $quemNome): ?string
{
    $pdo = getDB();
    $camposStatus = ['ad_status', 'int_status', 'enc_status'];
    $camposExecutor = ['ad_executor_id', 'int_executor_id', 'enc_executor_id'];

    if ($campo === 'pro_labore') {
        if (!in_array($valor, [0, 1], true)) {
            return 'Valor inválido.';
        }
    } elseif (in_array($campo, $camposStatus, true)) {
        if (!in_array($valor, [0, 1, 2], true)) {
            return 'Valor inválido.';
        }
    } elseif (in_array($campo, $camposExecutor, true)) {
        if ($valor === '' || $valor === null) {
            $valor = null;
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS t FROM decimo_executor WHERE id = ?");
            $stmt->execute([(string)$valor]);
            if ((int)$stmt->fetch()['t'] === 0) {
                return 'Executor não encontrado.';
            }
        }
    } elseif ($campo === 'observacao') {
        $valor = trim((string)$valor);
        if (mb_strlen($valor) > 5000) {
            return 'A observação é longa demais (máximo 5000 caracteres).';
        }
        if ($valor === '') {
            $valor = null;
        }
    } else {
        return 'Campo inválido.';
    }

    // $campo vem de lista fixa acima, então é seguro montar o nome da coluna.
    $stmt = $pdo->prepare("UPDATE decimo_empresa SET $campo = ?, atualizado_por = ?, atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$valor, $quemNome, $id]);
    if ($stmt->rowCount() === 0) {
        $existe = $pdo->prepare("SELECT COUNT(*) AS t FROM decimo_empresa WHERE id = ?");
        $existe->execute([$id]);
        if ((int)$existe->fetch()['t'] === 0) {
            return 'Empresa não encontrada.';
        }
    }
    return null;
}

function decimoSoDigitos(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s);
}

function decimoNormalizarNome(string $s): string
{
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
}

// Recebe uma matriz de linhas (de colar texto, CSV ou xlsx) e descobre as colunas
// código / nome / CNPJ pelo cabeçalho; sem cabeçalho: 1 coluna = nome,
// 2 colunas = código + nome, 3 ou mais = código + nome + CNPJ.
function decimoLinhasParaEmpresas(array $matriz): array
{
    $matriz = array_values(array_filter($matriz, function ($l) {
        foreach ($l as $c) {
            if (trim((string)$c) !== '') return true;
        }
        return false;
    }));
    if (empty($matriz)) {
        return [];
    }

    $iCod = $iNome = $iCnpj = null;
    $cab = array_map(fn($c) => mb_strtolower(trim((string)$c)), $matriz[0]);
    $temCabecalho = false;
    // Só vale como cabeçalho se a célula for exatamente um nome de coluna conhecido
    // (assim uma empresa chamada "EMPRESA ALFA" na primeira linha não é confundida).
    foreach ($cab as $i => $c) {
        if ($iCod === null && preg_match('/^(c[oó]d\.?|c[oó]digo|codigo|c[oó]d\. ?empresa|c[oó]digo da empresa)$/u', $c)) { $iCod = $i; $temCabecalho = true; }
        elseif ($iCnpj === null && preg_match('/^(cnpj|cnpj\/cpf|cnpj da empresa)$/u', $c)) { $iCnpj = $i; $temCabecalho = true; }
        elseif ($iNome === null && preg_match('/^(nome|empresa|nome da empresa|nome empresa|raz[aã]o social|raz[aã]o|cliente)$/u', $c)) { $iNome = $i; $temCabecalho = true; }
    }

    if ($temCabecalho && $iNome !== null) {
        array_shift($matriz);
    } else {
        $iCod = $iNome = $iCnpj = null;
        $n = count($matriz[0]);
        if ($n <= 1) { $iNome = 0; }
        elseif ($n === 2) { $iCod = 0; $iNome = 1; }
        else { $iCod = 0; $iNome = 1; $iCnpj = 2; }
    }

    $empresas = [];
    foreach ($matriz as $l) {
        $nome = trim((string)($l[$iNome] ?? ''));
        if ($nome === '') continue;
        $cod = $iCod !== null ? trim((string)($l[$iCod] ?? '')) : '';
        // números inteiros vindos do xlsx chegam como float (ex.: 12.0)
        if ($iCod !== null && is_float($l[$iCod] ?? null) && floor($l[$iCod]) == $l[$iCod]) {
            $cod = (string)(int)$l[$iCod];
        }
        $cnpj = $iCnpj !== null ? trim((string)($l[$iCnpj] ?? '')) : '';
        $empresas[] = ['codigo' => $cod, 'nome' => $nome, 'cnpj' => $cnpj];
    }
    return $empresas;
}

// Importa empresas novas, ignorando as que já existem (mesmo CNPJ ou mesmo nome).
function decimoImportarEmpresas(array $empresas): array
{
    $pdo = getDB();
    $existentes = $pdo->query("SELECT nome, cnpj FROM decimo_empresa")->fetchAll();
    $nomes = [];
    $cnpjs = [];
    foreach ($existentes as $e) {
        $nomes[decimoNormalizarNome($e['nome'])] = true;
        $d = decimoSoDigitos($e['cnpj']);
        if ($d !== '') $cnpjs[$d] = true;
    }

    $ins = $pdo->prepare("
        INSERT INTO decimo_empresa (id, codigo, nome, cnpj, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $novas = 0;
    $ignoradas = 0;
    $pdo->beginTransaction();
    try {
        foreach ($empresas as $e) {
            $chaveNome = decimoNormalizarNome($e['nome']);
            $d = decimoSoDigitos($e['cnpj']);
            if (isset($nomes[$chaveNome]) || ($d !== '' && isset($cnpjs[$d]))) {
                $ignoradas++;
                continue;
            }
            $ins->execute([
                gerarId(),
                $e['codigo'] !== '' ? mb_substr($e['codigo'], 0, 50) : null,
                mb_substr($e['nome'], 0, 255),
                $e['cnpj'] !== '' ? mb_substr($e['cnpj'], 0, 20) : null,
            ]);
            $nomes[$chaveNome] = true;
            if ($d !== '') $cnpjs[$d] = true;
            $novas++;
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    return ['novas' => $novas, 'ignoradas' => $ignoradas];
}

function decimoImportarExecutores(array $nomes): array
{
    $pdo = getDB();
    $sel = $pdo->prepare("SELECT id FROM decimo_executor WHERE LOWER(nome) = LOWER(?)");
    $ins = $pdo->prepare("INSERT INTO decimo_executor (id, nome, ativo, created_at) VALUES (?, ?, 1, NOW())");
    $novos = 0;
    $ignorados = 0;
    foreach ($nomes as $n) {
        $n = trim(preg_replace('/\s+/', ' ', (string)$n));
        if ($n === '') continue;
        $sel->execute([$n]);
        if ($sel->fetch()) {
            $ignorados++;
            continue;
        }
        $ins->execute([gerarId(), mb_substr($n, 0, 255)]);
        $novos++;
    }
    return ['novos' => $novos, 'ignorados' => $ignorados];
}
