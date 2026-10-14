<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';
require_once __DIR__ . '/includes/folha_core.php';
require_once __DIR__ . '/includes/folha_parser.php';
require_once __DIR__ . '/includes/folha_analise.php';

$user = exigirLogin();
$csrfToken = gerarCsrfToken();

$erro = null;
$resultado = null;
$comp = null;
$diasUteis = 22;
$diasNaoUteis = 8;

function brl($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada, atualize a página e tente novamente.';
    } elseif (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        $erro = 'Selecione o arquivo .xlsx do extrato do Domínio.';
    } else {
        $diasUteis = (float)($_POST['dias_uteis'] ?? 22);
        $diasNaoUteis = (float)($_POST['dias_nao_uteis'] ?? 8);
        $nomeOriginal = $_FILES['arquivo']['name'];

        if (!preg_match('/\.xlsx$/i', $nomeOriginal)) {
            $erro = 'Envie um arquivo .xlsx (se o Domínio exportou em .xls, abra no Excel e salve como .xlsx primeiro).';
        } else {
            try {
                [$comp, $funcs] = parseDominioTabular($_FILES['arquivo']['tmp_name']);
                if (empty($funcs)) {
                    $erro = 'Não foi possível identificar nenhum funcionário no arquivo. Confira se é o extrato correto do Domínio.';
                } else {
                    $resultado = analisarFolha($funcs, $diasUteis, $diasNaoUteis);
                }
            } catch (Throwable $e) {
                $erro = 'Erro ao processar o arquivo: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conferência de Folha — Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .cf-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
  .cf-badge-critico { background:rgba(239,68,68,.15); color:#f87171; border:1px solid rgba(239,68,68,.4); }
  .cf-badge-alerta { background:rgba(201,162,39,.15); color:var(--dourado); border:1px solid rgba(201,162,39,.4); }
  .cf-badge-ok { background:rgba(34,197,94,.15); color:#4ade80; border:1px solid rgba(34,197,94,.4); }
  .cf-resumo { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
  .cf-resumo .item { flex:1; min-width:120px; background:var(--card); border:1px solid var(--borda); border-radius:8px; padding:14px 16px; }
  .cf-resumo .num { font-size:26px; color:#fff; font-family:Georgia,serif; }
  .cf-resumo .lbl { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,.4); margin-top:2px; }
  .cf-tabela { width:100%; border-collapse:collapse; font-size:13px; }
  .cf-tabela th { text-align:left; padding:8px 10px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:rgba(255,255,255,.4); border-bottom:1px solid var(--borda); }
  .cf-tabela td { padding:8px 10px; border-bottom:1px solid var(--borda); color:rgba(255,255,255,.85); vertical-align:top; }
  .cf-tabela tr:hover td { background:rgba(255,255,255,.02); }
  .cf-alist { margin:0; padding-left:16px; }
  .cf-alist li.critico { color:#f87171; }
  .cf-alist li.alerta { color:var(--dourado-claro); }
  .cf-mono { font-variant-numeric: tabular-nums; }
  .cf-diff-ok { color:#4ade80; }
  .cf-diff-bad { color:#f87171; font-weight:600; }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="container">
  <p class="font-display" style="font-size:24px; color:#fff; margin-bottom:20px;">Conferência de Folha</p>

  <?php if ($erro): ?>
    <p class="erro-msg"><?= htmlspecialchars($erro) ?></p>
  <?php endif; ?>

  <div class="card" style="margin-bottom:24px;">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <div class="campo">
        <label>Extrato mensal do Domínio (.xlsx)</label>
        <input type="file" name="arquivo" accept=".xlsx" required>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div class="campo">
          <label>Dias úteis do mês</label>
          <input type="number" name="dias_uteis" value="<?= htmlspecialchars((string)$diasUteis) ?>" min="0" max="31" required>
        </div>
        <div class="campo">
          <label>Dias não úteis do mês</label>
          <input type="number" name="dias_nao_uteis" value="<?= htmlspecialchars((string)$diasNaoUteis) ?>" min="0" max="31" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Processar conferência</button>
    </form>
  </div>

  <?php if ($resultado !== null): ?>
    <?php
      $total = count($resultado);
      $critico = 0; $alerta = 0; $ok = 0;
      foreach ($resultado as $d) {
          if ($d['demitido']) { $ok++; continue; }
          if ($d['nivel'] === 'CRITICO') $critico++;
          elseif ($d['nivel'] === 'ALERTA') $alerta++;
          else $ok++;
      }
    ?>
    <p class="texto-fraco" style="margin-bottom:12px;">Competência identificada: <strong style="color:#fff;"><?= htmlspecialchars($comp ?: '—') ?></strong></p>

    <div class="cf-resumo">
      <div class="item"><div class="num"><?= $total ?></div><div class="lbl">Funcionários</div></div>
      <div class="item"><div class="num" style="color:#f87171;"><?= $critico ?></div><div class="lbl">Críticos</div></div>
      <div class="item"><div class="num" style="color:var(--dourado);"><?= $alerta ?></div><div class="lbl">Alertas</div></div>
      <div class="item"><div class="num" style="color:#4ade80;"><?= $ok ?></div><div class="lbl">OK</div></div>
    </div>

    <div class="tabs" id="cf-tabs">
      <button class="ativo" data-aba="encargos">Div. Encargos</button>
      <button data-aba="lancamentos">Div. Lançamentos</button>
      <button data-aba="consolidado">Consolidado</button>
      <button data-aba="consignado">Consignado</button>
    </div>

    <div class="card" id="cf-aba-encargos">
      <table class="cf-tabela">
        <thead><tr><th>Funcionário</th><th>Nível</th><th>INSS (lanç. / calc.)</th><th>FGTS (lanç. / calc.)</th><th>IRRF (lanç. / calc.)</th><th>Alertas</th></tr></thead>
        <tbody>
        <?php foreach ($resultado as $d): if ($d['demitido'] || empty($d['alertas_encargos'])) continue; ?>
          <tr>
            <td><?= htmlspecialchars($d['nome']) ?></td>
            <td><span class="cf-badge cf-badge-<?= strtolower($d['nivel']) ?>"><?= $d['nivel'] ?></span></td>
            <td class="cf-mono <?= abs($d['inss_rub'] - $d['inss_calc']) > 0.15 ? 'cf-diff-bad' : 'cf-diff-ok' ?>"><?= brl($d['inss_rub']) ?> / <?= brl($d['inss_calc']) ?></td>
            <td class="cf-mono <?= abs($d['fgts'] - $d['fgts_calc']) > 0.5 ? 'cf-diff-bad' : 'cf-diff-ok' ?>"><?= brl($d['fgts']) ?> / <?= brl($d['fgts_calc']) ?></td>
            <td class="cf-mono <?= abs($d['irrf_rub'] - $d['irrf_calc']) > 0.15 ? 'cf-diff-bad' : 'cf-diff-ok' ?>"><?= brl($d['irrf_rub']) ?> / <?= brl($d['irrf_calc']) ?></td>
            <td><ul class="cf-alist"><?php foreach ($d['alertas_encargos'] as $a): ?><li class="<?= $a['tipo'] ?>"><?= htmlspecialchars($a['txt']) ?></li><?php endforeach; ?></ul></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="cf-aba-lancamentos" style="display:none;">
      <table class="cf-tabela">
        <thead><tr><th>Funcionário</th><th>Situação</th><th>Divergência</th><th>Resolvido</th></tr></thead>
        <tbody>
        <?php foreach ($resultado as $d):
          if ($d['demitido'] || empty($d['alertas_lanc'])) continue;
          if (!in_array($d['nivel'], ['CRITICO', 'ALERTA'], true)) continue;
          foreach ($d['alertas_lanc'] as $a): ?>
          <tr>
            <td><?= htmlspecialchars($d['nome']) ?></td>
            <td><?= htmlspecialchars($d['situacao']) ?></td>
            <td><?= htmlspecialchars($a['txt']) ?></td>
            <td><input type="checkbox"></td>
          </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="cf-aba-consolidado" style="display:none;">
      <table class="cf-tabela">
        <thead><tr><th>Funcionário</th><th>Cargo</th><th>Situação</th><th>INSS calc.</th><th>IRRF calc.</th><th>FGTS calc.</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($resultado as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['nome']) ?></td>
            <td><?= htmlspecialchars($d['cargo']) ?></td>
            <td><?= htmlspecialchars($d['situacao']) ?></td>
            <td class="cf-mono"><?= brl($d['inss_calc']) ?></td>
            <td class="cf-mono"><?= brl($d['irrf_calc']) ?></td>
            <td class="cf-mono"><?= brl($d['fgts_calc']) ?></td>
            <td><span class="cf-badge cf-badge-<?= $d['demitido'] ? 'ok' : strtolower($d['nivel']) ?>"><?= $d['demitido'] ? 'DESLIGADO' : $d['nivel'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="cf-aba-consignado" style="display:none;">
      <table class="cf-tabela">
        <thead><tr><th>Funcionário</th><th>Situação</th><th>Rubricas</th><th>Total</th></tr></thead>
        <tbody>
        <?php
          $totalGeralConsig = 0;
          foreach ($resultado as $d):
            $todas = array_merge(
                array_map(fn($r) => [$r, ''], $d['cons_rubricas']),
                array_map(fn($r) => [$r, '[Férias] '], $d['cons_ferias'])
            );
            if (empty($todas)) continue;
            $totalC = 0;
            foreach ($todas as [$r, ]) $totalC += $r['valor'];
            $totalC = r2($totalC);
            $totalGeralConsig = r2($totalGeralConsig + $totalC);
        ?>
          <tr>
            <td><?= htmlspecialchars($d['nome']) ?></td>
            <td><?= htmlspecialchars($d['situacao']) ?></td>
            <td>
              <?php foreach ($todas as [$r, $tag]): ?>
                <div style="font-size:11px; color:rgba(255,255,255,.5);"><?= htmlspecialchars($tag) ?><?= htmlspecialchars($r['desc']) ?> — <span class="cf-mono"><?= brl($r['valor']) ?></span></div>
              <?php endforeach; ?>
            </td>
            <td class="cf-mono"><?= brl($totalC) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($totalGeralConsig > 0): ?>
          <tr style="border-top:2px solid var(--dourado);">
            <td colspan="3" style="font-weight:700; color:var(--dourado); text-transform:uppercase; font-size:11px;">Total Geral de Consignados</td>
            <td class="cf-mono" style="font-weight:700; color:var(--dourado);"><?= brl($totalGeralConsig) ?></td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>

<script>
document.querySelectorAll('#cf-tabs button').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#cf-tabs button').forEach((b) => b.classList.remove('ativo'));
    btn.classList.add('ativo');
    ['encargos', 'lancamentos', 'consolidado', 'consignado'].forEach((aba) => {
      document.getElementById('cf-aba-' + aba).style.display = (aba === btn.dataset.aba) ? 'block' : 'none';
    });
  });
});
</script>
</body>
</html>
