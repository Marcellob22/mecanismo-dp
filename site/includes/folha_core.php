<?php
// ============================================================================
// Conferência de Folha de Pagamento — Pretorian
// Porte fiel do sistema original (ConferenciaFolha.py) para PHP.
// ============================================================================

// ---------- Tabelas 2026 (idênticas ao original) ----------
const SAL_MIN_2026 = 1621.00;

const INSS_FAIXAS = [
    ['ate' => 1621.00, 'aliq' => 0.075],
    ['ate' => 2900.93, 'aliq' => 0.09],
    ['ate' => 4347.65, 'aliq' => 0.12],
    ['ate' => 8475.55, 'aliq' => 0.14],
];

const IRRF_FAIXAS = [
    ['ate' => 2428.80, 'aliq' => 0.000, 'ded' => 0],
    ['ate' => 2826.65, 'aliq' => 0.075, 'ded' => 182.16],
    ['ate' => 3751.05, 'aliq' => 0.150, 'ded' => 394.16],
    ['ate' => 4664.68, 'aliq' => 0.225, 'ded' => 675.49],
    ['ate' => 9e9,     'aliq' => 0.275, 'ded' => 908.73],
];

const SAL_FAMILIA_VALOR = 62.04;

function r2($v): float
{
    return round((float)$v, 2);
}

function calc_inss(float $base): float
{
    $total = 0.0;
    $ant = 0.0;
    foreach (INSS_FAIXAS as $f) {
        $trecho = min($base, $f['ate']) - $ant;
        if ($trecho <= 0) break;
        $total += $trecho * $f['aliq'];
        $ant = $f['ate'];
        if ($base <= $f['ate']) break;
    }
    return min(r2($total), 988.09);
}

function calc_irrf(float $base_irrf, ?float $base_inss = null): float
{
    if ($base_inss === null) $base_inss = $base_irrf;
    foreach (IRRF_FAIXAS as $f) {
        if ($base_irrf <= $f['ate']) {
            $bruto = max(0, r2($base_irrf * $f['aliq'] - $f['ded']));
            if ($base_inss <= 5000) {
                $red = $bruto;
            } elseif ($base_inss <= 7350) {
                $red = max(0, min($bruto, r2(978.62 - 0.133145 * $base_inss)));
            } else {
                $red = 0;
            }
            return max(0, r2($bruto - $red));
        }
    }
    return 0;
}

// Equivalente a tf() do Python: converte valores numéricos ou texto
// (formatos BR "1.234,56") em float, tratando também o "win_mode".
function tf($v, bool $win_mode = false): ?float
{
    if ($v === null || $v === '') return null;
    if (is_bool($v)) return null;
    if (is_float($v)) return $v;
    if (is_int($v)) {
        $n = (float)$v;
        if ($win_mode && $n == (int)$n && abs($n) > 10000) {
            return round($n / 100, 2);
        }
        return $n;
    }
    $s = trim((string)$v);
    if ($s === '') return null;
    try {
        if (strpos($s, ',') !== false) {
            return (float)str_replace(',', '.', str_replace('.', '', $s));
        }
        if (strpos($s, '.') !== false) {
            // Pode ser separador de milhar (BR) ou decimal (US) — como o
            // original, tratamos como milhar quando vier sem vírgula.
            $semPontos = str_replace('.', '', $s);
            if (is_numeric($semPontos)) {
                return (float)$semPontos;
            }
            return is_numeric($s) ? (float)$s : null;
        }
        return is_numeric($s) ? (float)$s : null;
    } catch (Throwable $e) {
        return null;
    }
}

function extrair_percentual(string $nome, ?float $padrao = null): ?float
{
    if (preg_match('/(\d+)\s*%/', $nome, $m)) {
        return ((float)$m[1]) / 100;
    }
    return $padrao;
}

// ---------- Identificadores de rubricas (idênticos ao original) ----------
function eh_inss(string $d): bool
{
    $d = strtoupper(trim($d));
    return in_array($d, ['I.N.S.S.', 'INSS FERIAS', 'INSS DIFERENCA FERIAS'], true);
}

function eh_irrf(string $d): bool
{
    $d = strtoupper($d);
    return (strpos($d, 'IRRF') !== false || strpos($d, 'IMPOSTO DE RENDA') !== false)
        && strpos($d, 'EMPREGADOR') === false;
}

function eh_consignado(string $d): bool
{
    $d = strtoupper(trim($d));
    foreach (['DESC. EMP. CRED. TRAB', 'DESC.EMP.CRED.TRAB', 'DESC EMP CRED TRAB', 'DESC.EMPREST. CREDITO TRAB', 'DESC 2 EMPREST CREDITO TRABALHADOR'] as $p) {
        if (str_starts_with($d, $p)) return true;
    }
    return false;
}

function eh_consignado_ferias(string $d): bool
{
    $d = strtoupper(trim($d));
    return strpos($d, 'CRED TRAB FE') !== false || strpos($d, 'PROVISAO DESC. EMP. CRED. TRAB') !== false;
}

function eh_he(string $d): bool
{
    $d = strtoupper($d);
    if (strpos($d, 'REFLEXO') !== false || strpos($d, 'DSR') !== false) return false;
    return strpos($d, 'EXTRA') !== false || strpos($d, 'H.E') !== false || strpos($d, 'EXTRAORDINAR') !== false;
}

function eh_dsr_he(string $d): bool
{
    $d = strtoupper($d);
    return (strpos($d, 'REFLEXO') !== false || strpos($d, 'DSR') !== false)
        && strpos($d, 'EXTRA') !== false && strpos($d, 'NOTURNO') === false;
}

function eh_noturno(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'NOTURNO') !== false && strpos($d, 'REFLEXO') === false && strpos($d, 'DSR') === false;
}

function eh_dsr_noturno(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'NOTURNO') !== false && (strpos($d, 'REFLEXO') !== false || strpos($d, 'DSR') !== false);
}

function eh_falta_horas(string $d): bool
{
    $d = strtoupper($d);
    return (strpos($d, 'HORA') !== false || strpos($d, 'PARCIAL') !== false)
        && strpos($d, 'FALT') !== false && strpos($d, 'DSR') === false;
}

function eh_falta_dias(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'DIA') !== false && strpos($d, 'FALT') !== false && strpos($d, 'DSR') === false;
}

function eh_dsr_falta(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'FALT') !== false && (strpos($d, 'DSR') !== false || strpos($d, 'REFLEXO') !== false);
}

function eh_periculosidade(string $d): bool
{
    return strpos(strtoupper($d), 'PERICULOSI') !== false;
}

function eh_insalubridade(string $d): bool
{
    return strpos(strtoupper($d), 'INSALUBRI') !== false;
}

function eh_vt(string $d): bool
{
    $d = strtoupper($d);
    return (strpos($d, 'TRANSP') !== false || str_starts_with($d, 'VT')) && strpos($d, 'REEMBOLSO') === false;
}

function eh_sal_familia(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'SAL') !== false && strpos($d, 'FAMIL') !== false && strpos($d, 'FERIAS') === false;
}

function eh_estouro(string $d): bool
{
    return strpos(strtoupper($d), 'ESTOURO') !== false;
}

function eh_pensao(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'PENSAO') !== false || strpos($d, 'PENSÃO') !== false;
}

function eh_quebra_caixa(string $d): bool
{
    $d = strtoupper($d);
    return strpos($d, 'QUEBRA') !== false && strpos($d, 'CAIXA') !== false;
}

function cargo_eh_caixa(string $cargo): bool
{
    return strpos(strtoupper($cargo), 'CAIXA') !== false;
}
