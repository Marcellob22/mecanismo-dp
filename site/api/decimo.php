<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/decimo.php';
require_once __DIR__ . '/../includes/folha_xlsx.php';

$user = exigirLoginApi();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    respostaJson([
        'empresas' => decimoListarEmpresas(),
        'executores' => decimoListarExecutores(),
    ]);
}

if ($metodo === 'PATCH') {
    exigirCsrfApi();
    $id = $_GET['id'] ?? '';
    $dados = corpoJson();
    if ($id === '' || !isset($dados['campo']) || !array_key_exists('valor', $dados)) {
        respostaJson(['erro' => 'Dados incompletos.'], 400);
    }
    $erro = decimoAtualizarCampo($id, (string)$dados['campo'], $dados['valor'], $user['nome']);
    if ($erro) {
        respostaJson(['erro' => $erro], 400);
    }
    respostaJson(['ok' => true]);
}

if ($metodo === 'POST') {
    exigirCsrfApi();
    // Tudo aqui é restrito ao gestor
    $user = exigirGestorApi();

    // Importação por arquivo (.xlsx ou .csv): multipart/form-data
    if (!empty($_FILES['arquivo'])) {
        if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            respostaJson(['erro' => 'Não foi possível receber o arquivo.'], 400);
        }
        $nome = $_FILES['arquivo']['name'];
        try {
            if (preg_match('/\.xlsx$/i', $nome)) {
                $matriz = lerXlsx($_FILES['arquivo']['tmp_name']);
            } elseif (preg_match('/\.(csv|txt)$/i', $nome)) {
                $conteudo = file_get_contents($_FILES['arquivo']['tmp_name']);
                if (!mb_check_encoding($conteudo, 'UTF-8')) {
                    $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
                }
                $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
                $linhas = preg_split('/\r\n|\r|\n/', $conteudo);
                $delim = (substr_count($linhas[0] ?? '', ';') >= substr_count($linhas[0] ?? '', ',')) ? ';' : ',';
                $matriz = array_map(fn($l) => str_getcsv($l, $delim), $linhas);
            } else {
                respostaJson(['erro' => 'Envie um arquivo .xlsx ou .csv.'], 400);
            }
        } catch (RuntimeException $e) {
            respostaJson(['erro' => $e->getMessage()], 400);
        }
        $empresas = decimoLinhasParaEmpresas($matriz);
        if (empty($empresas)) {
            respostaJson(['erro' => 'Não encontrei nenhuma empresa no arquivo.'], 400);
        }
        respostaJson(decimoImportarEmpresas($empresas));
    }

    $dados = corpoJson();
    $acao = $dados['acao'] ?? '';

    if ($acao === 'importar_empresas') {
        $texto = (string)($dados['texto'] ?? '');
        $linhas = preg_split('/\r\n|\r|\n/', $texto);
        $matriz = [];
        foreach ($linhas as $l) {
            if (trim($l) === '') continue;
            $matriz[] = str_contains($l, "\t") ? explode("\t", $l) : (str_contains($l, ';') ? explode(';', $l) : [$l]);
        }
        $empresas = decimoLinhasParaEmpresas($matriz);
        if (empty($empresas)) {
            respostaJson(['erro' => 'Cole pelo menos uma empresa.'], 400);
        }
        respostaJson(decimoImportarEmpresas($empresas));
    }

    if ($acao === 'importar_executores') {
        $nomes = preg_split('/\r\n|\r|\n|;|\t/', (string)($dados['texto'] ?? ''));
        respostaJson(decimoImportarExecutores($nomes));
    }

    if ($acao === 'executor_ativo') {
        $stmt = getDB()->prepare("UPDATE decimo_executor SET ativo = ? WHERE id = ?");
        $stmt->execute([empty($dados['ativo']) ? 0 : 1, (string)($dados['id'] ?? '')]);
        respostaJson(['ok' => true]);
    }

    if ($acao === 'remover_empresa') {
        $stmt = getDB()->prepare("DELETE FROM decimo_empresa WHERE id = ?");
        $stmt->execute([(string)($dados['id'] ?? '')]);
        respostaJson(['ok' => true]);
    }

    respostaJson(['erro' => 'Ação inválida.'], 400);
}

respostaJson(['erro' => 'Método não suportado.'], 405);
