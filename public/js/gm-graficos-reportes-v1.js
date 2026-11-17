/* Grifo Munichis · gráficos de reportes v1.0.0 · sin dependencias externas */
(() => {
  'use strict';
  const contenedor = document.getElementById('gm-graficos-interactivos');
  const datos = document.getElementById('gm-datos-graficos');
  if (!contenedor || !datos) return;
  let cfg;
  try { cfg = JSON.parse(datos.textContent); } catch (_) { return; }
  const filas = cfg.registros || [];
  const dimensiones = cfg.dimensiones || [];
  const metricas = cfg.metricas || [];
  const temporal = !!cfg.temporal;
  const controles = contenedor.querySelector('#gm-grafico-filtros');
  const paneles = contenedor.querySelector('#gm-grafico-paneles');
  const aviso = contenedor.querySelector('#gm-grafico-aviso');
  const colores = ['#086a3b','#1565aa','#e48526','#8150b4','#db4765','#087f85','#a2741b','#456078','#477e2b','#b04c29','#885889','#1d86bc'];
  const nf = (n, digitos = 2) => Number(n).toLocaleString('es-PE', {minimumFractionDigits:digitos, maximumFractionDigits:digitos});
  const e = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const unidad = u => u === 'S/' ? 'S/ ' : u === 'S/ por gal' ? 'S/ ' : '';
  const sufijo = u => ['S/','S/ por gal'].includes(u) ? (u === 'S/ por gal' ? ' /gal' : '') : ` ${u}`;
  const valor = (n, u) => unidad(u) + nf(n, u === 'gal' ? 3 : ['turnos','cortes','operaciones'].includes(u) ? 0 : 2) + sufijo(u);
  const ordenar = (a, b) => String(a).localeCompare(String(b),'es',{numeric:true,sensitivity:'base'});
  const tituloFecha = f => f && /^\d{4}-\d{2}-\d{2}$/.test(f) ? f.slice(8,10)+'/'+f.slice(5,7)+'/'+f.slice(0,4) : f;
  const valorSelect = (id) => contenedor.querySelector('#'+id)?.value || '*';

  function campoSelect(id, titulo, opciones, valorInicial = '*') {
    return `<label class="gm-grafico-campo"><span>${e(titulo)}</span><select id="${e(id)}" aria-label="${e(titulo)}">`+
      opciones.map(o=>`<option value="${e(o.valor)}"${o.valor === valorInicial ? ' selected' : ''}>${e(o.nombre)}</option>`).join('')+
      `</select></label>`;
  }
  const alternativas = (campo) => [...new Set(filas.map(f=>f[campo]).filter(v=>v !== undefined && v !== null && v !== ''))].sort(ordenar);

  let formulario = campoSelect('gm-metrica','Indicador', [{valor:'*',nombre:'Todos'}, ...metricas.map(m=>({valor:m.campo,nombre:m.nombre}))]);
  for (const dim of dimensiones) {
    formulario += campoSelect('gm-filtrar-'+dim.campo, dim.nombre,
      [{valor:'*',nombre:'Todos'}, ...alternativas(dim.campo).map(x=>({valor:x,nombre:x}))]);
  }
  const nombreTodos = ['precios','competencia','trabajadores'].includes(cfg.tipo) ? 'Todos (desglosados)' : 'Todos (consolidado)';
  const por = [{valor:'*',nombre:nombreTodos},...dimensiones.map(d=>({valor:d.campo,nombre:d.nombre}))];
  formulario += campoSelect('gm-comparar','Comparar por',por,'*');
  if (temporal) {
    const fechas = filas.map(f=>f.fecha).filter(Boolean).sort();
    const dias = fechas.length ? Math.round((new Date(fechas[fechas.length-1]+'T00:00:00Z') - new Date(fechas[0]+'T00:00:00Z'))/86400000) : 0;
    const inicio = dias > 180 ? 'mes' : dias > 45 ? 'semana' : 'dia';
    formulario += campoSelect('gm-agrupar','Agrupar fechas',[
      {valor:'dia',nombre:'Diario'}, {valor:'semana',nombre:'Semanal'},
      {valor:'mes',nombre:'Mensual'}, {valor:'todo',nombre:'Todos (período completo)'},
    ],inicio);
  }
  controles.innerHTML = formulario;
  controles.querySelectorAll('select').forEach(x=>x.addEventListener('change',dibujar));

  function bucket(fecha, intervalo) {
    if (intervalo === 'todo') return 'Período completo';
    if (intervalo === 'mes') return fecha.slice(0,7);
    if (intervalo === 'semana') {
      const d = new Date(fecha+'T00:00:00Z');
      d.setUTCDate(d.getUTCDate()-((d.getUTCDay()+6)%7));
      return d.toISOString().slice(0,10);
    }
    return fecha;
  }
  function etiqueta(b, intervalo) {
    if (intervalo === 'todo') return 'Todo el período';
    if (intervalo === 'mes') return b.slice(5,7)+'/'+b.slice(0,4);
    return intervalo === 'semana' ? 'Sem. '+tituloFecha(b).slice(0,5) : tituloFecha(b);
  }
  function filasFiltradas() {
    return filas.filter(f=>dimensiones.every(d=> {
      const s = valorSelect('gm-filtrar-'+d.campo);
      return s === '*' || String(f[d.campo] ?? '') === s;
    }));
  }
  function obtenerSeries(filtradas, grupoMetricas, comparar, intervalo) {
    const porPeriodo = new Map(), dias = new Map(), detalles = new Map();
    const esComparacion = !temporal;
    for (const m of grupoMetricas) {
      for (const [i,f] of filtradas.entries()) {
        const numero = Number(f[m.campo]);
        if (f[m.campo] === null || f[m.campo] === undefined || !Number.isFinite(numero)) continue;
        let categoria = comparar === '*' ? '' : String(f[comparar] || 'Sin especificar');
        if (cfg.tipo === 'trabajadores' && comparar === '*') categoria = String(f.trabajador || 'Sin especificar');
        // Los precios de combustibles distintos nunca se promedian bajo «Todos».
        if (cfg.tipo === 'precios' && comparar === '*') categoria = String(f.producto || 'Sin especificar');
        if (cfg.tipo === 'competencia') {
          const producto = String(f.producto || 'Sin producto');
          const grifo = String(f.grifo || 'Sin grifo');
          const todosProductos = valorSelect('gm-filtrar-producto') === '*';
          const todosGrifos = valorSelect('gm-filtrar-grifo') === '*';
          if (comparar === '*') categoria = (todosGrifos ? grifo : '') + (todosGrifos && todosProductos ? ' · ' : '') + (todosProductos ? producto : '');
          else if (comparar === 'grifo' && todosProductos) categoria = grifo+' · '+producto;
          else if (comparar === 'producto' && todosGrifos) categoria = producto+' · '+grifo;
        }
        const nomSerie = esComparacion ? m.nombre : m.nombre + (categoria ? ' · '+categoria : '');
        const x = esComparacion ? (categoria || 'Todos') : bucket(f.fecha,intervalo);
        const llave = JSON.stringify([x,nomSerie]);
        detalles.set(nomSerie,{nombre:nomSerie,metrica:m});
        if (m.modo === 'ultimo') {
          const dia = JSON.stringify([x,nomSerie,f.fecha]);
          // Los registros de precio y caja llegan ordenados del más reciente al más antiguo.
          if (cfg.tipo === 'precios' || cfg.tipo === 'competencia' || cfg.tipo === 'caja') {
            if (!dias.has(dia)) dias.set(dia,{llave,fecha:f.fecha,numero});
          } else {
            const previo = dias.get(dia);
            dias.set(dia,{llave,fecha:f.fecha,numero:(previo?.numero || 0)+numero});
          }
        } else {
          porPeriodo.set(llave,(porPeriodo.get(llave)||0)+numero);
        }
      }
    }
    // Los saldos y precios son fotografías: escoger el último día del intervalo,
    // nunca sumar existencias de diferentes fechas.
    const ultimos = new Map();
    for (const v of dias.values()) {
      const actual = ultimos.get(v.llave);
      if (!actual || v.fecha > actual.fecha) ultimos.set(v.llave,v);
    }
    for (const [k,v] of ultimos) porPeriodo.set(k,v.numero);
    const todosX = [...new Set([...porPeriodo.keys()].map(k=>JSON.parse(k)[0]))].sort(esComparacion ? ordenar : undefined);
    const nombresSeries = [...new Set([...porPeriodo.keys()].map(k=>JSON.parse(k)[1]))].sort(ordenar);
    // En análisis por trabajador, destacar primero quienes presentan valores más altos.
    if (esComparacion) {
      todosX.sort((a,b)=>{
        const sumar = x => nombresSeries.reduce((t,s)=>t+(porPeriodo.get(JSON.stringify([x,s]))||0),0);
        return sumar(b)-sumar(a) || ordenar(a,b);
      });
    }
    // Comparaciones grandes no deben ocultar el total; se muestra una advertencia si
    // el usuario decide desagregar en más de 14 series.
    let seriesVisibles = nombresSeries;
    let recorte = false;
    if (nombresSeries.length > 14 && !esComparacion) {
      recorte = true;
      seriesVisibles = nombresSeries.map(s=>({s,total:todosX.reduce((a,x)=>a+Math.abs(porPeriodo.get(JSON.stringify([x,s]))||0),0)}))
        .sort((a,b)=>b.total-a.total).slice(0,14).map(v=>v.s).sort(ordenar);
    }
    const series = seriesVisibles.map((s,i)=>({
      nombre:s, color:colores[i%colores.length],
      metrica:detalles.get(s).metrica,
      puntos:todosX.map(x=>{
        const key=JSON.stringify([x,s]);
        return porPeriodo.has(key) ? porPeriodo.get(key) : null;
      }),
    }));
    return {x:todosX,series,recorte};
  }

  function lineaSvg(x1,y1,x2,y2,color='#e3e9e7',grosor=1,extra='') {
    return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${color}" stroke-width="${grosor}" ${extra}/>`;
  }
  function graficoSvg(estructura, u, tipo, intervalo) {
    const labels = estructura.x, series = estructura.series;
    if (!labels.length || !series.length) return '<p class="gm-sin-datos">No hay registros con valores para esta selección.</p>';
    const barras = !temporal || intervalo === 'todo';
    const ancho = Math.max(620, barras ? labels.length * Math.max(48,Math.min(85,250/Math.max(1,series.length)))+125 : labels.length*38+100);
    const alto = 330, izquierda=88, derecha=32, arriba=20, abajo=76;
    const pw=ancho-izquierda-derecha, ph=alto-arriba-abajo;
    const valores=series.flatMap(s=>s.puntos).filter(v=>v !== null && Number.isFinite(v));
    if (!valores.length) return '<p class="gm-sin-datos">No hay valores para graficar.</p>';
    let minimo=Math.min(0,...valores), maximo=Math.max(0,...valores);
    if (minimo === maximo) maximo=minimo+1;
    if (tipo === 'precios' || tipo === 'competencia') {
      const bajo=Math.min(...valores), altoValor=Math.max(...valores);
      const margen=Math.max(0.15,(altoValor-bajo)*.15);
      minimo=Math.max(0,bajo-margen); maximo=altoValor+margen;
    } else {
      const margen=(maximo-minimo)*.08;
      maximo+=margen; if(minimo<0) minimo-=margen;
    }
    const y=v=>arriba+(maximo-v)/(maximo-minimo)*ph;
    const posX=i=>izquierda+(i+.5)*pw/labels.length;
    let svg=`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${ancho} ${alto}" width="${ancho}" height="${alto}" role="img" aria-label="Gráfico ${e(u)}">`;
    for (let i=0;i<=4;i++) {
      const v=minimo+(maximo-minimo)*i/4, yy=y(v);
      svg+=lineaSvg(izquierda,yy,ancho-derecha,yy);
      svg+=`<text x="${izquierda-8}" y="${yy+4}" text-anchor="end" class="gm-grafico-eje">${e(nf(v,Math.abs(maximo-minimo)<15?2:0))}</text>`;
    }
    if(minimo<0 && maximo>0) svg+=lineaSvg(izquierda,y(0),ancho-derecha,y(0),'#82938c',1.5);
    const salto = Math.max(1,Math.ceil(labels.length/12));
    labels.forEach((label,i)=> {
      if(i%salto !== 0 && i !== labels.length-1) return;
      const x=posX(i);
      const nombre=temporal ? etiqueta(label,intervalo) : label;
      const corto=nombre.length>17 ? nombre.slice(0,15)+'…' : nombre;
      svg+=`<text x="${x}" y="${alto-abajo+19}" text-anchor="end" transform="rotate(-35 ${x} ${alto-abajo+19})" class="gm-grafico-eje"><title>${e(nombre)}</title>${e(corto)}</text>`;
    });
    if (barras) {
      const anchoGrupo=pw/labels.length;
      const bw=Math.max(2,Math.min(40,anchoGrupo*.75/series.length));
      series.forEach((s,si)=>s.puntos.forEach((v,i)=> {
        if(v===null) return;
        const cx=posX(i)+(si-(series.length-1)/2)*bw;
        const top=Math.min(y(0),y(v)), height=Math.max(1,Math.abs(y(v)-y(0)));
        const label=temporal ? etiqueta(labels[i],intervalo) : labels[i];
        svg+=`<rect x="${cx-bw*.46}" y="${top}" width="${bw*.92}" height="${height}" rx="2" fill="${s.color}" opacity=".92"><title>${e(label)} · ${e(s.nombre)}: ${e(valor(v,u))}</title></rect>`;
      }));
    } else {
      for(const s of series) {
        let tramo=[];
        const segmentos=[];
        s.puntos.forEach((v,i)=>{
          if(v===null) { if(tramo.length) segmentos.push(tramo); tramo=[]; }
          else tramo.push([posX(i),y(v)]);
        });
        if(tramo.length) segmentos.push(tramo);
        for(const puntos of segmentos) {
          svg+=`<polyline fill="none" stroke="${s.color}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="${puntos.map(p=>p.join(',')).join(' ')}"/>`;
        }
        s.puntos.forEach((v,i)=>{
          if(v===null) return;
          const x=posX(i), yy=y(v);
          svg+=`<circle cx="${x}" cy="${yy}" r="4" stroke="#fff" stroke-width="1.1" fill="${s.color}"><title>${e(etiqueta(labels[i],intervalo))} · ${e(s.nombre)}: ${e(valor(v,u))}</title></circle>`;
        });
      }
    }
    svg+='</svg>';
    return `<div class="gm-grafico-desplazar" tabindex="0" aria-label="Desplazar gráfico horizontalmente cuando sea necesario">${svg}</div>`;
  }

  function dibujar() {
    const subset=filasFiltradas(), metricasElegidas=metricas.filter(m=>valorSelect('gm-metrica')==='*' || valorSelect('gm-metrica')===m.campo);
    const comparar=valorSelect('gm-comparar'), intervalo=temporal ? valorSelect('gm-agrupar') : 'todo';
    const porUnidad=new Map();
    for(const m of metricasElegidas) {
      if(!porUnidad.has(m.unidad)) porUnidad.set(m.unidad,[]);
      porUnidad.get(m.unidad).push(m);
    }
    if(!subset.length || !metricasElegidas.length) {
      paneles.innerHTML='<p class="gm-sin-datos">No hay registros en esta selección. Prueba «Todos» o modifica el período.</p>';
      aviso.textContent='';
      return;
    }
    let html='';
    for(const [u,ms] of porUnidad) {
      const estructura=obtenerSeries(subset,ms,comparar,intervalo);
      const cantidad=estructura.x.length;
      const totalPorSerie=estructura.series.map(s=>{
        const nums=s.puntos.filter(v=>v!==null);
        const resultado=s.metrica.modo==='ultimo' ? (nums[nums.length-1]??0) : nums.reduce((a,b)=>a+b,0);
        return {nombre:s.nombre,total:resultado};
      });
      const clase=(ms.length===1 ? ms[0].nombre : 'Comparación de indicadores');
      html+=`<article class="gm-grafico-panel"><div class="gm-grafico-panel-cabecera"><div><h3>${e(clase)}</h3><p>${cantidad} ${temporal ? 'intervalos con registros' : 'categorías con registros'} · ${e(u)} · ${e(ms[0].modo==='ultimo' ? 'Último registro del intervalo' : 'Acumulado del intervalo')}</p></div></div>`;
      html+=graficoSvg(estructura,u,cfg.tipo,intervalo);
      html+=`<div class="gm-grafico-leyenda">${estructura.series.map(s=>`<span title="${e(s.nombre)}"><i style="background:${s.color}"></i>${e(s.nombre)}</span>`).join('')}</div>`;
      if(totalPorSerie.length && totalPorSerie.length<=6) html+=`<div class="gm-grafico-indicadores">${totalPorSerie.map(t=>`<div><small>${e(t.nombre)}</small><b>${e(valor(t.total,u))}</b></div>`).join('')}</div>`;
      if(estructura.recorte) html+='<p class="gm-grafico-advertencia">Para mantener legible la comparación, se muestran las 14 series de mayor magnitud. Selecciona una categoría para verla individualmente.</p>';
      html+='</article>';
    }
    paneles.innerHTML=html;
    aviso.textContent=cfg.nota || '';
  }
  dibujar();
})();
