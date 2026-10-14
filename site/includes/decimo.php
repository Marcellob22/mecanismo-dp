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
               enc_status, enc_executor_id, observacao, pro_labore, equipe, atualizado_por, atualizado_em
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

    $iCod = $iNome = $iCnpj = $iEquipe = null;
    $cab = array_map(fn($c) => mb_strtolower(trim((string)$c)), $matriz[0]);
    $temCabecalho = false;
    // Só vale como cabeçalho se a célula for exatamente um nome de coluna conhecido
    // (assim uma empresa chamada "EMPRESA ALFA" na primeira linha não é confundida).
    foreach ($cab as $i => $c) {
        if ($iCod === null && preg_match('/^(c[oó]d\.?|c[oó]digo|codigo|c[oó]d\. ?empresa|c[oó]digo da empresa)$/u', $c)) { $iCod = $i; $temCabecalho = true; }
        elseif ($iCnpj === null && preg_match('/^(cnpj|cnpj\/cpf|cnpj da empresa)$/u', $c)) { $iCnpj = $i; $temCabecalho = true; }
        elseif ($iEquipe === null && preg_match('/^(time|equipe|c[eé]lula|celula|time respons[aá]vel)$/u', $c)) { $iEquipe = $i; $temCabecalho = true; }
        elseif ($iNome === null && preg_match('/^(nome|empresa|nome da empresa|nome empresa|raz[aã]o social|raz[aã]o|cliente)$/u', $c)) { $iNome = $i; $temCabecalho = true; }
    }

    if ($temCabecalho && $iNome !== null) {
        array_shift($matriz);
    } else {
        $iCod = $iNome = $iCnpj = $iEquipe = null;
        $n = count($matriz[0]);
        if ($n <= 1) { $iNome = 0; }
        elseif ($n === 2) { $iCod = 0; $iNome = 1; }
        elseif ($n === 3) { $iCod = 0; $iNome = 1; $iCnpj = 2; }
        else { $iCod = 0; $iNome = 1; $iCnpj = 2; $iEquipe = 3; }
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
        $equipe = $iEquipe !== null ? trim(preg_replace('/\s+/', ' ', (string)($l[$iEquipe] ?? ''))) : '';
        $empresas[] = ['codigo' => $cod, 'nome' => $nome, 'cnpj' => $cnpj, 'equipe' => $equipe];
    }
    return $empresas;
}

// Importa empresas novas. Empresas que já existem (mesmo CNPJ ou mesmo nome) não são
// duplicadas e nunca perdem as marcações; só o time (equipe) é atualizado, se vier no arquivo.
function decimoImportarEmpresas(array $empresas): array
{
    // Linhas repetidas no próprio arquivo (mesmo nome ou mesmo CNPJ): fica o cadastro da
    // primeira linha e vale o time da última linha preenchida.
    $unicas = [];
    $indicePorChave = [];
    $repetidas = 0;
    foreach ($empresas as $e) {
        $chaves = ['n:' . decimoNormalizarNome($e['nome'])];
        $dg = decimoSoDigitos($e['cnpj']);
        if ($dg !== '') $chaves[] = 'c:' . $dg;
        $pos = null;
        foreach ($chaves as $c) {
            if (isset($indicePorChave[$c])) { $pos = $indicePorChave[$c]; break; }
        }
        if ($pos === null) {
            $pos = count($unicas);
            $unicas[] = $e;
        } else {
            $repetidas++;
            if (($e['equipe'] ?? '') !== '') $unicas[$pos]['equipe'] = $e['equipe'];
        }
        foreach ($chaves as $c) $indicePorChave[$c] = $pos;
    }
    $empresas = $unicas;

    $pdo = getDB();
    $existentes = $pdo->query("SELECT id, nome, cnpj, equipe FROM decimo_empresa")->fetchAll();
    $porNome = [];
    $porCnpj = [];
    $equipeAtual = [];
    foreach ($existentes as $e) {
        $porNome[decimoNormalizarNome($e['nome'])] = $e['id'];
        $d = decimoSoDigitos($e['cnpj']);
        if ($d !== '') $porCnpj[$d] = $e['id'];
        $equipeAtual[$e['id']] = (string)$e['equipe'];
    }

    $ins = $pdo->prepare("
        INSERT INTO decimo_empresa (id, codigo, nome, cnpj, equipe, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $upd = $pdo->prepare("UPDATE decimo_empresa SET equipe = ? WHERE id = ?");
    $novas = 0;
    $ignoradas = $repetidas;
    $atualizadas = 0;
    $tocadas = [];
    $pdo->beginTransaction();
    try {
        foreach ($empresas as $e) {
            $chaveNome = decimoNormalizarNome($e['nome']);
            $d = decimoSoDigitos($e['cnpj']);
            $equipe = mb_substr($e['equipe'] ?? '', 0, 100);
            $idExistente = $porNome[$chaveNome] ?? ($d !== '' ? ($porCnpj[$d] ?? null) : null);

            if ($idExistente !== null && isset($tocadas[$idExistente])) {
                // Linha repetida dentro do mesmo arquivo: vale a primeira ocorrência.
                $ignoradas++;
                continue;
            }

            if ($idExistente !== null) {
                $tocadas[$idExistente] = true;
                if ($equipe !== '' && $equipeAtual[$idExistente] !== $equipe) {
                    $upd->execute([$equipe, $idExistente]);
                    $equipeAtual[$idExistente] = $equipe;
                    $atualizadas++;
                } else {
                    $ignoradas++;
                }
                continue;
            }

            $id = gerarId();
            $ins->execute([
                $id,
                $e['codigo'] !== '' ? mb_substr($e['codigo'], 0, 50) : null,
                mb_substr($e['nome'], 0, 255),
                $e['cnpj'] !== '' ? mb_substr($e['cnpj'], 0, 20) : null,
                $equipe !== '' ? $equipe : null,
            ]);
            $porNome[$chaveNome] = $id;
            if ($d !== '') $porCnpj[$d] = $id;
            $equipeAtual[$id] = $equipe;
            $tocadas[$id] = true;
            $novas++;
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    return ['novas' => $novas, 'ignoradas' => $ignoradas, 'atualizadas' => $atualizadas];
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
