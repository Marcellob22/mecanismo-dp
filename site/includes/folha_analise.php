<?php
require_once __DIR__ . '/folha_core.php';

// Tradução fiel de analisar(). Recebe a lista de funcionários (do parser) e
// os dias úteis/não úteis do mês, devolve a lista com os cálculos e alertas.
function analisarFolha(array $funcs, float $diasUteis, float $diasNaoUteis): array
{
    $LIMIAR = 2.00;
    $resultado = [];

    foreach ($funcs as $f) {
        $sit = strtolower($f['situacao']);
        $demitido = ($f['sit_raw'] ?? null) === 8;
        if (!$demitido) {
            foreach (['demit', 'deslig'] as $k) {
                if (strpos($sit, $k) !== false) { $demitido = true; break; }
            }
        }

        $rp = $f['rubricas_p'];
        $rd = $f['rubricas_d'];

        $somaSe = function (array $itens, callable $filtro): float {
            $t = 0.0;
            foreach ($itens as $r) if ($filtro($r['desc'])) $t += $r['valor'];
            return r2($t);
        };

        $inss_rub = $somaSe($rd, 'eh_inss');
        $irrf_rub = $somaSe($rd, 'eh_irrf');
        $vt_rub = $somaSe($rd, 'eh_vt');
        $cons_rubricas = array_values(array_filter($rd, fn($r) => eh_consignado($r['desc'])));
        $cons_ferias = array_values(array_filter($rd, fn($r) => eh_consignado_ferias($r['desc'])));
        $pensao_rubricas = array_values(array_filter($rd, fn($r) => eh_pensao($r['desc'])));
        $estouro_rubricas = array_values(array_filter(array_merge($rp, $rd), fn($r) => eh_estouro($r['desc'])));
        $sal_fam_rub = $somaSe($rp, 'eh_sal_familia');

        // Detectar férias no mês
        $rubricas_ferias = array_values(array_filter($rp, function ($r) {
            $u = mb_strtoupper($r['desc']);
            return strpos($u, 'FERIA') !== false || strpos($u, 'FÉRIA') !== false;
        }));
        $tem_ferias = count($rubricas_ferias) > 0;
        $sal_ref = $f['salario'] ?: 0;
        $dias_ferias = 0.0;
        if ($tem_ferias && $sal_ref > 0) {
            $candidatos = array_filter($rubricas_ferias, function ($r) {
                $u = mb_strtoupper($r['desc']);
                return strpos($u, 'HORA') !== false || str_starts_with($u, 'FERIA');
            });
            $val_fer_princ = 0.0;
            foreach ($candidatos as $r) $val_fer_princ = max($val_fer_princ, $r['valor']);
            if ($val_fer_princ > 0) {
                $dias_ferias = min(round($val_fer_princ / ($sal_ref / 30), 1), 30);
            }
        }
        $dias_trabalhados = max(30 - $dias_ferias, 1);

        if ($tem_ferias && $diasUteis > 0 && $dias_ferias > 0) {
            $prop = $dias_trabalhados / 30;
            $dias_uteis_ef = max(round($diasUteis * $prop), 1);
            $dias_nao_uteis_ef = max(round($diasNaoUteis * $prop), 1);
        } else {
            $dias_uteis_ef = $diasUteis;
            $dias_nao_uteis_ef = $diasNaoUteis;
        }

        $peric_val = $somaSe($rp, 'eh_periculosidade');
        $insalub_val = $somaSe($rp, 'eh_insalubridade');
        $base_he = r2(($sal_ref ?: 0) + $peric_val + $insalub_val);
        $valor_hora = $f['horas_mes'] > 0 ? r2($base_he / $f['horas_mes']) : 0;

        $vinculo_cod = $f['vinculo_cod'] ?? 0;
        $socio = ($vinculo_cod === 11);
        $aprendiz = ($vinculo_cod === 53);
        $tem_prolabore = false;
        foreach ($rp as $r) {
            $u = mb_strtoupper($r['desc']);
            $semEspacoTraco = str_replace([' ', '-'], '', $u);
            if (strpos($semEspacoTraco, 'PROLABORE') !== false || strpos($u, 'PRO LABORE') !== false
                || strpos($u, 'PRÓ-LABORE') !== false || strpos($u, 'PRO-LABORE') !== false) {
                $tem_prolabore = true;
                break;
            }
        }
        if ($tem_prolabore) $socio = true;

        // INSS
        if ($socio) {
            $inss_calc = $f['base_inss'] > 0 ? min(r2($f['base_inss'] * 0.11), 988.09) : 0;
        } else {
            $inss_calc = $f['base_inss'] > 0 ? calc_inss($f['base_inss']) : 0;
        }

        // IRRF mensal / férias
        $base_irrf_men = $f['base_irrf_mensal'] ?: (!$f['base_irrf_ferias'] ? $f['base_irrf'] : 0);
        $irrf_calc_men = $base_irrf_men > 0 ? calc_irrf($base_irrf_men, $f['base_inss']) : 0;

        $base_irrf_fer = $f['base_irrf_ferias'] ?? 0;
        $irrf_calc_fer = $base_irrf_fer > 0 ? calc_irrf($base_irrf_fer, $f['base_inss']) : 0;

        $irrf_calc = r2($irrf_calc_men + $irrf_calc_fer);

        // FGTS
        if ($socio) {
            $fgts_calc = 0;
            $base_fgts = 0;
        } else {
            $aliq_fgts = $aprendiz ? 0.02 : 0.08;
            $base_fgts = $f['base_fgts'] ?: $f['base_inss'];
            $fgts_calc = $base_fgts > 0 ? r2($base_fgts * $aliq_fgts) : 0;
        }

        $alertas_encargos = [];
        $alertas_lanc = [];
        $nivel = 'OK';

        if (!$demitido) {
            // INSS
            if ($inss_rub > 0 && $f['base_inss'] > 0) {
                $diff = abs($inss_rub - $inss_calc);
                if ($diff > 0.15) {
                    $tipo = $diff <= $LIMIAR ? 'alerta' : 'critico';
                    $nota = $diff <= $LIMIAR ? ' (arredondamento)' : '';
                    $alertas_encargos[] = ['txt' => sprintf('INSS R$%.2f | calc. R$%.2f | Δ R$%.2f%s', $inss_rub, $inss_calc, $diff, $nota), 'tipo' => $tipo, 'cat' => 'inss'];
                    if ($tipo === 'critico') $nivel = 'CRITICO';
                    elseif ($nivel === 'OK') $nivel = 'ALERTA';
                }
            }

            // FGTS
            if ($f['fgts'] > 0 && $fgts_calc > 0) {
                $diff = abs($f['fgts'] - $fgts_calc);
                if ($diff > 0.50) {
                    $tipo = $diff <= $LIMIAR ? 'alerta' : 'critico';
                    $nota = $diff <= $LIMIAR ? ' (arredondamento)' : '';
                    $alertas_encargos[] = ['txt' => sprintf('FGTS R$%.2f | calc. R$%.2f | Δ R$%.2f%s', $f['fgts'], $fgts_calc, $diff, $nota), 'tipo' => $tipo, 'cat' => 'fgts'];
                    if ($tipo === 'critico' && $nivel !== 'CRITICO') $nivel = 'CRITICO';
                    elseif ($nivel === 'OK') $nivel = 'ALERTA';
                }
            }

            // IRRF mensal / férias (separado por rubrica)
            $irrf_rub_men = $somaSe($rd, function ($d) { return eh_irrf($d) && strpos(mb_strtoupper($d), 'FERIA') === false; });
            $irrf_rub_fer = $somaSe($rd, function ($d) { return eh_irrf($d) && strpos(mb_strtoupper($d), 'FERIA') !== false; });

            if ($irrf_rub_men > 0 && $base_irrf_men > 0) {
                $diff = abs($irrf_rub_men - $irrf_calc_men);
                if ($diff > 0.15) {
                    $nota = $diff <= $LIMIAR ? ' (arredondamento)' : '';
                    $alertas_encargos[] = ['txt' => sprintf('IRRF Mensal R$%.2f | calc. R$%.2f | Δ R$%.2f%s', $irrf_rub_men, $irrf_calc_men, $diff, $nota), 'tipo' => 'alerta', 'cat' => 'irrf'];
                    if ($nivel === 'OK') $nivel = 'ALERTA';
                }
            } elseif ($irrf_calc_men > $LIMIAR && $irrf_rub_men == 0 && $base_irrf_men > 0) {
                $alertas_encargos[] = ['txt' => sprintf('IRRF Mensal nao lancado | base R$%.2f | devido R$%.2f', $base_irrf_men, $irrf_calc_men), 'tipo' => 'alerta', 'cat' => 'irrf'];
                if ($nivel === 'OK') $nivel = 'ALERTA';
            }

            if ($irrf_rub_fer > 0 && $base_irrf_fer > 0) {
                $diff = abs($irrf_rub_fer - $irrf_calc_fer);
                if ($diff > 0.15) {
                    $nota = $diff <= $LIMIAR ? ' (arredondamento)' : '';
                    $alertas_encargos[] = ['txt' => sprintf('IRRF Ferias R$%.2f | calc. R$%.2f | Δ R$%.2f%s', $irrf_rub_fer, $irrf_calc_fer, $diff, $nota), 'tipo' => 'alerta', 'cat' => 'irrf'];
                    if ($nivel === 'OK') $nivel = 'ALERTA';
                }
            } elseif ($irrf_calc_fer > $LIMIAR && $irrf_rub_fer == 0 && $base_irrf_fer > 0) {
                $alertas_encargos[] = ['txt' => sprintf('IRRF Ferias nao lancado | base R$%.2f | devido R$%.2f', $base_irrf_fer, $irrf_calc_fer), 'tipo' => 'alerta', 'cat' => 'irrf'];
                if ($nivel === 'OK') $nivel = 'ALERTA';
            }

            $irrf_rub = r2($irrf_rub_men + $irrf_rub_fer);

            // Horas extras
            foreach ($rp as $r) {
                if (eh_he($r['desc'])) {
                    $pct = extrair_percentual($r['desc'], 0.50);
                    $mult = 1 + $pct;
                    $esperado = r2($r['qtd'] * $valor_hora * $mult);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('HE %d%% — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', (int)($pct * 100), $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                        if ($nivel === 'OK') $nivel = 'ALERTA';
                    }
                }
            }

            // DSR de HE
            foreach ($rp as $r) {
                if (eh_dsr_he($r['desc']) && $dias_uteis_ef > 0) {
                    $total_he = 0.0;
                    foreach ($rp as $x) if (eh_he($x['desc'])) $total_he += $x['valor'];
                    $esperado = r2($total_he / $dias_uteis_ef * $dias_nao_uteis_ef);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('DSR de HE — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                    }
                }
            }

            // Adicional noturno
            foreach ($rp as $r) {
                if (eh_noturno($r['desc'])) {
                    $pct = extrair_percentual($r['desc'], 0.20);
                    $esperado = r2($r['qtd'] * $valor_hora * $pct);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('Adic. Noturno %d%% — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', (int)($pct * 100), $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                        if ($nivel === 'OK') $nivel = 'ALERTA';
                    }
                }
            }

            // DSR adicional noturno
            foreach ($rp as $r) {
                if (eh_dsr_noturno($r['desc']) && $dias_uteis_ef > 0) {
                    $total_an = 0.0;
                    foreach ($rp as $x) if (eh_noturno($x['desc'])) $total_an += $x['valor'];
                    $esperado = r2($total_an / $dias_uteis_ef * $dias_nao_uteis_ef);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('DSR Adic. Noturno — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                    }
                }
            }

            // Faltas
            foreach ($rd as $r) {
                if (eh_falta_horas($r['desc'])) {
                    $hora_norm = $f['horas_mes'] ? r2($f['salario'] / $f['horas_mes']) : 0;
                    $esperado = r2($r['qtd'] * $hora_norm);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('Falta horas — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                        if ($nivel === 'OK') $nivel = 'ALERTA';
                    }
                } elseif (eh_falta_dias($r['desc'])) {
                    $esperado = r2($r['qtd'] * $f['salario'] / 30);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('Falta dias — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                        if ($nivel === 'OK') $nivel = 'ALERTA';
                    }
                }
            }

            // DSR de faltas
            $faltas_desc = array_values(array_filter($rd, fn($r) => eh_falta_horas($r['desc']) || eh_falta_dias($r['desc'])));
            $dsr_falta_desc = array_values(array_filter($rd, fn($r) => eh_dsr_falta($r['desc'])));
            if (count($faltas_desc) > 0) {
                $total_faltas = array_sum(array_column($faltas_desc, 'valor'));
                if (count($dsr_falta_desc) === 0) {
                    $alertas_lanc[] = ['txt' => 'DSR de faltas nao lançado', 'tipo' => 'alerta'];
                } elseif ($dias_uteis_ef > 0) {
                    $esperado = r2($total_faltas / $dias_uteis_ef * $dias_nao_uteis_ef);
                    $total_dsr = array_sum(array_column($dsr_falta_desc, 'valor'));
                    $diff = abs($total_dsr - $esperado);
                    if ($diff > $LIMIAR) {
                        if ($total_dsr > $esperado * 1.5) {
                            $alertas_lanc[] = ['txt' => sprintf('DSR faltas com possivel duplicidade (feriado na semana?) — lançado R$%.2f | esperado R$%.2f', $total_dsr, $esperado), 'tipo' => 'alerta'];
                        } else {
                            $alertas_lanc[] = ['txt' => sprintf('DSR faltas — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', $total_dsr, $esperado, $diff), 'tipo' => 'alerta'];
                        }
                    }
                }
            }

            // Periculosidade
            foreach ($rp as $r) {
                if (eh_periculosidade($r['desc'])) {
                    $pct = extrair_percentual($r['desc'], 0.30);
                    $sal_peric = ($tem_ferias && $dias_ferias > 0) ? r2($sal_ref * $dias_trabalhados / 30) : $sal_ref;
                    $esperado = r2($sal_peric * $pct);
                    $diff = abs($r['valor'] - $esperado);
                    if ($diff > $LIMIAR) {
                        $alertas_lanc[] = ['txt' => sprintf('Periculosidade %d%% — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', (int)($pct * 100), $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                        if ($nivel === 'OK') $nivel = 'ALERTA';
                    }
                }
            }

            // Insalubridade
            foreach ($rp as $r) {
                if (eh_insalubridade($r['desc'])) {
                    $pct = extrair_percentual($r['desc']);
                    $bases_validas = [r2(SAL_MIN_2026 * 0.10), r2(SAL_MIN_2026 * 0.20), r2(SAL_MIN_2026 * 0.40)];
                    if ($pct) {
                        $esperado = r2(SAL_MIN_2026 * $pct);
                        $diff = abs($r['valor'] - $esperado);
                        if ($diff > $LIMIAR) {
                            $alertas_lanc[] = ['txt' => sprintf('Insalubridade %d%% — lançado R$%.2f | calc. R$%.2f | Δ R$%.2f', (int)($pct * 100), $r['valor'], $esperado, $diff), 'tipo' => 'alerta'];
                        }
                    } else {
                        $bate = false;
                        foreach ($bases_validas as $b) if (abs($r['valor'] - $b) <= $LIMIAR) { $bate = true; break; }
                        if (!$bate) {
                            $alertas_lanc[] = ['txt' => sprintf('Insalubridade — base de calculo divergente (R$%.2f) — verificar se CCT usa base diferente do salario minimo', $r['valor']), 'tipo' => 'alerta'];
                        }
                    }
                    if ($nivel === 'OK') $nivel = 'ALERTA';
                }
            }

            // Vale-transporte
            if ($vt_rub > 0 && $f['salario'] > 0) {
                $rubVt = null;
                foreach ($rd as $r) if (eh_vt($r['desc'])) { $rubVt = $r; break; }
                $pctVt = $rubVt ? extrair_percentual($rubVt['desc'], 0.06) : 0.06;
                $lim = r2($f['salario'] * $pctVt);
                if ($vt_rub > $lim + 0.05) {
                    $alertas_lanc[] = ['txt' => sprintf('VT R$%.2f > limite %d%% (R$%.2f)', $vt_rub, (int)($pctVt * 100), $lim), 'tipo' => 'alerta'];
                    if ($nivel === 'OK') $nivel = 'ALERTA';
                }
            }

            // Salário-família
            if ($sal_fam_rub > 0 && $f['num_dep'] > 0) {
                $esperado = r2($f['num_dep'] * SAL_FAMILIA_VALOR);
                $diff = abs($sal_fam_rub - $esperado);
                if ($diff > $LIMIAR) {
                    $alertas_lanc[] = ['txt' => sprintf('Sal. Familia R$%.2f | esperado R$%.2f (%d dep.) | Δ R$%.2f', $sal_fam_rub, $esperado, $f['num_dep'], $diff), 'tipo' => 'alerta'];
                    if ($nivel === 'OK') $nivel = 'ALERTA';
                }
            }

            // Quebra de caixa
            if (cargo_eh_caixa($f['cargo']) && !($tem_ferias && $dias_ferias >= 28)) {
                $hasQuebra = false;
                foreach ($rp as $r) if (eh_quebra_caixa($r['desc'])) { $hasQuebra = true; break; }
                if (!$hasQuebra) {
                    $alertas_lanc[] = ['txt' => 'Cargo de caixa sem quebra de caixa lancada — verificar', 'tipo' => 'alerta'];
                    if ($nivel === 'OK') $nivel = 'ALERTA';
                }
            }

            // Estouro
            foreach ($estouro_rubricas as $r) {
                $alertas_lanc[] = ['txt' => sprintf('Estouro identificado: %s R$%.2f — verificar ocorrencia', $r['desc'], $r['valor']), 'tipo' => 'alerta'];
                if ($nivel === 'OK') $nivel = 'ALERTA';
            }

            // Pensão alimentícia
            foreach ($pensao_rubricas as $r) {
                $alertas_lanc[] = ['txt' => sprintf('Pensão alimenticia: %s R$%.2f — conferir com decisao judicial', $r['desc'], $r['valor']), 'tipo' => 'alerta'];
                if ($nivel === 'OK') $nivel = 'ALERTA';
            }
        }

        $resultado[] = array_merge($f, [
            'inss_calc' => $inss_calc, 'irrf_calc' => $irrf_calc,
            'irrf_calc_men' => $irrf_calc_men, 'irrf_calc_fer' => $irrf_calc_fer,
            'fgts_calc' => !$socio ? $fgts_calc : 0,
            'base_fgts_usado' => !$socio ? $base_fgts : 0,
            'inss_rub' => $inss_rub, 'irrf_rub' => $irrf_rub,
            'cons_rubricas' => $cons_rubricas, 'cons_ferias' => $cons_ferias,
            'alertas_encargos' => $alertas_encargos,
            'alertas_lanc' => $alertas_lanc,
            'nivel' => $nivel, 'demitido' => $demitido,
        ]);
    }

    $ordem = ['CRITICO' => 0, 'ALERTA' => 1, 'INFO' => 2, 'OK' => 3];
    usort($resultado, fn($a, $b) => $ordem[$a['nivel']] <=> $ordem[$b['nivel']]);

    return $resultado;
}
