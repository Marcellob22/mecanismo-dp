<?php
// Leitor mínimo de .xlsx em PHP puro, usando apenas as extensões
// Zip e SimpleXML (padrão em praticamente qualquer hospedagem PHP).
// Não depende de nenhuma biblioteca externa (nada de Composer).

function colunaParaIndice(string $letras): int
{
    $indice = 0;
    $letras = strtoupper($letras);
    for ($i = 0; $i < strlen($letras); $i++) {
        $indice = $indice * 26 + (ord($letras[$i]) - ord('A') + 1);
    }
    return $indice - 1;
}

// Lê a primeira planilha de um arquivo .xlsx e retorna uma matriz de linhas,
// cada uma com o mesmo número de colunas (preenchendo com null quando vazio),
// equivalente ao list(ws.iter_rows(values_only=True)) do openpyxl.
function lerXlsx(string $caminhoArquivo): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensão Zip do PHP não está disponível neste servidor.');
    }

    $zip = new ZipArchive();
    if ($zip->open($caminhoArquivo) !== true) {
        throw new RuntimeException('Não foi possível abrir o arquivo. Confirme que é um .xlsx válido.');
    }

    // Textos compartilhados (sharedStrings.xml)
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $texto = '';
                    foreach ($si->r as $r) {
                        $texto .= (string)$r->t;
                    }
                    $sharedStrings[] = $texto;
                }
            }
        }
    }

    // Localizar a primeira planilha
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheetPath) === false) {
        $sheetPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nome)) {
                $sheetPath = $nome;
                break;
            }
        }
        if ($sheetPath === null) {
            $zip->close();
            throw new RuntimeException('Não foi possível localizar a planilha dentro do arquivo.');
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('Não foi possível ler o conteúdo da planilha.');
    }

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('O conteúdo da planilha está corrompido ou em formato inesperado.');
    }

    $linhasPorIndice = [];
    $maxCol = 0;

    foreach ($sheet->sheetData->row as $rowXml) {
        $rowIndex = ((int)$rowXml['r']) - 1;
        if ($rowIndex < 0) continue;
        $rowData = [];
        foreach ($rowXml->c as $c) {
            $ref = (string)$c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) continue;
            $colIndex = colunaParaIndice($m[1]);
            $maxCol = max($maxCol, $colIndex);

            $tipo = (string)$c['t'];
            $valor = null;
            if (isset($c->v)) {
                $vRaw = (string)$c->v;
                if ($tipo === 's') {
                    $idx = (int)$vRaw;
                    $valor = $sharedStrings[$idx] ?? '';
                } elseif ($tipo === 'str') {
                    $valor = $vRaw;
                } elseif ($tipo === 'b') {
                    $valor = ($vRaw === '1');
                } else {
                    $valor = is_numeric($vRaw) ? (float)$vRaw : $vRaw;
                }
            } elseif ($tipo === 'inlineStr' && isset($c->is->t)) {
                $valor = (string)$c->is->t;
            }
            $rowData[$colIndex] = $valor;
        }
        $linhasPorIndice[$rowIndex] = $rowData;
    }

    if (empty($linhasPorIndice)) {
        throw new RuntimeException('A planilha está vazia.');
    }

    $maxRow = max(array_keys($linhasPorIndice));
    $resultado = [];
    for ($r = 0; $r <= $maxRow; $r++) {
        $linha = [];
        $dados = $linhasPorIndice[$r] ?? [];
        for ($c = 0; $c <= $maxCol; $c++) {
            $linha[] = $dados[$c] ?? null;
        }
        $resultado[] = $linha;
    }

    return $resultado;
}
