(() => {
  function tokenCsrf(){return document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '';}
  function idDesdeFila(row){
    for(const a of row.querySelectorAll('a[href]')){
      const m=a.getAttribute('href').match(/\/caja\/(?:control\/)?(\d+)(?:\/editar)?(?:$|[?#])/i) || a.getAttribute('href').match(/\/gestion\/caja\/(\d+)\/editar/i);
      if(m) return m[1];
    }
    return null;
  }
  function crearDialogo(){
    let d=document.getElementById('gm-modal-eliminar-caja'); if(d) return d;
    d=document.createElement('dialog'); d.id='gm-modal-eliminar-caja'; d.className='gm-modal-caja';
    d.innerHTML=`<form method="dialog" id="gm-form-eliminar-caja"><h3>Eliminar control de caja</h3><p>El registro se archivará en auditoría y los controles posteriores dependientes se recalcularán.</p><label style="display:block;font-weight:700;margin-bottom:6px">Motivo de eliminación *</label><textarea name="motivo_eliminacion" minlength="5" maxlength="500" required></textarea><div class="gm-modal-acciones"><button type="button" class="boton boton-secundario" data-cancelar>Cancelar</button><button type="submit" class="boton boton-primario" style="background:#b91c1c">Eliminar control</button></div></form>`;
    document.body.appendChild(d); d.querySelector('[data-cancelar]').addEventListener('click',()=>d.close()); return d;
  }
  function enviar(id,motivo){
    const f=document.createElement('form'); f.method='POST'; f.action=`/caja/control/${id}/eliminar`; f.style.display='none';
    f.innerHTML=`<input type="hidden" name="_token" value="${tokenCsrf().replace(/"/g,'&quot;')}"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="motivo_eliminacion">`;
    f.querySelector('[name="motivo_eliminacion"]').value=motivo; document.body.appendChild(f); f.submit();
  }
  document.addEventListener('DOMContentLoaded',()=>{
    if(!location.pathname.includes('caja')) return;
    const dialog=crearDialogo(); const form=dialog.querySelector('form'); let actual=null;
    document.querySelectorAll('table tbody tr').forEach(row=>{
      const id=idDesdeFila(row); if(!id) return;
      const celda=row.lastElementChild || row.insertCell();
      if(celda.querySelector('.gm-btn-eliminar-caja')) return;
      const b=document.createElement('button'); b.type='button'; b.className='gm-btn-eliminar-caja'; b.textContent='Eliminar'; b.addEventListener('click',()=>{actual=id;form.reset();dialog.showModal();}); celda.appendChild(b);
    });
    form.addEventListener('submit',e=>{e.preventDefault();const motivo=form.elements.motivo_eliminacion.value.trim();if(motivo.length<5){form.elements.motivo_eliminacion.reportValidity();return;}dialog.close();enviar(actual,motivo);});
  });
})();
