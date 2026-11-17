@extends('layouts.aplicacion')
@section('titulo', 'Competencia')
@section('encabezado', 'Competencia')
@section('contenido')
<style>
.cmp{--cm-ink:#12344e;--cm-muted:#63788b;--cm-line:#e0e9ef;--cm-blue:#14698f;--cm-deep:#063a47;--cm-soft:#f4f8fa;color:var(--cm-ink);max-width:1500px;margin:0 auto;padding-bottom:32px;font-size:14px}
.cmp *, .cmp *::before,.cmp *::after{box-sizing:border-box}
.cmp h1{margin:4px 0 6px;font-size:clamp(1.48rem,2.6vw,2rem);font-weight:800;line-height:1.15;letter-spacing:-.025em;color:#102e44}
.cmp h2{font-size:1.12rem;margin:0;font-weight:760;color:#17344b}
.cmp .muted{color:var(--cm-muted)}.cmp .small{font-size:.83rem}.cmp .fine,.cmp .foot{font-size:.79rem;color:#62798b;line-height:1.55}.cmp .foot{margin-top:8px}
.cmp .eyebrow{font-size:.73rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:#2683a4}
.cmp .top{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;margin:0 0 16px}
.cmp .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.cmp .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid transparent;padding:11px 16px;border-radius:10px;background:var(--cm-blue);color:#fff;font:inherit;font-weight:720;cursor:pointer;text-decoration:none;transition:background .16s}
.cmp .btn:not(:disabled):hover{background:#0f5575}.cmp .btn.alt{border-color:#d0e5ed;color:#185270;background:#edf6f9}.cmp .btn.alt:hover{background:#dcecf2}
.cmp .btn:disabled{opacity:.53;cursor:not-allowed}.cmp .btn.small-btn{padding:7px 10px;font-size:.81rem}
.cmp .section,.cmp .card,.cmp .action-panel,.cmp .mini-stat{border:1px solid var(--cm-line);background:#fff;border-radius:16px;box-shadow:0 3px 16px #0b3b5508}
.cmp .section{padding:20px;margin-bottom:15px}.cmp .section-head{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:16px}
.cmp .notice{padding:12px 16px;border-radius:12px;background:#eef8f9;border:1px solid #c5e0e8;color:#225870;margin-bottom:14px;line-height:1.5}
.cmp .notice.bad{background:#fff3f0;color:#8b3529;border-color:#f1c9c2}.cmp .notice details{margin-top:6px;font-size:.78rem}.cmp .notice details pre{white-space:pre-wrap;overflow-wrap:anywhere;max-width:100%;margin:6px 0}
.cmp .action-panel{padding:22px;background:linear-gradient(115deg,#0d4053,#125c6a);color:#fff;margin-bottom:14px;border-color:#11505d}
.cmp .action-grid{display:grid;grid-template-columns:minmax(250px,1.8fr) repeat(3,minmax(130px,1fr));gap:14px;align-items:center}
.cmp .action-panel h2{color:white;font-size:1.14rem}.cmp .action-panel p{margin:8px 0 12px;color:#cce1e5;line-height:1.6}.cmp .action-panel .btn{background:#fff;color:#12465a}.cmp .action-panel .btn:not(:disabled):hover{background:#e5f5f5}
.cmp .action-panel .btn:disabled{color:#355b63;background:#d9e3e5}.cmp .action-panel .metric{border-left:1px solid #ffffff40;padding-left:17px;min-height:63px}
.cmp .action-panel .metric strong{display:block;font-size:1.55rem;font-weight:800;line-height:1.2}.cmp .action-panel .metric span{color:#cde3e8;font-size:.79rem}
.cmp .hint{font-size:.76rem;color:#cee3e5;margin-top:8px}.cmp .hint.error-hint{color:#ffdfac}
.cmp .cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin:0 0 15px}.cmp .card{padding:19px}
.cmp .card .kpi{font-size:1.9rem;line-height:1.22;font-weight:850;color:#143e56;margin:8px 0 3px}.cmp .card .stat{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid #e6edf1}.cmp .card .stat div{font-size:.84rem;min-width:0}
.cmp .card .stat strong{display:block;color:#18384d;font-size:.97rem;margin-top:4px;font-variant-numeric:tabular-nums}
.cmp .filters{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.cmp label{display:block;font-weight:700;font-size:.79rem;margin-bottom:5px;color:#355166}
.cmp select,.cmp input[type=file]{padding:10px 11px;border:1px solid #cbdce5;border-radius:9px;background:white;color:#203d52;max-width:100%;font:inherit;min-height:41px}
.cmp .tabs{display:flex;align-items:center;gap:5px;flex-wrap:wrap;border-bottom:1px solid #dce6eb;padding-bottom:9px;margin:4px 0 17px}
.cmp .tab-btn{border:0;cursor:pointer;background:transparent;border-radius:9px;padding:11px 14px;font:inherit;font-weight:700;color:#516e81}
.cmp .tab-btn[aria-selected=true]{color:#fff;background:#176586}.cmp .tab-btn:focus-visible,.cmp .btn:focus-visible{outline:3px solid #8ed3ec;outline-offset:2px}
.cmp [hidden]{display:none!important}.cmp .table-wrap{overflow:auto;max-width:100%}.cmp table{border-collapse:separate;border-spacing:0;min-width:1000px;width:100%;font-size:.86rem}
.cmp th{text-align:left;background:#edf4f7;color:#385267;padding:13px 10px;font-weight:800;white-space:nowrap}.cmp td{border-top:1px solid #e9eef2;padding:11px 10px;vertical-align:middle}.cmp tbody tr:hover{background:#f7fafb}.cmp tr.self{background:#eef9f4}.cmp td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.cmp td small{display:block;color:var(--cm-muted);max-width:320px;margin-top:4px}
.cmp .tag{background:#e8f3f7;color:#235875;border-radius:18px;font-size:.74rem;padding:4px 9px;white-space:nowrap}.cmp .tag.warn{background:#fff1d8;color:#956100}
.cmp .chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cmp .chart{padding:15px;border:1px solid #e2ebf0;border-radius:12px}.cmp .chart svg{width:100%;height:auto;display:block}
.cmp .form-inline{display:flex;gap:8px;align-items:center}.cmp .form-inline select{min-width:118px}.cmp .import{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.cmp .empty{padding:24px;text-align:center;background:#f7fafc;border:1px dashed #cbdde6;border-radius:13px;color:#667d8f}
.cmp .subhead{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px}.cmp .status-chip{display:inline-flex;gap:6px;align-items:center;border-radius:99px;padding:5px 10px;font-weight:700;background:#e5f5ee;color:#186547;font-size:.76rem}
.cmp .rank-filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end;padding:15px;border:1px solid #dce9ef;background:#f6fafb;border-radius:12px;margin-bottom:15px}
.cmp .rank-filters>div{min-width:160px;flex:1 1 160px}.cmp .rank-filters .btn{flex:0 0 auto}
.cmp .rank-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
.cmp .rank-metric{padding:13px 16px;background:#f4f8fa;border:1px solid #e1eaf0;border-radius:12px}
.cmp .rank-metric b{display:block;font-size:1.27rem;margin-top:5px;color:#133b54}
.cmp .rank-hl{background:#e9f7f0!important}.cmp .rank-pos{display:inline-flex;align-items:center;justify-content:center;background:#e9f1f5;color:#154563;padding:5px 9px;border-radius:8px;font-weight:800;min-width:32px}
.cmp .rank-hl .rank-pos{background:#d2efdf;color:#176140}.cmp .rank-caption{font-size:.8rem;line-height:1.5;color:#61778a;margin:10px 0 0}
.cmp .subsection-divider{border:0;border-top:1px solid #dfe8ee;margin:23px 0}
.cmp .rank-search{margin:0 0 12px;display:flex;gap:10px;align-items:end;flex-wrap:wrap}.cmp .rank-search input{padding:10px 11px;min-height:41px;min-width:230px;width:min(340px,100%);border:1px solid #cbdce5;border-radius:9px;font:inherit}
@media(max-width:1100px){.cmp .action-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.cmp .action-grid>div:first-child{grid-column:1/-1}.cmp .action-panel .metric{border-left:0;border-top:1px solid #ffffff40;padding:11px 0 0}}
@media(max-width:720px){.cmp .rank-meta{grid-template-columns:1fr}.cmp .rank-filters>div{flex:1 1 100%}.cmp .rank-filters .btn{width:100%}.cmp .cards,.cmp .chart-grid{grid-template-columns:1fr}.cmp .section{padding:14px}.cmp .action-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.cmp .action-panel{padding:16px}.cmp .action-panel .metric strong{font-size:1.2rem}.cmp .card .stat{gap:8px}.cmp .tabs{gap:3px}.cmp .tab-btn{padding:9px 10px;font-size:.8rem}.cmp table{min-width:920px}}
.cmp .cambio-fila{background:#fffaf1}.cmp .cambio-fila:hover{background:#fff5e6!important}
.cmp .cambio-marca{display:inline-flex;align-items:center;gap:4px;margin-left:6px;padding:3px 7px;border-radius:8px;background:#fff0d8;color:#865300;font-size:.72rem;font-weight:800;white-space:nowrap}
.cmp .cambio-delta{display:block;font-size:.76rem;font-weight:800;margin-top:3px;white-space:nowrap}
.cmp .cambio-delta.subida{color:#a83b30}.cmp .cambio-delta.bajada{color:#11734a}
.cmp .change-summary{border:1px solid #dfe8ef;border-radius:14px;background:#fff;padding:17px;margin:12px 0 16px}
.cmp .change-summary h2{font-size:1.1rem}.cmp .change-stats{display:flex;flex-wrap:wrap;gap:12px;margin:8px 0}
.cmp .change-stats span{display:inline-block;background:#f1f6fa;border-radius:9px;padding:8px 12px;font-weight:700}
.cmp .change-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:0;list-style:none;margin:12px 0 0}
.cmp .change-list li{padding:9px 12px;border:1px solid #e1e9ee;border-radius:10px;line-height:1.5}
.cmp .changes-no-data{margin-top:8px;color:#63788b;font-size:.85rem}
@media(max-width:720px){.cmp .change-list{grid-template-columns:1fr}}
.cmp .insights-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}
.cmp .insight{border:1px solid #e1e9ef;border-radius:12px;background:#f7fafc;padding:12px 14px}
.cmp .insight b{display:block;font-size:1.55rem;margin:5px 0 0;font-variant-numeric:tabular-nums}
.cmp .insight strong{font-size:.78rem;color:#506779}
.cmp .alertas-directos{margin-top:14px;border-top:1px solid #e1e9ef;padding-top:12px}
.cmp .alertas-directos ul{padding:0;list-style:none;display:grid;gap:8px;margin:10px 0}
.cmp .alertas-directos li{padding:10px 12px;border:1px solid #e3eaf0;border-left:4px solid #14698f;border-radius:9px;background:#f8fbfd}
.cmp .hist-selector{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin:12px 0 16px}
.cmp .hist-selector select{min-width:240px;max-width:100%;width:min(430px,100%)}
.cmp .hist-delta{display:block;font-size:.73rem;font-weight:800;margin-top:3px}
.cmp .hist-delta.subida{color:#a83b30}.cmp .hist-delta.bajada{color:#11734a}
@media(max-width:850px){.cmp .insights-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.cmp .insights-grid{grid-template-columns:1fr 1fr}.cmp .insight{padding:10px}.cmp .insight b{font-size:1.3rem}}


/* COMPETENCIA v1.7 — Diseño compacto, sin duplicar alertas. */
.cmp{font-size:14px;padding-bottom:18px}
.cmp .section{padding:14px 16px;margin-bottom:10px;border-radius:13px}
.cmp .section-head{margin-bottom:10px}
.cmp .top{margin-bottom:11px}
.cmp .top h1{font-size:1.65rem;margin:3px 0}
.cmp .foot{line-height:1.4}
.cmp .tabs{margin:4px 0 12px;padding-bottom:7px}
.cmp .tab-btn{padding:9px 12px}
.cmp .action-panel{padding:15px 18px;margin-bottom:10px}
.cmp .action-grid{gap:12px}
.cmp .action-panel p{margin:4px 0 9px;line-height:1.42;font-size:.88rem}
.cmp .action-panel .metric{min-height:48px}
.cmp .action-panel .metric strong{font-size:1.3rem}
.cmp .action-panel .hint{margin-top:5px}
.cmp .cards{gap:10px;margin-bottom:10px}
.cmp .card{padding:14px 16px;border-radius:13px}
.cmp .card .kpi{font-size:1.65rem;margin:5px 0 2px}
.cmp .card .stat{gap:8px 12px;margin-top:9px;padding-top:9px}
.cmp .card .stat div{font-size:.81rem}
.cmp .card .stat strong{margin-top:2px;font-size:.93rem}
.cmp th{padding:9px 9px}
.cmp td{padding:8px 9px}
.cmp .change-summary{padding:15px 16px;margin:0 0 10px}
.cmp .cmp-moves-title{margin-bottom:10px}
.cmp .cmp-moves-title h2{font-size:1.13rem}
.cmp .cmp-dates{display:inline-flex;gap:7px;align-items:center;border-radius:9px;background:#e8f3f6;border:1px solid #cfe3ea;padding:6px 10px;font-weight:780;font-size:.85rem;color:#1b586e;white-space:nowrap}
.cmp .insights-grid{margin:9px 0 11px;gap:8px}
.cmp .insight{padding:9px 12px;border-radius:10px}
.cmp .insight strong{font-size:.79rem}
.cmp .insight b{font-size:1.42rem;margin-top:2px;line-height:1.25}
.cmp .cmp-movement-grid{display:grid;grid-template-columns:minmax(260px,.9fr) minmax(0,1.65fr);gap:12px;align-items:start}
.cmp .cmp-movement-panel{min-width:0;border:1px solid #e3ebf0;border-radius:11px;overflow:hidden}
.cmp .cmp-movement-heading{min-height:40px;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:10px 11px 8px}
.cmp .cmp-movement-heading h3{margin:0;font-size:.93rem;color:#21465d;font-weight:800}
.cmp .cmp-change-total{font-size:.79rem;font-weight:750;color:#547084;white-space:nowrap}
.cmp .cmp-movement-table{min-width:0;width:100%;font-size:.83rem}
.cmp .cmp-movement-table th{background:#f1f6f9;padding:8px 10px}
.cmp .cmp-movement-table td{padding:8px 10px;line-height:1.35}
.cmp .cmp-movement-table td small{display:block;margin-top:2px;font-size:.76rem}
.cmp .cmp-movement-table .num{text-align:right}
.cmp .cmp-movement-detail{min-width:520px}
.cmp .cmp-mini-badge{background:#e5f4f0;color:#17644c;border-radius:7px;font-size:.69rem;font-weight:800;padding:2px 6px;margin-left:5px;white-space:nowrap}
.cmp .cmp-movement-delta{display:inline-block;white-space:nowrap;font-weight:800;font-size:.81rem}
.cmp .cmp-movement-delta.subida{color:#a73931}
.cmp .cmp-movement-delta.bajada{color:#12734c}
.cmp .cmp-direct-note{padding:9px 11px;color:#315c70;background:#f6fafc;font-size:.8rem;line-height:1.4}
.cmp .cmp-direct-note span{color:#647c8d}
.cmp .cmp-empty-movement{padding:12px 14px;color:#506c7d}
.cmp .cmp-help{border-top:1px solid #e5ecf0;margin-top:11px;padding-top:8px;color:#526f80;font-size:.83rem;line-height:1.45}
.cmp .cmp-help summary{cursor:pointer;font-weight:700;color:#23637b}
.cmp .cmp-help p{margin:6px 0 0}
.cmp .cmp-info{margin:0 0 11px;padding:8px 12px;border:1px solid #e2ebef;border-radius:10px;background:#f8fbfc}
.cmp .rank-filters{padding:11px;margin-bottom:11px}
.cmp .rank-metric{padding:9px 12px}
.cmp .chart{padding:12px}
@media(min-width:1450px){.cmp .cards{grid-template-columns:repeat(4,minmax(0,1fr))}.cmp .card .stat{grid-template-columns:1fr 1fr}}
@media(max-width:1060px){.cmp .cmp-movement-grid{grid-template-columns:1fr}.cmp .cmp-movement-detail{min-width:520px}}
@media(max-width:720px){.cmp .section{padding:12px}.cmp .card{padding:12px}.cmp .action-panel{padding:14px}.cmp .cmp-movement-heading{flex-wrap:wrap}.cmp .cmp-dates{font-size:.78rem}.cmp .cmp-moves-title{align-items:flex-start}}

</style>
<div class="cmp">
 <header class="top">
  <div><div class="eyebrow">INTELIGENCIA COMERCIAL · YURIMAGUAS</div><h1>Precios de la competencia</h1><div class="muted small">Facilito / Osinergmin · Ubigeo 160201 · Seguimiento de Gasolina 84, Diésel B5, Regular y Premium</div></div>
  <div class="toolbar"><a class="btn alt" href="{{ route('competencia.manual') }}">Registro manual anterior</a><?php if ($captura): ?><a class="btn alt" href="{{ route('competencia.exportar', ['captura'=>$captura->id]) }}">Exportar CSV</a><?php endif; ?></div>
 </header>
 <?php if (session('exito')): ?><div class="notice" role="status">{{ session('exito') }}</div><?php endif; ?>
 <?php if (session('error')): ?>
  <?php $mensajeTecnico = (string) session('error'); $errorCertificado = str_contains(strtolower($mensajeTecnico), 'curl error 60') || str_contains(strtolower($mensajeTecnico), 'certificate'); ?>
  <div class="notice bad" role="alert"><strong>No se pudo completar la actualización.</strong> {{ $errorCertificado ? 'El servidor no pudo validar el certificado HTTPS de ApisTED.' : 'Verifica la conexión y revisa los detalles del intento.' }}
   <?php if (auth()->user()->rol === 'ADMINISTRADOR'): ?><details><summary>Ver detalle técnico</summary><pre>{{ $mensajeTecnico }}</pre></details><?php endif; ?>
  </div>
 <?php endif; ?>
 <?php if ($errors->any()): ?><div class="notice bad" role="alert">{{ $errors->first() }}</div><?php endif; ?>
 <?php if ($captura && $captura->advertencia): ?><div class="notice bad">Atención: {{ $captura->advertencia }}</div><?php endif; ?>
 <?php if (auth()->user()->rol==='ADMINISTRADOR'): ?>
  <section class="action-panel" aria-label="Captura manual de Facilito">
   <div class="action-grid">
    <div>
     <div class="eyebrow" style="color:#9edee5">ACTUALIZACIÓN MANUAL</div>
     <h2>Consultar precios en Facilito</h2>
     <p>Guarda una fotografía de los precios publicados en Yurimaguas. Solo se ejecuta cuando presionas el botón; no requiere dejar la computadora encendida.</p>
     <form method="POST" action="{{ route('competencia.capturar') }}" onsubmit="return confirm('¿Consultar ApisTED y guardar la captura de hoy? Solo se permite una captura válida por día.');">
      <input type="hidden" name="_token" value="{{ csrf_token() }}">
      <button class="btn" type="submit" <?php echo (!$puedeCapturar) ? 'disabled' : ''; ?>>Consultar Facilito ahora</button>
     </form>
     <?php if ($motivoBloqueo): ?><div class="hint error-hint">{{ $motivoBloqueo }}</div><?php else: ?><div class="hint">Una captura válida por día, cualquier día de la semana · Sin consultas automáticas</div><?php endif; ?>
    </div>
    <div class="metric"><strong>{{ $yaCapturadoHoy ? '1 / 1' : '0 / 1' }}</strong><span>Capturas hoy</span></div>
    <div class="metric"><strong>{{ $puedeCapturar ? 'Disponible' : 'No disponible' }}</strong><span>Consulta manual</span></div>
    <div class="metric"><strong>{{ $captura ? \Carbon\Carbon::parse($captura->fecha_jornada)->format('d/m') : '—' }}</strong><span>Última jornada guardada</span></div>
   </div>
  </section>
 <?php endif; ?>
 <nav class="tabs" role="tablist" aria-label="Secciones de Competencia">
  <button type="button" class="tab-btn" role="tab" id="cmp-tab-resumen" aria-controls="cmp-panel-resumen" aria-selected="true" data-cmp-tab="resumen">Resumen</button>
  <button type="button" class="tab-btn" role="tab" id="cmp-tab-ranking" aria-controls="cmp-panel-ranking" aria-selected="false" data-cmp-tab="ranking">Establecimientos<?php if ($variaciones['grifos_afectados'] > 0): ?> ({{ $variaciones['grifos_afectados'] }} con cambios)<?php endif; ?></button>
  <button type="button" class="tab-btn" role="tab" id="cmp-tab-tendencias" aria-controls="cmp-panel-tendencias" aria-selected="false" data-cmp-tab="tendencias">Evolución</button>
  <button type="button" class="tab-btn" role="tab" id="cmp-tab-historial" aria-controls="cmp-panel-historial" aria-selected="false" data-cmp-tab="historial">Historial</button>
  <?php if (auth()->user()->rol==='ADMINISTRADOR'): ?><button type="button" class="tab-btn" role="tab" id="cmp-tab-admin" aria-controls="cmp-panel-admin" aria-selected="false" data-cmp-tab="admin">Administración</button><?php endif; ?>
 </nav>
 <div id="cmp-panel-resumen" role="tabpanel" aria-labelledby="cmp-tab-resumen" data-cmp-panel="resumen">
 <section class="section">
  <div class="section-head"><div><h2>Seleccionar período y ámbito</h2><div class="foot">El precio publicado por Facilito puede diferir del precio vigente en el dispensador.</div></div>
  <form method="GET" action="{{ route('competencia.indice') }}" class="filters">
   <div><label for="cmp-captura">Fecha de consulta</label><select id="cmp-captura" name="captura" <?php echo (!$captura) ? 'disabled' : ''; ?>><?php foreach ($capturas as $c): ?><option value="{{ $c->id }}" <?php echo ($captura && $c->id == $captura->id) ? 'selected' : ''; ?>>{{ \Carbon\Carbon::parse($c->fecha_jornada)->format('d/m/Y') }} · {{ $c->origen }}</option><?php endforeach; ?></select></div>
   <div><label for="cmp-zona">Ámbito de comparación</label><select id="cmp-zona" name="zona"><option value="cerca" <?php echo ($zona==='cerca') ? 'selected' : ''; ?>>A 5 km en línea recta</option><option value="directos" <?php echo ($zona==='directos') ? 'selected' : ''; ?>>Competencia directa</option><option value="todos" <?php echo ($zona==='todos') ? 'selected' : ''; ?>>Todo el distrito</option></select></div>
   <button class="btn" type="submit">Aplicar</button>
  </form></div>
  <?php if ($captura): ?><div class="fine">Fuente: {{ $captura->origen }} · Consulta del {{ \Carbon\Carbon::parse($captura->fecha_consulta)->format('d/m/Y H:i') }} · {{ $captura->establecimientos_guardados }} establecimientos · {{ $captura->precios_guardados }} precios · Identificación propia: código 102201</div><?php else: ?><div class="muted">Aún no hay capturas. Usa el botón principal para consultar Facilito o importa el JSON inicial desde Administración.</div><?php endif; ?>
 </section>
 <div class="cards">
 <?php foreach ($catalogo as $codigo => $meta): ?>
 <?php $s = $datos['resumen'][$codigo] ?? null; ?>
 <div class="card"><div class="muted small">{{ $meta['nombre'] }} · S/ por galón</div>
   <div class="kpi">{{ $s && $s['promedio']!==null ? 'S/ '.number_format($s['promedio'],2) : '—' }}</div><div class="fine">Promedio de competidores seleccionados · {{ $s['n'] ?? 0 }} registros</div>
   <div class="stat"><div><span class="muted">Munichis en API</span><strong>{{ $s && $s['propio']!==null ? 'S/ '.number_format($s['propio'],2) : '—' }}</strong></div><div><span class="muted">Precio operativo</span><strong>{{ $meta['producto_id'] === null ? 'Sin producto en catálogo' : (isset($preciosLocales[$meta['producto_id']]) ? 'S/ '.number_format((float) $preciosLocales[$meta['producto_id']],2) : '—') }}</strong></div><div><span class="muted">Mínimo / Máximo</span><strong>{{ $s && $s['n'] ? number_format($s['minimo'],2).' / '.number_format($s['maximo'],2) : '—' }}</strong></div><div><span class="muted">Brecha API vs promedio</span><strong>{{ $s && $s['brecha']!==null ? sprintf('%+.2f', $s['brecha']) : '—' }}</strong></div></div>
 </div>
 <?php endforeach; ?>
 </div>

  <section class="change-summary cmp-moves" aria-label="Variaciones de precios frente a la consulta anterior">
   <div class="section-head cmp-moves-title">
    <div>
     <h2>Movimientos de precios</h2>
     <div class="foot">Cambios observados en todo Yurimaguas, por grifo y combustible.</div>
    </div>
    <?php if ($captura && $variaciones['anterior']): ?>
     <span class="cmp-dates">{{ \Carbon\Carbon::parse($variaciones['anterior']->fecha_jornada)->format('d/m/Y') }} <span aria-hidden="true">→</span> {{ \Carbon\Carbon::parse($captura->fecha_jornada)->format('d/m/Y') }}</span>
    <?php endif; ?>
   </div>
   <?php if (!$captura): ?>
    <div class="empty">Aún no hay capturas guardadas.</div>
   <?php elseif (!$variaciones['anterior']): ?>
    <div class="empty">Esta es la primera captura. Los cambios aparecerán cuando se registre otra fecha.</div>
   <?php else: ?>
    <div class="insights-grid" aria-label="Resumen de movimientos">
     <div class="insight"><strong>Grifos comparables</strong><b>{{ $variaciones['grifos_comparables'] }}</b></div>
     <div class="insight"><strong>Con subidas</strong><b>{{ $variaciones['grifos_con_subida'] }}</b></div>
     <div class="insight"><strong>Con bajadas</strong><b>{{ $variaciones['grifos_con_bajada'] }}</b></div>
     <div class="insight"><strong>Sin cambios</strong><b>{{ $variaciones['grifos_estables'] }}</b></div>
    </div>
    <div class="cmp-movement-grid">
     <div class="cmp-movement-panel">
      <div class="cmp-movement-heading"><h3>Variaciones por combustible</h3><span class="fine">N.º de precios</span></div>
      <div class="table-wrap"><table class="cmp-movement-table" aria-label="Variaciones por combustible">
       <thead><tr><th>Combustible</th><th class="num">Subidas</th><th class="num">Bajadas</th></tr></thead><tbody>
       <?php foreach ($catalogo as $codigoResumen => $metaResumen): ?>
        <tr><td>{{ $metaResumen['nombre'] }}</td><td class="num">{{ $variaciones['por_producto'][$codigoResumen]['subidas'] ?? 0 }}</td><td class="num">{{ $variaciones['por_producto'][$codigoResumen]['bajadas'] ?? 0 }}</td></tr>
       <?php endforeach; ?>
       </tbody></table></div>
      <div class="cmp-direct-note">{{ $variaciones['directos_con_cambios'] }} competidor(es) directos con cambios. <span>Las variaciones incluyen grifos fuera de 5 km.</span></div>
     </div>
     <div class="cmp-movement-panel">
      <div class="cmp-movement-heading"><h3>Precios que cambiaron</h3>
       <span class="cmp-change-total">{{ $variaciones['grifos_afectados'] }} grifo(s) · ↑ {{ $variaciones['subidas'] }} · ↓ {{ $variaciones['bajadas'] }}</span>
      </div>
      <?php if (count($variaciones['cambios']) > 0): ?>
       <div class="table-wrap">
        <table class="cmp-movement-table cmp-movement-detail" aria-label="Detalles de precios modificados">
         <thead><tr><th>Establecimiento / combustible</th><th class="num">Antes</th><th class="num">Ahora</th><th class="num">Variación</th></tr></thead>
         <tbody>
         <?php foreach ($variaciones['cambios'] as $cambio): ?>
          <tr>
           <td><strong>{{ $cambio['nombre'] }}</strong>
            <?php if ($cambio['es_munichis']): ?><span class="cmp-mini-badge">Munichis</span>
            <?php elseif ($cambio['directo']): ?><span class="cmp-mini-badge">Directo</span><?php endif; ?>
            <small>{{ $cambio['combustible'] }}<?php if ($cambio['distancia'] !== null): ?> · {{ number_format($cambio['distancia'],2) }} km<?php endif; ?></small>
           </td>
           <td class="num">S/ {{ number_format($cambio['anterior'], 2) }}</td>
           <td class="num">S/ {{ number_format($cambio['actual'], 2) }}</td>
           <td class="num"><span class="cmp-movement-delta {{ $cambio['tipo'] }}">{{ $cambio['variacion'] > 0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($cambio['variacion']), 2) }}</span></td>
          </tr>
         <?php endforeach; ?>
         </tbody>
        </table>
       </div>
      <?php else: ?>
       <div class="cmp-empty-movement">No se observaron cambios en los precios comparables.</div>
      <?php endif; ?>
     </div>
    </div>
    <details class="cmp-help"><summary>Cómo se calculan las variaciones</summary>
     <p>Se compara cada precio de la captura seleccionada con la observación inmediatamente anterior del mismo código Osinergmin y combustible. Son fechas de observación: no se conoce el momento exacto de modificación. Un grifo que sube un combustible y baja otro figura en ambos conteos. Los indicadores por combustible y por grifos comparables excluyen a Munichis, pero la tabla de movimientos puede mostrar sus cambios. No se cuentan como cambios los productos nuevos o sin precio anterior.</p>
    </details>
   <?php endif; ?>
  </section>
  <details class="cmp-help cmp-info"><summary>Cómo interpretar los precios</summary><p>Los promedios consideran los competidores del ámbito seleccionado y excluyen a Munichis. «—» significa que el precio no fue reportado; no equivale a cero. Premium se monitorea aunque no exista en el catálogo operativo. El precio operativo proviene del sistema Munichis y puede diferir del publicado en Facilito. Las distancias son en línea recta.</p></details>
 </div>
 <div id="cmp-panel-ranking" role="tabpanel" aria-labelledby="cmp-tab-ranking" data-cmp-panel="ranking" hidden>
 <section class="section" aria-labelledby="cmp-rank-title">
  <div class="section-head"><div><h2 id="cmp-rank-title">Ranking de precios por combustible</h2><div class="foot">Posición = orden de precio del combustible seleccionado, no calidad ni volumen de ventas.</div></div><span class="tag">Captura seleccionada · {{ $captura ? \Carbon\Carbon::parse($captura->fecha_jornada)->format('d/m/Y') : 'Sin datos' }}</span></div>
  <form method="GET" action="{{ route('competencia.indice') }}#ranking" class="rank-filters">
   <?php if ($captura): ?><input type="hidden" name="captura" value="{{ $captura->id }}"><?php endif; ?>
   <div><label for="cmp-rank-producto">Combustible</label><select id="cmp-rank-producto" name="producto">
    <?php foreach ($catalogo as $codigo => $meta): ?><option value="{{ $codigo }}" <?php echo $productoRanking === $codigo ? 'selected' : ''; ?>>{{ $meta['nombre'] }}</option><?php endforeach; ?>
   </select></div>
   <div><label for="cmp-rank-orden">Orden del precio</label><select id="cmp-rank-orden" name="orden"><option value="asc" <?php echo $ordenRanking==='asc' ? 'selected' : ''; ?>>Menor a mayor</option><option value="desc" <?php echo $ordenRanking==='desc' ? 'selected' : ''; ?>>Mayor a menor</option></select></div>
   <div><label for="cmp-rank-zona">Ámbito de comparación</label><select id="cmp-rank-zona" name="zona"><option value="cerca" <?php echo $zona==='cerca' ? 'selected' : ''; ?>>Hasta 5 km</option><option value="directos" <?php echo $zona==='directos' ? 'selected' : ''; ?>>Competencia directa</option><option value="todos" <?php echo $zona==='todos' ? 'selected' : ''; ?>>Todo el distrito</option></select></div>
   <button class="btn" type="submit">Ver ranking</button>
  </form>
  <?php if ($captura): ?>
   <div class="rank-meta" aria-label="Indicadores del ranking">
    <div class="rank-metric"><span class="fine">Competidores con precio</span><b>{{ $ranking['competidores'] }}</b></div>
    <div class="rank-metric"><span class="fine">Precio Munichis publicado</span><b>{{ $ranking['precio_munichis']===null ? 'No reportado' : 'S/ '.number_format($ranking['precio_munichis'],2) }}</b></div>
    <div class="rank-metric rank-hl"><span class="fine">Posición Munichis</span><b>{{ $ranking['puesto_munichis']===null ? 'No participa' : $ranking['puesto_munichis'].' de '.$ranking['total'] }}</b></div>
   </div>
   <?php if ($ranking['total'] > 0): ?>
    <div class="table-wrap"><table style="min-width:760px" aria-label="Ranking de precios del combustible seleccionado"><thead><tr><th scope="col">Puesto</th><th scope="col">Establecimiento</th><th scope="col" style="text-align:right">Precio S/ galón</th><th scope="col" style="text-align:right">Dif. vs Munichis</th><th scope="col">Distancia</th><th scope="col">Cambio vs anterior</th></tr></thead><tbody>
    <?php foreach ($ranking['filas'] as $f): ?>
     <tr class="{{ $f['variacion'] ? 'cambio-fila' : ($f['es_munichis'] ? 'rank-hl' : '') }}"><td><span class="rank-pos">{{ $f['puesto'] }}</span></td><td><strong>{{ $f['nombre'] }}</strong><?php if ($f['es_munichis']): ?> <span class="tag">Munichis</span><?php endif; ?><small>Código {{ $f['codigo'] }}</small></td>
     <td class="num"><strong>S/ {{ number_format($f['precio'],2) }}</strong></td>
     <td class="num">{{ $f['diferencia']===null ? '—' : (($f['diferencia']>0 ? '+' : '').'S/ '.number_format($f['diferencia'],2)) }}</td>
     <td>{{ $f['distancia']===null ? 'Sin coordenadas' : number_format($f['distancia'],2).' km' }}</td>
     <td><?php if ($f['variacion']): ?><span class="cambio-delta {{ $f['variacion']['tipo'] }}">{{ $f['variacion']['variacion']>0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($f['variacion']['variacion']),2) }}</span><?php else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <p class="rank-caption">Empates comparten puesto (por ejemplo 1, 1, 3); se comparan precios con cuatro decimales. Munichis participa en el puesto si reporta ese combustible, pero no cuenta como competidor. La diferencia es precio del establecimiento menos precio publicado por Munichis. «—» indica que falta el precio de referencia.</p>
   <?php else: ?><div class="empty">No hay precios reportados de {{ $catalogo[$productoRanking]['nombre'] }} en el ámbito seleccionado para esta captura.</div><?php endif; ?>
  <?php else: ?><div class="empty">Todavía no hay capturas de Facilito. Realiza una consulta o importa un JSON válido.</div><?php endif; ?>
 </section>
 <section class="section" aria-labelledby="cmp-comparador-title">
  <div class="section-head"><div><h2 id="cmp-comparador-title">Comparador de establecimientos</h2><div class="foot">Listado alfabético, sin posiciones. Todos los combustibles informados por cada grifo.</div></div><span class="fine">{{ count($datos['filas']) }} establecimientos en el ámbito</span></div>
  <?php if ($captura): ?>
  <div class="rank-search"><div><label for="cmp-buscar-est">Buscar por establecimiento, dirección o código</label><input type="search" id="cmp-buscar-est" autocomplete="off" placeholder="Buscar en la tabla visible…"></div><span class="fine" id="cmp-buscar-contador" role="status" aria-live="polite"></span></div>
  <div class="table-wrap"><table id="cmp-comparador"><thead><tr><th>Establecimiento / dirección</th><th>Código</th><th>Distancia</th><th style="text-align:right">Gasolina 84</th><th style="text-align:right">Diésel B5</th><th style="text-align:right">Regular</th><th style="text-align:right">Premium</th><th>Clasificación</th></tr></thead><tbody>
  <?php foreach ($datos['filas'] as $f): ?>
  <?php $cambiosGrifo = $variaciones['por_codigo'][(string) $f->codigo] ?? []; ?>
  <tr class="{{ count($cambiosGrifo) ? 'cambio-fila' : ($f->es_munichis ? 'self' : '') }}"><td><strong>{{ $f->nombre }}</strong> <a class="fine" href="{{ route('competencia.indice', ['captura'=>$captura->id, 'zona'=>$zona, 'grifo'=>$f->codigo]) }}#historial" title="Ver precios históricos">Ver historial</a>
  <?php if (count($cambiosGrifo)): ?><span class="cambio-marca" title="Cambió al menos un precio respecto a la captura anterior">⚡ Precios modificados: {{ count($cambiosGrifo) }}</span><?php endif; ?><?php if ($f->es_munichis): ?> <span class="tag">Munichis</span><?php endif; ?><small>{{ $f->direccion }}</small></td><td>{{ $f->codigo }}</td><td>{{ $f->distancia===null ? 'Sin datos' : number_format($f->distancia,2).' km' }}</td><td class="num">{{ isset($f->precios['gasolina_84']) ? 'S/ '.number_format($f->precios['gasolina_84'],2) : '—' }}<?php if (isset($cambiosGrifo['gasolina_84'])): ?>
   <span class="cambio-delta {{ $cambiosGrifo['gasolina_84']['tipo'] }}" title="Precio anterior: S/ {{ number_format($cambiosGrifo['gasolina_84']['anterior'],2) }}">{{ $cambiosGrifo['gasolina_84']['variacion']>0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($cambiosGrifo['gasolina_84']['variacion']),2) }}</span><?php endif; ?></td><td class="num">{{ isset($f->precios['diesel_b5_uv']) ? 'S/ '.number_format($f->precios['diesel_b5_uv'],2) : '—' }}<?php if (isset($cambiosGrifo['diesel_b5_uv'])): ?>
   <span class="cambio-delta {{ $cambiosGrifo['diesel_b5_uv']['tipo'] }}" title="Precio anterior: S/ {{ number_format($cambiosGrifo['diesel_b5_uv']['anterior'],2) }}">{{ $cambiosGrifo['diesel_b5_uv']['variacion']>0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($cambiosGrifo['diesel_b5_uv']['variacion']),2) }}</span><?php endif; ?></td><td class="num">{{ isset($f->precios['gasolina_regular']) ? 'S/ '.number_format($f->precios['gasolina_regular'],2) : '—' }}<?php if (isset($cambiosGrifo['gasolina_regular'])): ?>
   <span class="cambio-delta {{ $cambiosGrifo['gasolina_regular']['tipo'] }}" title="Precio anterior: S/ {{ number_format($cambiosGrifo['gasolina_regular']['anterior'],2) }}">{{ $cambiosGrifo['gasolina_regular']['variacion']>0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($cambiosGrifo['gasolina_regular']['variacion']),2) }}</span><?php endif; ?></td><td class="num">{{ isset($f->precios['gasolina_premium']) ? 'S/ '.number_format($f->precios['gasolina_premium'],2) : '—' }}<?php if (isset($cambiosGrifo['gasolina_premium'])): ?>
   <span class="cambio-delta {{ $cambiosGrifo['gasolina_premium']['tipo'] }}" title="Precio anterior: S/ {{ number_format($cambiosGrifo['gasolina_premium']['anterior'],2) }}">{{ $cambiosGrifo['gasolina_premium']['variacion']>0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($cambiosGrifo['gasolina_premium']['variacion']),2) }}</span><?php endif; ?></td><td>
  <?php if (!$f->es_munichis && auth()->user()->rol==='ADMINISTRADOR'): ?><form method="POST" action="{{ route('competencia.preferencia') }}" class="form-inline"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="codigo_osinergmin" value="{{ $f->codigo }}"><select name="categoria" aria-label="Clasificación de {{ $f->nombre }}"><option value="AUTO" <?php echo ($f->categoria==='AUTO') ? 'selected' : ''; ?>>Auto (5 km)</option><option value="DIRECTO" <?php echo ($f->categoria==='DIRECTO') ? 'selected' : ''; ?>>Directo</option><option value="GENERAL" <?php echo ($f->categoria==='GENERAL') ? 'selected' : ''; ?>>General</option><option value="EXCLUIR" <?php echo ($f->categoria==='EXCLUIR') ? 'selected' : ''; ?>>Excluir análisis</option></select><button class="btn alt small-btn" type="submit">Guardar</button></form>
  <?php else: ?><span class="tag {{ $f->directo ? '' : 'warn' }}">{{ $f->es_munichis ? 'Propio' : ($f->directo ? 'Directo' : 'General') }}</span><?php endif; ?>
  </td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="foot">Distancia en línea recta calculada desde las coordenadas de Munichis; no equivale al recorrido real. Estaciones sin coordenadas válidas se omiten de la proximidad. «—» significa que no se informó precio. Las filas resaltadas presentan variaciones frente a la captura anterior. La tabla no asigna puestos ni mide calidad del establecimiento.</div>
  <?php else: ?><div class="muted">Importa una captura para consultar el comparador.</div><?php endif; ?>
 </section>

 </div>
 <div id="cmp-panel-tendencias" role="tabpanel" aria-labelledby="cmp-tab-tendencias" data-cmp-panel="tendencias" hidden>
 <section class="section">
  <div class="section-head"><h2>Evolución de precios — competidores a 5 km</h2><span class="fine">Hasta 24 capturas; cada punto representa una observación real.</span></div>
  <?php
    $graficos = [];
    foreach ($catalogo as $codigoGrafico => $metaGrafico) {
        $valoresGrafico = array_values(array_filter(
            array_column($tendencia, $codigoGrafico),
            fn ($valor) => $valor !== null
        ));
        $minimoGrafico = count($valoresGrafico) ? min($valoresGrafico) - 0.3 : 0;
        $maximoGrafico = count($valoresGrafico) ? max($valoresGrafico) + 0.3 : 1;
        $puntosGrafico = [];
        foreach ($tendencia as $indiceGrafico => $registroGrafico) {
            $valorGrafico = $registroGrafico[$codigoGrafico] ?? null;
            if ($valorGrafico !== null) {
                $xGrafico = 35 + $indiceGrafico * 590 / max(1, count($tendencia) - 1);
                $yGrafico = 155 - (($valorGrafico - $minimoGrafico) / max(0.01, $maximoGrafico - $minimoGrafico)) * 115;
                $puntosGrafico[] = round($xGrafico, 1).','.round($yGrafico, 1);
            }
        }
        $graficos[$codigoGrafico] = [
            'minimo' => $minimoGrafico,
            'maximo' => $maximoGrafico,
            'puntos' => implode(' ', $puntosGrafico),
            'cantidad' => count($puntosGrafico),
        ];
    }
    $primeraFechaGrafico = count($tendencia) ? \Carbon\Carbon::parse($tendencia[0]['fecha'])->format('d/m') : '';
    $ultimaFechaGrafico = count($tendencia) ? \Carbon\Carbon::parse($tendencia[count($tendencia)-1]['fecha'])->format('d/m') : '';
    $coloresGraficos = ['gasolina_84' => '#9a7b25', 'diesel_b5_uv' => '#218b70', 'gasolina_regular' => '#176c9a', 'gasolina_premium' => '#7a56a1'];
  ?>
  <div class="chart-grid">
   <?php foreach ($catalogo as $codigoGrafico => $metaGrafico): ?>
    <div class="chart">
     <strong>{{ $metaGrafico['nombre'] }}</strong>
     <?php if ($graficos[$codigoGrafico]['cantidad'] >= 2): ?>
      <svg viewBox="0 0 660 182" role="img" aria-label="Evolución promedio de {{ $metaGrafico['nombre'] }}">
       <line x1="35" y1="155" x2="625" y2="155" stroke="#b8c7d7"/>
       <line x1="35" y1="40" x2="625" y2="40" stroke="#e2e8f0" stroke-dasharray="4 4"/>
       <text x="2" y="44" font-size="12" fill="#64748b">{{ number_format($graficos[$codigoGrafico]['maximo'],2) }}</text>
       <text x="2" y="158" font-size="12" fill="#64748b">{{ number_format($graficos[$codigoGrafico]['minimo'],2) }}</text>
       <polyline points="{{ $graficos[$codigoGrafico]['puntos'] }}" fill="none" stroke="{{ $coloresGraficos[$codigoGrafico] }}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
       <text x="35" y="177" font-size="12" fill="#64748b">{{ $primeraFechaGrafico }}</text>
       <text x="572" y="177" font-size="12" fill="#64748b">{{ $ultimaFechaGrafico }}</text>
      </svg>
     <?php else: ?>
      <div class="muted small" style="padding:30px 0">Se requieren al menos dos capturas con precios observados de este combustible para graficar.</div>
     <?php endif; ?>
     <div class="foot">Promedio de competidores a 5 km, excluyendo Munichis. No se inventan precios faltantes.</div>
    </div>
   <?php endforeach; ?>
  </div>
 </section>
 <section class="section"><div class="section-head"><h2>Estadísticas mensuales y anuales</h2><span class="fine">Promedio de promedios por captura · competidores a 5 km</span></div>
 <div class="chart-grid">
 <?php foreach (['meses'=>'Resumen mensual','anios'=>'Resumen anual'] as $tipo=>$titulo): ?>
 <div class="table-wrap"><strong>{{ $titulo }}</strong><table style="min-width:560px"><thead><tr><th>Período</th>
 <?php foreach ($catalogo as $metaPeriodo): ?><th class="num" style="text-align:right">{{ $metaPeriodo['nombre'] }}</th><?php endforeach; ?>
 <th style="text-align:right">Capturas</th></tr></thead><tbody>
 <?php if (!empty($periodos[$tipo])): foreach ($periodos[$tipo] as $periodo=>$r): ?>
 <tr><td>{{ $periodo }}</td>
 <?php $conteosPeriodo = []; foreach ($catalogo as $codigoPeriodo=>$metaPeriodo): $conteosPeriodo[] = $r[$codigoPeriodo.'_capturas'] ?? 0; ?>
 <td class="num" title="{{ $r[$codigoPeriodo.'_capturas'] ?? 0 }} capturas con precio de este producto">{{ ($r[$codigoPeriodo] ?? null) === null ? '—' : 'S/ '.number_format($r[$codigoPeriodo],2) }}</td>
 <?php endforeach; ?><td class="num">{{ max($conteosPeriodo) }}</td></tr>
 <?php endforeach; else: ?><tr><td colspan="6">Sin información.</td></tr><?php endif; ?>
 </tbody></table></div>
 <?php endforeach; ?>
 </div><div class="foot">Cada promedio usa únicamente capturas con ese producto disponible. La cantidad de capturas puede variar entre combustibles. Son promedios observados, no ponderados por ventas ni valores interpolados.</div></section>

 </div>
 <div id="cmp-panel-historial" role="tabpanel" aria-labelledby="cmp-tab-historial" data-cmp-panel="historial" hidden>
 <section class="section"><div class="section-head"><h2>Historial de capturas</h2><span class="fine">Las capturas válidas son inmutables; nunca se reemplazan precios anteriores.</span></div>
 <div class="table-wrap"><table><thead><tr><th>Jornada</th><th>Consulta</th><th>Origen</th><th>Estaciones</th><th>Precios</th><th>Observaciones</th></tr></thead><tbody>
 <?php if ($capturas->isNotEmpty()): foreach ($capturas as $c): ?><tr><td><a href="{{ route('competencia.indice',['captura'=>$c->id,'zona'=>$zona]) }}">{{ \Carbon\Carbon::parse($c->fecha_jornada)->format('d/m/Y') }}</a></td><td>{{ \Carbon\Carbon::parse($c->fecha_consulta)->format('H:i') }}</td><td>{{ $c->origen }}</td><td>{{ $c->establecimientos_guardados }}</td><td>{{ $c->precios_guardados }}</td><td>{{ $c->advertencia ?: 'Completa' }}</td></tr><?php endforeach; else: ?><tr><td colspan="6">Sin capturas registradas.</td></tr><?php endif; ?>
 </tbody></table></div>
 </section>
 <section class="section" aria-labelledby="cmp-hist-grifo">
  <div class="section-head"><div><h2 id="cmp-hist-grifo">Historial por grifo</h2><div class="foot">Hasta 40 observaciones reales por código Osinergmin. Incluye los cuatro combustibles, si se reportaron.</div></div></div>
  <?php if ($captura): ?>
  <form method="GET" action="{{ route('competencia.indice') }}#historial" class="hist-selector">
   <input type="hidden" name="captura" value="{{ $captura->id }}">
   <input type="hidden" name="zona" value="{{ $zona }}">
   <div><label for="cmp-grifo-hist">Seleccionar establecimiento</label><select id="cmp-grifo-hist" name="grifo" required>
    <option value="">Elegir grifo...</option>
    <?php foreach ($opcionesGrifos as $opcion): ?><option value="{{ $opcion->codigo }}" <?php echo ($grifoSeleccionado && (string) $grifoSeleccionado->codigo === (string) $opcion->codigo) ? 'selected' : ''; ?>>{{ $opcion->nombre }} ({{ $opcion->codigo }})</option><?php endforeach; ?>
   </select></div><button class="btn" type="submit">Ver historial</button>
  </form>
  <?php if ($grifoSeleccionado): ?>
   <div class="notice"><strong>{{ $grifoSeleccionado->nombre }}</strong> · Código {{ $grifoSeleccionado->codigo }} · {{ count($historicoGrifo) }} observaciones recuperadas.</div>
   <div class="table-wrap"><table style="min-width:820px" aria-label="Historial de precios del establecimiento seleccionado"><thead><tr><th>Fecha observada</th><?php foreach ($catalogo as $metaHist): ?><th class="num">{{ $metaHist['nombre'] }}</th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($historicoGrifo as $registroHist): ?><tr><td><strong>{{ \Carbon\Carbon::parse($registroHist['fecha'])->format('d/m/Y') }}</strong></td>
     <?php foreach ($catalogo as $codigoHist => $metaHist): ?>
      <?php $precioHist = $registroHist['precios'][$codigoHist] ?? null; $deltaHist = $registroHist['variaciones'][$codigoHist] ?? null; ?>
      <td class="num">{{ $precioHist === null ? '—' : 'S/ '.number_format($precioHist,2) }}
       <?php if ($deltaHist !== null): ?><span class="hist-delta {{ $deltaHist > 0 ? 'subida' : 'bajada' }}">{{ $deltaHist > 0 ? '↑ +' : '↓ −' }}S/ {{ number_format(abs($deltaHist),2) }}</span><?php endif; ?>
      </td>
     <?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="foot">La variación se calcula frente a la observación anterior disponible de este mismo grifo; no se inventan valores para productos ausentes ni se afirma la fecha exacta de una modificación.</div>
  <?php else: ?><div class="empty">Selecciona un establecimiento para ver sus precios históricos.</div><?php endif; ?>
  <?php else: ?><div class="empty">Registra una primera captura antes de consultar el historial por grifo.</div><?php endif; ?>
 </section>

 </div>
 <?php if (auth()->user()->rol==='ADMINISTRADOR'): ?>
 <div id="cmp-panel-admin" role="tabpanel" aria-labelledby="cmp-tab-admin" data-cmp-panel="admin" hidden>
  <section class="section"><div class="section-head"><h2>Importar respaldo JSON</h2><span class="status-chip">Solo administrador</span></div>
   <p class="muted small">Usa esta opción únicamente para recuperar una observación real de SoapUI. Se permite una captura válida por fecha de consulta, sin límite semanal.</p>
  <form method="POST" action="{{ route('competencia.importar') }}" enctype="multipart/form-data" class="import"><input type="hidden" name="_token" value="{{ csrf_token() }}"><div><label for="cmp-file">Importar respaldo JSON de SoapUI (máximo 4 MB)</label><input id="cmp-file" name="archivo" type="file" accept=".json,.txt,application/json" required></div><button type="submit" class="btn alt">Importar captura</button></form>
  <div class="foot">Solo para respaldo o recuperación de una observación real, como el JSON inicial del 29/10/2026. Si ya existe una captura válida de esa fecha, no se importa otra. No sobrescribe jornadas ni cambia precios de venta. El token permanece en el servidor.</div>

  </section>
 <section class="section"><div class="section-head"><h2>Últimos intentos de actualización</h2><span class="fine">Auditoría técnica de capturas correctas y fallidas</span></div><div class="table-wrap"><table><thead><tr><th>Jornada</th><th>Origen</th><th>Estado</th><th>Detalle</th></tr></thead><tbody><?php if ($intentos->isNotEmpty()): foreach ($intentos as $i): ?><tr><td>{{ $i->fecha_jornada }}</td><td>{{ $i->origen }}</td><td>{{ $i->estado }}</td><td>{{ $i->detalle }}</td></tr><?php endforeach; else: ?><tr><td colspan="4">Todavía no existen intentos.</td></tr><?php endif; ?></tbody></table></div></section>
 </div>
 <?php endif; ?>
</div>
<script>
(function(){
  var raiz=document.querySelector('.cmp');
  if(!raiz){return;}
  var tabs=Array.prototype.slice.call(raiz.querySelectorAll('[data-cmp-tab]'));
  var panels=Array.prototype.slice.call(raiz.querySelectorAll('[data-cmp-panel]'));
  function activar(nombre, actualizarUrl){
    if(!tabs.some(function(t){return t.getAttribute('data-cmp-tab')===nombre;})){nombre='resumen';}
    tabs.forEach(function(tab){var activo=tab.getAttribute('data-cmp-tab')===nombre;tab.setAttribute('aria-selected', activo?'true':'false');tab.setAttribute('tabindex',activo?'0':'-1');});
    panels.forEach(function(panel){panel.hidden=panel.getAttribute('data-cmp-panel')!==nombre;});
    if(actualizarUrl&&window.history&&window.history.replaceState){window.history.replaceState(null,'','#'+nombre);}
  }
  tabs.forEach(function(t){t.addEventListener('click',function(){activar(t.getAttribute('data-cmp-tab'),true);});});
  var buscador=raiz.querySelector('#cmp-buscar-est');
  var tabla=raiz.querySelector('#cmp-comparador');
  var contador=raiz.querySelector('#cmp-buscar-contador');
  if(buscador&&tabla){
    var filas=Array.prototype.slice.call(tabla.querySelectorAll('tbody tr'));
    var filtrar=function(){
      var texto=buscador.value.trim().toLocaleLowerCase();
      var visibles=0;
      filas.forEach(function(fila){
        var coincide=fila.cells[0].textContent.toLocaleLowerCase().indexOf(texto)!==-1 ||
          fila.cells[1].textContent.toLocaleLowerCase().indexOf(texto)!==-1;
        fila.hidden=!coincide;
        if(coincide) visibles++;
      });
      if(contador) contador.textContent=visibles+' de '+filas.length+' establecimientos';
    };
    buscador.addEventListener('input',filtrar);
    filtrar();
  }
  activar((window.location.hash||'').replace('#',''),false);
})();
</script>
@endsection
