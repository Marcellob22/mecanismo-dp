<?php
require_once __DIR__ . '/folha_xlsx.php';

// Tradução fiel de parse_dominio_tabular(). Recebe o caminho do .xlsx e
// devolve [competencia, funcionarios[]].
function parseDominioTabular(string $caminho): array
{
    $rows = lerXlsx($caminho);
    if (empty($rows)) {
        throw new RuntimeException('Arquivo vazio.');
    }

    // Mapa de cabeçalho: nome da coluna -> índice
    $h = [];
    foreach ($rows[0] as $i => $v) {
        if ($v !== null && $v !== '') {
            $h[$v] = $i;
        }
    }
    $col = function (string $nome, int $padrao) use ($h): int {
        return $h[$nome] ?? $padrao;
    };
    $val = function (array $row, int $indice) {
        return $row[$indice] ?? null;
    };

    // Detectar competência
    $comp = '';
    $colComp = $col('cp_competencia', 5);
    for ($i = 1; $i <= 4 && $i < count($rows); $i++) {
        $v = $val($rows[$i], $colComp);
        if ($v) {
            $s = (string)$v;
            if (preg_match('#^\d{2}/\d{4}#', $s)) {
                $comp = substr($s, 0, 7);
                break;
            }
        }
    }

    // Detectar win_mode (valores multiplicados por 100 em certas exportações)
    $winMode = false;
    $colBaseInss = $col('cp_bas_inss', 56);
    for ($i = 1; $i <= 9 && $i < count($rows); $i++) {
        $v = $val($rows[$i], $colBaseInss);
        if (is_numeric($v) && (float)$v > 100000) {
            $winMode = true;
            break;
        }
    }

    $tipoCol = $col('cp_tipo_linha', 40);
    $sitMap = [1 => 'Ativo', 2 => 'Ferias', 3 => 'Afastado', 6 => 'Afastado', 8 => 'Demitido', 9 => 'Desligado', 35 => 'Afastado'];

    $funcsDict = [];

    $colNome = $col('cp_nome_epr', 11);
    $colSituacao = $col('cp_situacao', 12);
    $colVinculo = $col('cp_vinculo', 17);
    $colCargo = $col('cp_nome_car', 16);
    $colSalario = $col('cp_salario', 14);
    $colHorasMes = $col('cp_horas_mes', 39);
    $colNumDep = $col('cp_num_dep', 55);
    $colEveP = $col('cp_nome_eve_p', 23);
    $colEveValP = $col('cp_eve_val_p', 25);
    $colEveInfP = $col('cp_eve_inf_p', 24);
    $colEveD = $col('cp_nome_eve_d', 28);
    $colEveValD = $col('cp_eve_val_d', 30);
    $colEveInfD = $col('cp_eve_inf_d', 29);
    $colTotProv = $col('cp_tot_prov', 50);
    $colTotDesc = $col('cp_tot_desc', 51);
    $colTotLiq = $col('cp_tot_liq', 54);
    $colBasFgts = $col('cp_bas_fgts', 58);
    $colValFgts = $col('cp_val_fgts', 59);
    $colBasIrrf = $col('cp_bas_irrf', 60);
    $colBaseIrrfMensal = $col('cp_base_irrf_mensal', 73);
    $colBaseIrrfFerias = $col('cp_base_irrf_ferias', 74);
    $colValorIrrfMensal = $col('cp_valor_irrf_mensal', 78);
    $colValorIrrfFerias = $col('cp_valor_irrf_ferias', 79);

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $nome = trim((string)($val($row, $colNome) ?? ''));
        if ($nome === '') continue;

        $tipo = $row[$tipoCol] ?? null;

        if (!isset($funcsDict[$nome])) {
            $funcsDict[$nome] = [
                'nome' => $nome, 'situacao' => 'Ativo', 'vinculo' => '',
                'vinculo_cod' => 0, 'sit_raw' => 1,
                'cargo' => '', 'salario' => 0.0, 'horas_mes' => 220.0,
                'num_dep' => 0, 'base_inss' => 0.0, 'base_fgts' => 0.0,
                'fgts' => 0.0, 'base_irrf' => 0.0,
                'base_irrf_mensal' => 0.0, 'base_irrf_ferias' => 0.0,
                'val_irrf_mensal' => 0.0, 'val_irrf_ferias' => 0.0,
                'proventos' => 0.0, 'descontos' => 0.0, 'liquido' => 0.0,
                'rubricas_p' => [], 'rubricas_d' => [],
            ];
        }
        $f = &$funcsDict[$nome];

        if ($tipo == 1) {
            $sitRaw = $val($row, $colSituacao);
            $f['sit_raw'] = $sitRaw;
            $f['situacao'] = $sitMap[$sitRaw] ?? (string)($sitRaw ?: 'Ativo');
            $vincRaw = $val($row, $colVinculo);
            $f['vinculo_cod'] = is_int($vincRaw) ? $vincRaw : (is_numeric($vincRaw) ? (int)$vincRaw : 0);
            $f['vinculo'] = (string)($vincRaw ?? '');
            $f['cargo'] = trim((string)($val($row, $colCargo) ?? ''));
            $f['salario'] = tf($val($row, $colSalario), $winMode) ?? $f['salario'];
            $f['horas_mes'] = tf($val($row, $colHorasMes)) ?? $f['horas_mes'];
            $f['num_dep'] = (int)(tf($val($row, $colNumDep)) ?? 0);
        } elseif ($tipo == 3) {
            $ep = trim((string)($val($row, $colEveP) ?? ''));
            $vp = tf($val($row, $colEveValP), $winMode);
            $ip = tf($val($row, $colEveInfP));
            $ed = trim((string)($val($row, $colEveD) ?? ''));
            $vd = tf($val($row, $colEveValD), $winMode);
            $idd = tf($val($row, $colEveInfD));
            if ($ep !== '' && $vp !== null) $f['rubricas_p'][] = ['desc' => $ep, 'valor' => $vp, 'qtd' => $ip ?? 0];
            if ($ed !== '' && $vd !== null) $f['rubricas_d'][] = ['desc' => $ed, 'valor' => $vd, 'qtd' => $idd ?? 0];
        } elseif ($tipo == 4) {
            $prov = tf($val($row, $colTotProv), $winMode);
            $desc = tf($val($row, $colTotDesc), $winMode);
            $liq = tf($val($row, $colTotLiq), $winMode);
            if ($prov) $f['proventos'] = $prov;
            if ($desc) $f['descontos'] = $desc;
            if ($liq) $f['liquido'] = $liq;
        } elseif ($tipo == 5) {
            $bi = tf($val($row, $colBaseInss), $winMode);
            $bf = tf($val($row, $colBasFgts), $winMode);
            $vf = tf($val($row, $colValFgts), $winMode);
            $br = tf($val($row, $colBasIrrf), $winMode);
            if ($bi) $f['base_inss'] = $bi;
            if ($bf) $f['base_fgts'] = $bf;
            if ($vf) $f['fgts'] = $vf;
            if ($br) $f['base_irrf'] = $br;

            $bim = tf($val($row, $colBaseIrrfMensal), $winMode);
            $bif = tf($val($row, $colBaseIrrfFerias), $winMode);
            $vim = tf($val($row, $colValorIrrfMensal), $winMode);
            $vif = tf($val($row, $colValorIrrfFerias), $winMode);
            if ($bim) $f['base_irrf_mensal'] = $bim;
            if ($bif) $f['base_irrf_ferias'] = $bif;
            if ($vim) $f['val_irrf_mensal'] = $vim;
            if ($vif) $f['val_irrf_ferias'] = $vif;

            if (!$f['salario']) {
                $f['salario'] = tf($val($row, $colSalario), $winMode) ?? 0;
            }
            if (!$f['cargo']) {
                $f['cargo'] = trim((string)($val($row, $colCargo) ?? ''));
            }
            if (!$f['vinculo']) {
                $vincRaw = $val($row, $colVinculo);
                $f['vinculo_cod'] = is_int($vincRaw) ? $vincRaw : (is_numeric($vincRaw) ? (int)$vincRaw : 0);
                $f['vinculo'] = (string)($vincRaw ?? '');
            }
            if ($f['situacao'] === 'Ativo') {
                $sitRaw = $val($row, $colSituacao);
                $f['sit_raw'] = $sitRaw;
                $f['situacao'] = $sitMap[$sitRaw] ?? 'Ativo';
            }
        }
        unset($f);
    }

    return [$comp, array_values($funcsDict)];
}
