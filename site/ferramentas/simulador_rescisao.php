<?php
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simulador de Rescisão · DP 2026</title>
<style>
  :root {
    --ouro:#C9A84C; --ouro2:#E8C96A; --ouro-bg:#1e1a0e;
    --preto:#0d0d0d; --card:#161616; --card2:#1e1e1e;
    --borda:#2e2a1e; --borda2:#3d3720;
    --texto:#e8e0cc; --muted:#8a7f68;
    --verde:#4ade80; --verm:#f87171; --info:#60a5fa;
    --radius:8px; --radius-lg:12px;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--preto);color:var(--texto);font-family:'Segoe UI',system-ui,sans-serif;font-size:14px;min-height:100vh;}

  /* Print styles */
  @media print {
    body{background:#fff;color:#000;}
    header,footer,.no-print{display:none!important;}
    .print-area{display:block!important;}
    .rcard{border:1px solid #ccc;background:#fff;color:#000;padding:10px;}
    .pos{color:#166534;} .neg{color:#991b1b;} .nt{color:#666;}
    .tv{color:#b8860b;} .rheader{color:#b8860b;}
    .tbadge{background:#f5f0e0;color:#b8860b;border:1px solid #b8860b;}
    .mcard{background:#f9f9f9;border:1px solid #ddd;}
    .mval{color:#b8860b;}
    .divider{border-color:#ddd;}
    .rrow{border-color:#eee;}
    .print-header{display:block!important;text-align:center;margin-bottom:16px;border-bottom:2px solid #b8860b;padding-bottom:10px;}
    .print-footer{display:block!important;text-align:center;margin-top:16px;font-size:11px;color:#666;border-top:1px solid #ddd;padding-top:8px;}
    .ssub{color:#b8860b;}
    .trow{border-top:2px solid #b8860b;}
    .mgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
  }

  header{background:linear-gradient(135deg,#0d0d0d 0%,#1a1400 100%);border-bottom:1px solid var(--ouro);padding:18px 32px;display:flex;align-items:center;gap:16px;}
  .logo{font-size:22px;font-weight:700;color:var(--ouro);letter-spacing:.05em;}
  .logo span{color:var(--texto);font-weight:300;}
  .tagline{font-size:11px;color:var(--muted);margin-top:2px;letter-spacing:.08em;text-transform:uppercase;}
  .main{max-width:820px;margin:0 auto;padding:28px 20px 60px;}
  .stitle{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ouro);border-bottom:1px solid var(--borda2);padding-bottom:6px;margin:24px 0 14px;}
  .stitle:first-of-type{margin-top:0;}
  .g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  @media(max-width:600px){.g2{grid-template-columns:1fr;}}
  .field{display:flex;flex-direction:column;gap:5px;}
  label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;}
  input,select{width:100%;background:var(--card2);border:1px solid var(--borda);border-radius:var(--radius);color:var(--texto);font-size:13px;padding:9px 12px;transition:border-color .15s;}
  input:focus,select:focus{outline:none;border-color:var(--ouro);}
  select option{background:#fafafa;}
  input::placeholder{color:var(--muted);}
  .rg{display:flex;gap:8px;flex-wrap:wrap;}
  .rb{flex:1;min-width:80px;padding:8px 10px;border:1px solid var(--borda);border-radius:var(--radius);font-size:12px;cursor:pointer;text-align:center;background:var(--card2);color:var(--muted);transition:all .15s;}
  .rb:hover{border-color:var(--ouro);color:var(--ouro);}
  .rb.active{background:var(--ouro-bg);border-color:var(--ouro);color:var(--ouro);font-weight:600;}
  .bw{font-size:10px;padding:2px 8px;border-radius:4px;background:rgba(201,168,76,.15);color:var(--ouro);margin-left:6px;font-weight:600;vertical-align:middle;}
  .bg{font-size:10px;padding:2px 8px;border-radius:4px;background:rgba(74,222,128,.12);color:var(--verde);margin-left:6px;font-weight:600;vertical-align:middle;}
  .irow{display:grid;grid-template-columns:1fr 150px 36px;gap:8px;align-items:center;margin-bottom:8px;}
  .clabels{display:grid;grid-template-columns:1fr 150px 36px;gap:8px;margin-bottom:5px;}
  .clabels span{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}
  .btn-add{padding:8px 14px;border:1px dashed var(--borda2);border-radius:var(--radius);background:transparent;cursor:pointer;font-size:12px;color:var(--muted);transition:all .15s;}
  .btn-add:hover{border-color:var(--ouro);color:var(--ouro);}
  .btn-rem{width:36px;height:36px;border:1px solid var(--borda);border-radius:var(--radius);background:transparent;cursor:pointer;font-size:18px;color:var(--verm);display:flex;align-items:center;justify-content:center;transition:background .15s;}
  .btn-rem:hover{background:rgba(248,113,113,.1);}
  .fbox{background:var(--card);border:1px solid var(--borda);border-radius:var(--radius-lg);padding:16px;margin-top:8px;}
  .ilabel{font-size:11px;color:var(--muted);line-height:1.5;margin-bottom:10px;}
  .btn-calc{width:100%;padding:13px;background:linear-gradient(135deg,var(--ouro) 0%,#a07820 100%);border:none;border-radius:var(--radius);font-size:14px;font-weight:700;letter-spacing:.06em;cursor:pointer;color:#0d0d0d;margin-top:24px;text-transform:uppercase;transition:opacity .15s;}
  .btn-calc:hover{opacity:.88;}
  .rcard{background:var(--card);border:1px solid var(--borda2);border-radius:var(--radius-lg);padding:22px;margin-top:24px;}
  .rheader{font-size:16px;font-weight:600;color:var(--ouro);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--borda2);}
  .tbadge{display:inline-block;font-size:10px;padding:3px 10px;border-radius:4px;margin-bottom:12px;background:rgba(201,168,76,.15);color:var(--ouro);text-transform:uppercase;letter-spacing:.07em;font-weight:600;}
  .mgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
  .mcard{background:var(--card2);border:1px solid var(--borda);border-radius:var(--radius);padding:10px 12px;}
  .mlabel{font-size:10px;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;}
  .mval{font-size:15px;font-weight:600;color:var(--ouro2);}
  .ssub{font-size:10px;font-weight:600;color:var(--ouro);margin-bottom:6px;text-transform:uppercase;letter-spacing:.08em;}
  .rrow{display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:13px;}
  .rrow:not(:last-child){border-bottom:1px solid var(--borda);}
  .rl{color:var(--muted);flex:1;}
  .rv{font-weight:600;min-width:120px;text-align:right;}
  .pos{color:var(--verde);} .neg{color:var(--verm);} .nt{color:var(--muted);}
  .divider{border:none;border-top:1px solid var(--borda);margin:12px 0;}
  .trow{display:flex;justify-content:space-between;align-items:center;padding:12px 0 0;margin-top:10px;border-top:2px solid var(--ouro);}
  .tl{font-size:15px;font-weight:600;color:var(--texto);}
  .tv{font-size:20px;font-weight:700;color:var(--ouro);}
  .ibox{background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);border-radius:var(--radius);padding:10px 12px;font-size:12px;color:var(--info);margin-top:10px;line-height:1.5;}
  .anote{font-size:11px;color:var(--muted);margin-top:12px;line-height:1.5;}
  .btn-pdf{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;background:linear-gradient(135deg,#1a1400 0%,#2a2000 100%);border:1px solid var(--ouro);border-radius:var(--radius);font-size:14px;font-weight:600;cursor:pointer;margin-top:14px;color:var(--ouro);letter-spacing:.04em;transition:all .15s;}
  .btn-pdf:hover{background:rgba(201,168,76,.1);}
  footer{text-align:center;font-size:11px;color:var(--muted);padding:20px;border-top:1px solid var(--borda);margin-top:40px;}
  .print-header,.print-footer{display:none;}
</style>
</head>
<body>
<header>
  <div style="display:flex;align-items:center;gap:20px;">
    <img src="data:image/png;base64,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" alt="Pretorian Contabilidade" style="height:52px;display:block;">
    <div style="border-left:1px solid #3d3720;padding-left:20px;">
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;">Simulador de Rescisão Trabalhista</div>
      <div style="font-size:10px;color:var(--ouro);margin-top:3px;letter-spacing:.06em;">INSS &amp; IRRF · Tabelas 2026</div>
    </div>
  </div>
</header>

<div class="main">
  <div class="stitle">Dados do colaborador</div>
  <div class="g2">
    <div class="field"><label>Nome completo</label><input type="text" id="nome" placeholder="Ex: Maria da Silva"></div>
    <div class="field"><label>Cargo</label><input type="text" id="cargo" placeholder="Ex: Tecnica de Enfermagem"></div>
  </div>
  <div class="g2" style="margin-top:14px">
    <div class="field"><label>Data de admissao</label><input type="date" id="admissao"></div>
    <div class="field"><label>Data de rescisao</label><input type="date" id="rescisao"></div>
  </div>
  <div class="g2" style="margin-top:14px">
    <div class="field"><label>Salario bruto (R$)</label><input type="number" id="salario" placeholder="0,00" min="0" step="0.01"></div>
    <div class="field"><label>Jornada semanal</label>
      <select id="jornada">
        <option value="44">44h - CLT padrao</option>
        <option value="40">40h</option><option value="36">36h</option>
        <option value="30">30h</option><option value="20">20h - meio periodo</option>
      </select>
    </div>
  </div>

  <div class="stitle">Tipo de rescisao</div>
  <div class="rg" id="rg-tipo">
    <div class="rb active" data-val="sem_justa">Sem justa causa</div>
    <div class="rb" data-val="com_justa">Com justa causa</div>
    <div class="rb" data-val="pedido">Pedido de demissao</div>
    <div class="rb" data-val="acordo">Acordo (484-A)</div>
  </div>

  <div class="stitle">Adicionais habituais <span class="bw">INSS · IRRF · Medias</span></div>
  <p class="ilabel">Insalubridade, periculosidade, gratificacoes, adicional noturno. Integram a remuneracao base para medias de ferias, 13o e FGTS.</p>
  <div class="clabels"><span>Descricao</span><span>Valor mensal (R$)</span><span></span></div>
  <div id="lst-adic"></div>
  <button class="btn-add" id="badd-adic">+ Adicionar adicional</button>

  <div class="stitle" style="margin-top:20px">Premiacoes / bonus <span class="bg">Sem incidencias</span></div>
  <p class="ilabel">Premiacoes esporadicas, PLR. Sem reflexos em INSS, IRRF ou medias.</p>
  <div class="clabels"><span>Descricao</span><span>Valor (R$)</span><span></span></div>
  <div id="lst-prem"></div>
  <button class="btn-add" id="badd-prem">+ Adicionar premiacao</button>

  <div class="stitle">Base FGTS - rescisao</div>
  <div class="fbox">
    <p class="ilabel">Informe o saldo acumulado para calcular a multa. Se nao informado, a multa sera omitida.</p>
    <div class="g2">
      <div class="field"><label>Saldo FGTS acumulado - fins rescisarios (R$)</label><input type="number" id="fgts-acum" placeholder="Deixe em branco para omitir" min="0" step="0.01"></div>
      <div class="field"><label>FGTS do mes da rescisao adicional (R$)</label><input type="number" id="fgts-mes" placeholder="0,00" min="0" step="0.01"></div>
    </div>
  </div>

  <div class="stitle">Verbas e beneficios</div>
  <div class="g2">
    <div class="field"><label>Ferias vencidas?</label>
      <div class="rg" id="rg-fv">
        <div class="rb active" data-val="nao">Nao</div>
        <div class="rb" data-val="sim">Sim</div>
      </div>
    </div>
    <div class="field"><label>Meses no periodo aquisitivo (ferias prop.)</label>
      <input type="number" id="mfp" placeholder="Calculado automatico" min="0" max="11">
    </div>
  </div>
  <div class="g2" style="margin-top:14px">
    <div class="field"><label>Aviso previo</label>
      <div class="rg" id="rg-av">
        <div class="rb active" data-val="trabalhado">Trabalhado</div>
        <div class="rb" data-val="indenizado">Indenizado</div>
        <div class="rb" data-val="dispensado">Dispensado</div>
      </div>
    </div>

  </div>
  <div class="g2" style="margin-top:14px">
    <div class="field"><label>Desconto VR/VA (R$)</label><input type="number" id="dvr" placeholder="0,00" min="0" step="0.01"></div>
    <div class="field"><label>Outros descontos (R$)</label><input type="number" id="odet" placeholder="0,00" min="0" step="0.01"></div>
  </div>

  <button class="btn-calc" id="btn-calc">&#9654; Calcular Rescisao</button>
  <div id="res"></div>
</div>

<footer class="no-print">Pretorian Gestão de Alta Performance e Contabilidade &nbsp;·&nbsp; Simulador de Rescisão Trabalhista 2026 &nbsp;·&nbsp; Template criado por Vinícius Marcello Batista</footer>

<script>
(function(){
  var tipo='sem_justa',fv='nao',av='trabalhado';

  function radio(id,cb){
    var bs=document.querySelectorAll('#'+id+' .rb');
    bs.forEach(function(b){b.addEventListener('click',function(){bs.forEach(function(x){x.classList.remove('active');});b.classList.add('active');cb(b.dataset.val);});});
  }
  radio('rg-tipo',function(v){tipo=v;});
  radio('rg-fv',function(v){fv=v;});
  radio('rg-av',function(v){av=v;});

  function addRow(lid,ph){
    var r=document.createElement('div');r.className='irow';
    var di=document.createElement('input');di.type='text';di.placeholder=ph;di.className='di';
    var vi=document.createElement('input');vi.type='number';vi.placeholder='0,00';vi.min='0';vi.step='0.01';vi.className='vi';
    var br=document.createElement('button');br.className='btn-rem';br.innerHTML='&times;';
    br.addEventListener('click',function(){r.parentNode.removeChild(r);});
    r.appendChild(di);r.appendChild(vi);r.appendChild(br);
    document.getElementById(lid).appendChild(r);
  }
  document.getElementById('badd-adic').addEventListener('click',function(){addRow('lst-adic','Ex: Insalubridade 20%');});
  document.getElementById('badd-prem').addEventListener('click',function(){addRow('lst-prem','Ex: Bonus de resultado');});

  function collect(lid){
    var out=[];
    document.querySelectorAll('#'+lid+' .irow').forEach(function(r){
      var d=r.querySelector('.di').value.trim()||'Item';
      var v=parseFloat(r.querySelector('.vi').value)||0;
      if(v>0)out.push({d:d,v:v});
    });
    return out;
  }

  
  // ── INSS 2026 (Portaria MPS/MF nº 13/2026) ──────────────────────────────
  function calcINSS(base){
    var f=[{t:1621,a:.075},{t:2902.84,a:.09},{t:4354.27,a:.12},{t:8475.55,a:.14}];
    var r=0,p=0;
    for(var i=0;i<f.length;i++){if(base<=p)break;r+=(Math.min(base,f[i].t)-p)*f[i].a;p=f[i].t;}
    return Math.min(r,988.09);
  }

  // ── IRRF 2026 (Lei nº 15.270/2025) ───────────────────────────────────────
  function calcIRRF(bruto,inss_pago){
    var ded=Math.max(607.20,inss_pago);
    var base=Math.max(0,bruto-ded);
    var ir=0;
    if(base<=2259.20)ir=0;
    else if(base<=2826.65)ir=base*.075-169.44;
    else if(base<=3751.05)ir=base*.15-381.44;
    else if(base<=4664.68)ir=base*.225-662.77;
    else ir=base*.275-896;
    ir=Math.max(0,ir);
    if(bruto<=5000)ir=0;
    else if(bruto<=7350)ir=Math.max(0,ir-Math.max(0,978.62-.133145*bruto));
    return Math.max(0,ir);
  }

  function M(v){return 'R$ '+Math.abs(v).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');}
  function FD(s){if(!s)return'';var p=s.split('-');return p[2]+'/'+p[1]+'/'+p[0];}

  // ── Gerador de PDF ───────────────────────────────────────────────────────
  function gerarPDF(payload,nome,hoje){
    var p=payload;
    var ref_inss=[['Faixa Salarial','Aliquota','Desc. Max.'],['Ate R$ 1.621,00','7,5%','R$ 121,58'],['R$ 1.621,01 a R$ 2.902,84','9,0%','R$ 237,23'],['R$ 2.902,85 a R$ 4.354,27','12%','R$ 410,76'],['R$ 4.354,28 a R$ 8.475,55','14%','R$ 988,09']];
    var ref_irrf=[['Base de Calculo','Aliquota','Parc. Deduzir'],['Ate R$ 2.259,20','Isento','-'],['R$ 2.259,21 a R$ 2.826,65','7,5%','R$ 169,44'],['R$ 2.826,66 a R$ 3.751,05','15%','R$ 381,44'],['R$ 3.751,06 a R$ 4.664,68','22,5%','R$ 662,77'],['Acima de R$ 4.664,68','27,5%','R$ 896,00']];

    function tRef(titulo,linhas){
      var rows=linhas.map(function(r,i){return '<tr>'+r.map(function(c){return i===0?'<th style="background:#1a1a1a;color:#C9A84C;padding:5px 8px;border:1px solid #ccc;font-size:11px;">'+c+'</th>':'<td style="padding:5px 8px;border:1px solid #ddd;font-size:11px;background:'+(i%2?'#f4f4f4':'#fafafa')+';color:#1a1a1a;">'+c+'</td>';}).join('')+'</tr>';}).join('');
      return '<div style="margin-bottom:14px;"><div style="background:#1a1a1a;border-bottom:1px solid #C9A84C;padding:6px 8px;font-size:11px;font-weight:700;color:#C9A84C;letter-spacing:.05em;">'+titulo+'</div><table style="width:100%;border-collapse:collapse;">'+rows+'</table></div>';
    }
    function pRow(desc,val,cor,bg){
      return '<tr><td style="padding:5px 8px;border:1px solid #ddd;font-size:12px;color:#1a1a1a;background:'+(bg||'#fafafa')+';">'+desc+'</td>'
        +'<td style="padding:5px 8px;border:1px solid #ddd;font-size:12px;font-weight:600;color:'+(cor||'#166534')+';text-align:right;background:'+(bg||'#fafafa')+';">'+M(val)+'</td></tr>';
    }
    function totRow(desc,val,cor,bg){
      return '<tr><td style="padding:7px 8px;font-size:13px;font-weight:700;color:#1a1a1a;background:'+(bg||'#fff8e8')+';border-top:2px solid #C9A84C;">'+desc+'</td>'
        +'<td style="padding:7px 8px;font-size:13px;font-weight:700;color:'+(cor||'#166534')+';text-align:right;background:'+(bg||'#fff8e8')+';border-top:2px solid #C9A84C;">'+M(val)+'</td></tr>';
    }

    // ── Proventos ─────────────────────────────────────────────────────────
    var linhasP='';
    linhasP+=pRow(p.saldoDesc,p.sS,null,'#fafafa');
    if(p.fVenc>0)linhasP+=pRow('Ferias vencidas + 1/3',p.fVenc,null,'#f4f4f4');
    if(p.tipo!=='com_justa'){
      linhasP+=pRow('Ferias proporcionais ('+p.mfp+'/12)',p.fProp,null,'#fafafa');
      linhasP+=pRow('1/3 constitucional s/ ferias prop.',p.t3,null,'#f4f4f4');
      linhasP+=pRow('13° salario proporcional ('+p.mRes+'/12)',p.d13,null,'#fafafa');
    }
    if(p.indAv>0)linhasP+=pRow('Aviso previo indenizado'+(p.tipo==='acordo'?' (50%)':''),p.indAv,null,'#f4f4f4');
    p.prems.forEach(function(x,i){linhasP+=pRow(x.d+' [premiacao]',x.v,'#b8860b',i%2?'#fafafa':'#f4f4f4');});
    linhasP+=totRow('Total Proventos',p.tProv,'#166534','#f0faf4');

    // ── Descontos ─────────────────────────────────────────────────────────
    var linhasD='';
    linhasD+=pRow('INSS Mensal 2026  (base: saldo sal. + adicionais)',p.vINSS_mensal,'#991b1b','#fafafa');
    var irfMD='IRRF Mensal 2026'+(p.vIRRF_mensal===0?' — Isento':' (Ded. '+(p.usouSimpl_mensal?'Simpl. R$607,20':'Legal INSS R$'+p.vINSS_mensal.toFixed(2).replace('.',','))+')');;
    linhasD+=pRow(irfMD,p.vIRRF_mensal,p.vIRRF_mensal>0?'#991b1b':'#555','#f4f4f4');
    if(p.tipo!=='com_justa'){
      linhasD+=pRow('INSS 13° Salario 2026  (base: 13° proporcional)',p.vINSS_13,'#991b1b','#fafafa');
      var irf13D='IRRF 13° Salario 2026'+(p.vIRRF_13===0?' — Isento':' (Ded. '+(p.usouSimpl_13?'Simpl. R$607,20':'Legal INSS R$'+p.vINSS_13.toFixed(2).replace('.',','))+')');;
      linhasD+=pRow(irf13D,p.vIRRF_13,p.vIRRF_13>0?'#991b1b':'#555','#f4f4f4');
    }
    if(p.descontoPedidoAviso>0)linhasD+=pRow('Desconto aviso previo nao cumprido (art. 487 §2° CLT)',p.descontoPedidoAviso,'#991b1b','#fafafa');
    if(p.dvr>0) linhasD+=pRow('VR/VA',p.dvr,'#991b1b','#fafafa');
    if(p.odet>0)linhasD+=pRow('Outros descontos',p.odet,'#991b1b','#f4f4f4');
    linhasD+=totRow('Total Descontos',p.tDesc,'#991b1b','#fff0f0');

    // ── TOTAL LÍQUIDO (proventos - descontos, SEM multa) ──────────────────
    var totalLiqHTML='<div style="background:#fff8e8;border:2px solid #C9A84C;border-radius:4px;padding:14px 20px;margin-top:14px;display:flex;justify-content:space-between;align-items:center;">'
      +'<span style="font-size:15px;font-weight:700;color:#1a1a1a;font-family:Georgia,serif;">TOTAL LÍQUIDO (Proventos - Descontos)</span>'
      +'<span style="font-size:20px;font-weight:700;color:#C9A84C;font-family:Georgia,serif;">'+M(p.tLiq)+'</span></div>';

    // ── FGTS + indicativo final ───────────────────────────────────────────
    var fgtsHTML='';
    if(p.fgtsOk&&(p.tipo==='sem_justa'||p.tipo==='acordo')){
      fgtsHTML='<div style="margin-top:16px;">'
        +'<div style="background:#1a1a1a;border-bottom:1px solid #C9A84C;padding:6px 8px;font-size:11px;font-weight:700;color:#C9A84C;letter-spacing:.05em;">FGTS — MULTA RESCISÓRIA</div>'
        +'<table style="width:100%;border-collapse:collapse;">'
        +pRow('Base FGTS Fins Rescisarios (saldo acumulado)',p.fgtsAcum,'#555','#fafafa')
        +pRow('FGTS Mensal (8% s/ saldo sal. + adicionais)',p.fgtsMensal,'#555','#f4f4f4')
        +(p.tipo!=='com_justa'?pRow('FGTS 13° Salario (8% s/ 13° proporcional)',p.fgts13,'#555','#fafafa'):'')
        +pRow('Base Total FGTS (Fins Resc. + Mensal + 13°)',p.baseFgtsTotal,'#555','#f4f4f4')
        +totRow('Multa FGTS ('+(p.pct*100).toFixed(0)+'%) — '+p.tLabel,p.multa,'#166534','#f0faf4')
        +'</table>'
        // Indicativo final com total líquido + multa
        +'<table style="width:100%;border-collapse:collapse;margin-top:12px;border:1.5px solid #C9A84C;border-radius:4px;">'
        +'<tr><td style="padding:9px 16px;font-size:13px;font-weight:600;color:#1a1a1a;background:#fafafa;border-bottom:1px solid #e5e5e5;">Total Líquido de Rescisão</td>'
        +'<td style="padding:9px 16px;font-size:14px;font-weight:700;color:#1a1a1a;text-align:right;background:#fafafa;border-bottom:1px solid #e5e5e5;">'+M(p.tLiq)+'</td></tr>'
        +'<tr><td style="padding:9px 16px;font-size:13px;font-weight:600;color:#166534;background:#f0faf4;">Valor da Guia de FGTS Rescisório (Multa + FGTS Mensal + FGTS 13°)</td>'
        +'<td style="padding:9px 16px;font-size:14px;font-weight:700;color:#166534;text-align:right;background:#f0faf4;">'+M(p.multa+p.fgtsMensal+p.fgts13)+'</td></tr>'
        +'</table>'
        +'</div>';
    }

    var logoSrc=document.querySelector('header img').src;
    var win=window.open('','_blank','width=900,height=750');
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Rescisao — '+nome+'</title>'
      +'<style>'
      +'body{background:#fff;color:#1a1a1a;font-family:Georgia,"Times New Roman",serif;padding:28px;margin:0;font-size:13px;}'
      // Forçar quebra de página antes das tabelas de referência
      +'.page-break{page-break-before:always;break-before:page;padding-top:24px;}'
      +'@media print{'
      +'  body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
      +'  .no-print{display:none!important;}'
      +'  .page-break{page-break-before:always;break-before:page;padding-top:24px;}'
      +'}'
      +'</style></head><body>'

      // ── CABEÇALHO (preto/dourado) ───────────────────────────────────────
      +'<div style="background:#0d0d0d;border-bottom:3px solid #C9A84C;padding:18px 24px 16px;margin-bottom:20px;display:flex;align-items:center;gap:24px;">'
      +'<img src="'+logoSrc+'" style="height:48px;">'
      +'<div style="border-left:1px solid #3d3720;padding-left:20px;flex:1;">'
      +'<div style="font-size:18px;font-weight:700;color:#C9A84C;letter-spacing:.06em;font-family:Georgia,serif;">SIMULAÇÃO DE RESCISÃO TRABALHISTA</div>'
      +'<div style="font-size:11px;color:#8a7f68;margin-top:5px;letter-spacing:.04em;">'+p.tLabel+' &nbsp;·&nbsp; Emitido em: '+hoje+' &nbsp;·&nbsp; Tabelas INSS/IRRF 2026</div>'
      +'</div></div>'

      // ── DADOS COLABORADOR ───────────────────────────────────────────────
      +'<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">'
      +'<tr><td style="padding:5px 8px;font-size:11px;color:#555;background:#f4f4f4;border:1px solid #ddd;width:15%;">Nome</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#f4f4f4;border:1px solid #ddd;width:35%;">'+nome+'</td><td style="padding:5px 8px;font-size:11px;color:#555;background:#fafafa;border:1px solid #ddd;width:15%;">Cargo</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#fafafa;border:1px solid #ddd;width:35%;">'+p.cargo+'</td></tr>'
      +'<tr><td style="padding:5px 8px;font-size:11px;color:#555;background:#fafafa;border:1px solid #ddd;">Admissao</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#fafafa;border:1px solid #ddd;">'+FD(p.aStr)+'</td><td style="padding:5px 8px;font-size:11px;color:#555;background:#f4f4f4;border:1px solid #ddd;">Rescisao</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#f4f4f4;border:1px solid #ddd;">'+FD(p.rStr)+'</td></tr>'
      +'<tr><td style="padding:5px 8px;font-size:11px;color:#555;background:#f4f4f4;border:1px solid #ddd;">Salario Bruto</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#f4f4f4;border:1px solid #ddd;">'+M(p.sal)+'</td><td style="padding:5px 8px;font-size:11px;color:#555;background:#fafafa;border:1px solid #ddd;">Rem. Habitual</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#fafafa;border:1px solid #ddd;">'+M(p.remH)+'</td></tr>'
      +'<tr><td style="padding:5px 8px;font-size:11px;color:#555;background:#fafafa;border:1px solid #ddd;">Tempo Servico</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#fafafa;border:1px solid #ddd;">'+(p.anos>0?p.anos+'a ':'')+p.meses%12+'m</td><td style="padding:5px 8px;font-size:11px;color:#555;background:#f4f4f4;border:1px solid #ddd;">Aviso Previo</td><td style="padding:5px 8px;font-size:12px;font-weight:600;background:#f4f4f4;border:1px solid #ddd;">'+p.diasAv+' dias</td></tr>'
      +'</table>'

      // ── PROVENTOS ───────────────────────────────────────────────────────
      +'<div style="background:#1a1a1a;border-bottom:1px solid #C9A84C;padding:6px 8px;font-size:11px;font-weight:700;color:#C9A84C;letter-spacing:.07em;">PROVENTOS</div>'
      +'<table style="width:100%;border-collapse:collapse;margin-bottom:14px;"><tbody>'+linhasP+'</tbody></table>'

      // ── DESCONTOS ───────────────────────────────────────────────────────
      +'<div style="background:#1a1a1a;border-bottom:1px solid #C9A84C;padding:6px 8px;font-size:11px;font-weight:700;color:#C9A84C;letter-spacing:.07em;">DESCONTOS</div>'
      +'<table style="width:100%;border-collapse:collapse;margin-bottom:0;"><tbody>'+linhasD+'</tbody></table>'

      // ── TOTAL LÍQUIDO (proventos - descontos, sem multa) ─────────────────
      +totalLiqHTML

      // ── FGTS + indicativo ────────────────────────────────────────────────
      +fgtsHTML

      // ── RODAPÉ da página 1 ───────────────────────────────────────────────
      +'<div style="text-align:center;font-size:10px;color:#999;margin-top:18px;padding-top:8px;border-top:1px solid #e5e5e5;font-family:Arial,sans-serif;">'
      +'Documento de SIMULAÇÃO — tabelas de referência na próxima página</div>'

      // ── QUEBRA DE PÁGINA FORÇADA ─────────────────────────────────────────
      +'<div class="page-break">'

      // ── CABEÇALHO pág 2 (compacto) ──────────────────────────────────────
      +'<div style="background:#0d0d0d;border-bottom:2px solid #C9A84C;padding:10px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;">'
      +'<img src="'+logoSrc+'" style="height:34px;">'
      +'<div style="border-left:1px solid #3d3720;padding-left:16px;">'
      +'<div style="font-size:13px;font-weight:700;color:#C9A84C;font-family:Georgia,serif;">TABELAS DE REFERÊNCIA 2026</div>'
      +'<div style="font-size:10px;color:#8a7f68;margin-top:2px;">'+nome+(p.cargo?' · '+p.cargo:'')+' &nbsp;·&nbsp; '+hoje+'</div>'
      +'</div></div>'

      // ── TABELAS ──────────────────────────────────────────────────────────
      +tRef('INSS 2026 — Portaria MPS/MF n. 13/2026',ref_inss)
      +tRef('IRRF 2026 — Lei n. 15.270/2025 | Isencao total ate R$5.000 | Reducao gradual ate R$7.350',ref_irrf)

      // ── RODAPÉ da página 2 ───────────────────────────────────────────────
      +'<div style="text-align:center;font-size:10px;color:#555;margin-top:20px;padding-top:10px;border-top:1px solid #ddd;font-family:Arial,sans-serif;line-height:1.7;">'
      +'Documento de SIMULAÇÃO para fins informativos. Para homologação oficial da rescisão consulte o profissional de DP responsável.<br>'
      +'Template criado por Vinícius Marcello Batista. A reprodução para fins divergentes é proibida sem autorização.'
      +'</div>'

      +'</div>'  // fecha .page-break
      +'<script>window.onload=function(){window.print();}<\/script>'
      +'</body></html>');
    win.document.close();
  }

  document.getElementById('btn-calc').addEventListener('click',function(){
    var aStr=document.getElementById('admissao').value;
    var rStr=document.getElementById('rescisao').value;
    var sal=parseFloat(document.getElementById('salario').value)||0;
    var nome=document.getElementById('nome').value||'Colaborador';
    var cargo=document.getElementById('cargo').value||'';
    var dvr=parseFloat(document.getElementById('dvr').value)||0;
    var odet=parseFloat(document.getElementById('odet').value)||0;
    var mfpRaw=document.getElementById('mfp').value.trim();
    var fgtsAcumRaw=document.getElementById('fgts-acum').value.trim();
    var fgtsOk=(fgtsAcumRaw!=='');
    var fgtsAcum=fgtsOk?parseFloat(fgtsAcumRaw)||0:0;
    if(!aStr||!rStr||sal<=0){alert('Preencha admissao, rescisao e salario.');return;}

    var adm=new Date(aStr+'T00:00:00'),res=new Date(rStr+'T00:00:00');
    var adics=collect('lst-adic'),prems=collect('lst-prem');
    var tAdic=adics.reduce(function(s,i){return s+i.v;},0);
    var tPrem=prems.reduce(function(s,i){return s+i.v;},0);
    var remH=sal+tAdic;

    var dias=Math.floor((res-adm)/864e5);
    var anos=Math.floor(dias/365),meses=Math.floor(dias/30);
    var dRes=res.getDate(),mRes=res.getMonth()+1;
    var diasAv=Math.min(30+(anos>1?Math.min(anos-1,20)*3:0),90);

    var mfp;
    if(mfpRaw!==''&&!isNaN(parseInt(mfpRaw)))mfp=Math.min(parseInt(mfpRaw),11);
    else{var ua=new Date(adm);ua.setFullYear(ua.getFullYear()+Math.floor(dias/365));mfp=Math.min(Math.floor((res-ua)/(864e5*30)),11);}

    // ── Proventos por tipo de rescisão ──────────────────────────────────────
    var sS=(remH/30)*dRes;
    var saldoDesc='Saldo de salario'+(tAdic>0?' + adicionais':'')+' ('+dRes+' dias)';
    var avVal=(remH/30)*diasAv;

    // COM JUSTA CAUSA (art. 482 CLT): só saldo + férias vencidas
    // SEM: férias prop., 1/3 prop., 13º prop., aviso, multa FGTS
    var fVenc=fv==='sim'?remH*(4/3):0;
    var fProp = tipo==='com_justa' ? 0 : (remH/12)*mfp;
    var t3    = tipo==='com_justa' ? 0 : fProp/3;
    var d13   = tipo==='com_justa' ? 0 : (remH/12)*mRes;

    // Aviso prévio indenizado
    // PEDIDO DE DEMISSÃO: se aviso indenizado, empregado DEVE ao empregador (desconto)
    var indAv=0;
    var descontoPedidoAviso=0;
    if(tipo==='sem_justa'&&av==='indenizado') indAv=avVal;
    if(tipo==='acordo') indAv=avVal/2;
    if(tipo==='pedido'&&av==='indenizado') descontoPedidoAviso=sal; // 1 salário base como desconto

    var tProv=sS+fVenc+fProp+t3+d13+indAv+tPrem;

    // ── INSS ─────────────────────────────────────────────────────────────────
    var vINSS_mensal=calcINSS(Math.min(sS,8475.55));
    var vINSS_13=tipo==='com_justa' ? 0 : calcINSS(Math.min(d13,8475.55));

    // ── IRRF ─────────────────────────────────────────────────────────────────
    var vIRRF_mensal=calcIRRF(sS,vINSS_mensal);
    var usouSimpl_mensal=(607.20>=vINSS_mensal);
    var vIRRF_13=tipo==='com_justa' ? 0 : calcIRRF(d13,vINSS_13);
    var usouSimpl_13=(607.20>=vINSS_13);

    // ── FGTS automático ───────────────────────────────────────────────────────
    var fgtsMensal=sS*0.08;
    var fgts13=tipo==='com_justa' ? 0 : d13*0.08;
    // Multa: não devida em justa causa nem pedido de demissão
    var pct=tipo==='acordo'?.2:.4;
    var multa=0, baseFgtsTotal=0;
    if(fgtsOk&&(tipo==='sem_justa'||tipo==='acordo')){
      baseFgtsTotal=fgtsAcum+fgtsMensal+fgts13;
      multa=baseFgtsTotal*pct;
    }
    var semMultaFGTS=!fgtsOk&&(tipo==='sem_justa'||tipo==='acordo');

    // ── Totais ────────────────────────────────────────────────────────────────
    var tDesc=vINSS_mensal+vINSS_13+vIRRF_mensal+vIRRF_13+dvr+odet+descontoPedidoAviso;
    var tLiq=tProv-tDesc;

    var tLabel={sem_justa:'Sem justa causa',com_justa:'Com justa causa',pedido:'Pedido de demissao',acordo:'Acordo mutuo (484-A)'}[tipo];
    var hoje=new Date().toLocaleDateString('pt-BR');

    // ── HTML resultado (tela) ─────────────────────────────────────────────
    var h='<div class="rcard">'
      +'<div class="tbadge">'+tLabel+'</div>'
      +'<div class="rheader">'+nome+(cargo?' <span style="font-weight:300;color:var(--muted)">· '+cargo+'</span>':'')+'</div>'
      +'<div class="mgrid">'
      +'<div class="mcard"><div class="mlabel">Tempo de servico</div><div class="mval">'+(anos>0?anos+'a ':'')+meses%12+'m</div></div>'
      +'<div class="mcard"><div class="mlabel">Aviso previo</div><div class="mval">'+diasAv+' dias</div></div>'
      +'<div class="mcard"><div class="mlabel">Ferias prop.</div><div class="mval">'+mfp+'/12 avos</div></div>'
      +'</div>';
    if(tAdic>0)h+='<div class="mgrid" style="margin-top:-6px">'
      +'<div class="mcard" style="grid-column:1/3"><div class="mlabel">Remuneracao habitual — base medias</div><div class="mval">'+M(remH)+'</div></div>'
      +'<div class="mcard"><div class="mlabel">Total adicionais</div><div class="mval">'+M(tAdic)+'</div></div></div>';

    // Proventos
    h+='<div class="ssub" style="margin-top:10px">Proventos</div>'
      +'<div class="rrow"><span class="rl">'+saldoDesc+'</span><span class="rv pos">'+M(sS)+'</span></div>';
    if(fVenc>0)h+='<div class="rrow"><span class="rl">Ferias vencidas + 1/3</span><span class="rv pos">'+M(fVenc)+'</span></div>';
    if(tipo!=='com_justa'){
      h+='<div class="rrow"><span class="rl">Ferias proporcionais ('+mfp+'/12)</span><span class="rv pos">'+M(fProp)+'</span></div>'
        +'<div class="rrow"><span class="rl">1/3 constitucional s/ ferias prop.</span><span class="rv pos">'+M(t3)+'</span></div>'
        +'<div class="rrow"><span class="rl">13° salario proporcional ('+mRes+'/12)</span><span class="rv pos">'+M(d13)+'</span></div>';
    }
    if(indAv>0)h+='<div class="rrow"><span class="rl">Aviso previo indenizado'+(tipo==='acordo'?' (50%)':'')+'</span><span class="rv pos">'+M(indAv)+'</span></div>';
    if(prems.length){h+='<hr class="divider"><div class="ssub">Premiacoes (sem incidencias)</div>';prems.forEach(function(x){h+='<div class="rrow"><span class="rl">'+x.d+'</span><span class="rv pos">'+M(x.v)+'</span></div>';});}

    // Descontos
    h+='<hr class="divider"><div class="ssub">Descontos</div>'
      +'<div class="rrow"><span class="rl">INSS Mensal 2026 <span style="font-size:10px;opacity:.65">(base: saldo sal.+adicionais)</span></span><span class="rv neg">- '+M(vINSS_mensal)+'</span></div>'
      +'<div class="rrow"><span class="rl">IRRF Mensal 2026 '
      +(vIRRF_mensal===0?'<span style="font-size:10px;padding:1px 6px;border-radius:3px;background:rgba(74,222,128,.12);color:var(--verde)">Isento</span>':'<span style="font-size:10px;opacity:.65">(ded. '+(usouSimpl_mensal?'simpl. R$607,20':'legal INSS R$'+vINSS_mensal.toFixed(2).replace('.',','))+')</span>')
      +'</span><span class="rv '+(vIRRF_mensal===0?'nt':'neg')+'">'+(vIRRF_mensal===0?'R$ 0,00':'- '+M(vIRRF_mensal))+'</span></div>'
      +(tipo!=='com_justa'?
        '<div class="rrow"><span class="rl">INSS 13° Salario 2026 <span style="font-size:10px;opacity:.65">(base: 13° proporcional)</span></span><span class="rv neg">- '+M(vINSS_13)+'</span></div>'
        +'<div class="rrow"><span class="rl">IRRF 13° Salario 2026 '
        +(vIRRF_13===0?'<span style="font-size:10px;padding:1px 6px;border-radius:3px;background:rgba(74,222,128,.12);color:var(--verde)">Isento</span>':'<span style="font-size:10px;opacity:.65">(ded. '+(usouSimpl_13?'simpl. R$607,20':'legal INSS R$'+vINSS_13.toFixed(2).replace('.',','))+')</span>')
        +'</span><span class="rv '+(vIRRF_13===0?'nt':'neg')+'">'+(vIRRF_13===0?'R$ 0,00':'- '+M(vIRRF_13))+'</span></div>'
      : '');
    if(descontoPedidoAviso>0)h+='<div class="rrow"><span class="rl">Desconto aviso previo nao cumprido (art. 487 §2° CLT)</span><span class="rv neg">- '+M(descontoPedidoAviso)+'</span></div>';
    if(dvr>0) h+='<div class="rrow"><span class="rl">VR/VA</span><span class="rv neg">- '+M(dvr)+'</span></div>';
    if(odet>0)h+='<div class="rrow"><span class="rl">Outros descontos</span><span class="rv neg">- '+M(odet)+'</span></div>';

    // Total líquido (proventos - descontos, sem multa) — ANTES do FGTS
    h+='<div class="trow"><span class="tl">Total liquido (Proventos - Descontos)</span><span class="tv">'+M(tLiq)+'</span></div>';

    // FGTS
    if(fgtsOk&&(tipo==='sem_justa'||tipo==='acordo')){
      h+='<hr class="divider"><div class="ssub">FGTS — Multa rescisoria</div>'
        +'<div class="rrow"><span class="rl">Base FGTS Fins Rescisarios (saldo acumulado)</span><span class="rv nt">'+M(fgtsAcum)+'</span></div>'
        +'<div class="rrow"><span class="rl">FGTS Mensal (8% s/ saldo sal.+adicionais)</span><span class="rv nt">'+M(fgtsMensal)+'</span></div>'
        +(tipo!=='com_justa'?'<div class="rrow"><span class="rl">FGTS 13° Salario (8% s/ 13° proporcional)</span><span class="rv nt">'+M(fgts13)+'</span></div>':'')
        +'<div class="rrow"><span class="rl">Base Total FGTS</span><span class="rv nt">'+M(baseFgtsTotal)+'</span></div>'
        +'<div class="rrow"><span class="rl">Multa ('+(pct*100).toFixed(0)+'%)</span><span class="rv pos">'+M(multa)+'</span></div>'
        // Indicativo final na tela
        +'<div style="background:var(--card2);border:1px solid var(--borda2);border-radius:var(--radius);padding:12px 14px;margin-top:12px;">'
        +'<div class="rrow" style="border:none;padding:4px 0"><span class="rl" style="color:var(--texto);font-weight:500">Total Liquido de Rescisao</span><span class="rv" style="color:var(--ouro2);font-size:15px">'+M(tLiq)+'</span></div>'
        +'<div class="rrow" style="border:none;padding:4px 0"><span class="rl" style="color:var(--texto);font-weight:500">Valor da Guia de FGTS Rescisório</span><span class="rv pos" style="font-size:15px">'+M(multa+fgtsMensal+fgts13)+'</span></div>'
        +'</div>';
    } else if(semMultaFGTS){
      h+='<hr class="divider"><div class="ssub">FGTS</div>'
        +'<div class="rrow"><span class="rl">FGTS Mensal calculado (8%)</span><span class="rv nt">'+M(fgtsMensal)+'</span></div>'
        +'<div class="rrow"><span class="rl">FGTS 13° calculado (8%)</span><span class="rv nt">'+M(fgts13)+'</span></div>'
        +'<div class="rrow"><span class="rl">Multa rescisoria</span><span class="rv nt" style="font-size:11px;">Informe o saldo acumulado para calcular</span></div>';
    }

    if(tAdic>0)h+='<div class="ibox">Adicionais habituais incorporados ao saldo de salario e base das medias. Nao exibidos como rubrica separada para evitar dupla contagem (art. 457 CLT / Sumula 60 TST).</div>';
    h+='<p class="anote">INSS: Portaria MPS/MF n.13/2026 · teto R$8.475,55. IRRF: Lei n.15.270/2025 · isencao ate R$5.000. Simulacao estimada.</p>';
    h+='<button class="btn-pdf no-print" id="btn-pdf">'
      +'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>'
      +' Gerar PDF / Imprimir</button></div>';

    var el=document.getElementById('res');
    el.innerHTML=h;el.style.display='block';
    el.scrollIntoView({behavior:'smooth',block:'start'});

    var payload={
      nome:nome,cargo:cargo,aStr:aStr,rStr:rStr,sal:sal,remH:remH,
      tipo:tipo,tLabel:tLabel,anos:anos,meses:meses,mfp:mfp,
      dRes:dRes,mRes:mRes,diasAv:diasAv,fVenc:fVenc,
      sS:sS,fProp:fProp,t3:t3,d13:d13,indAv:indAv,
      prems:prems,saldoDesc:saldoDesc,
      vINSS_mensal:vINSS_mensal,vINSS_13:vINSS_13,
      vIRRF_mensal:vIRRF_mensal,usouSimpl_mensal:usouSimpl_mensal,
      vIRRF_13:vIRRF_13,usouSimpl_13:usouSimpl_13,
      descontoPedidoAviso:descontoPedidoAviso,
      dvr:dvr,odet:odet,
      fgtsOk:fgtsOk,fgtsAcum:fgtsAcum,fgtsMensal:fgtsMensal,fgts13:fgts13,
      baseFgtsTotal:baseFgtsTotal,pct:pct,multa:multa,
      tProv:tProv,tDesc:tDesc,tLiq:tLiq,semMultaFGTS:semMultaFGTS
    };
    document.getElementById('btn-pdf').addEventListener('click',function(){
      gerarPDF(payload,nome,hoje);
    });
  });
})();
</script>
</body>
</html>
