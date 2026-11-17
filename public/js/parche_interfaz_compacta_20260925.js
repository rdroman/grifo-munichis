(() => {
  const txt = el => (el?.textContent || '').replace(/\s+/g,' ').trim();
  const quitarSiCoincide = (selector, patrones) => {
    document.querySelectorAll(selector).forEach(el => {
      const t = txt(el);
      if (patrones.some(p => t.includes(p))) el.remove();
    });
  };
  document.addEventListener('DOMContentLoaded', () => {
    quitarSiCoincide('p.texto-secundario, small, .alerta', [
      'La fecha y el tipo de turno se determinan automáticamente',
      'Determinada automáticamente por la secuencia operativa',
      'Siguiente turno regular obligatorio',
      'Inventario teórico, medición física y diferencias por producto',
      'Saldo teórico, medición física y diferencias por producto',
      'Los movimientos y el saldo teórico se calculan automáticamente',
      'Los precios se cargan automáticamente. Como ADMINISTRADOR',
      'La secuencia se toma del turno operativo real',
      'Se determina automáticamente a partir del turno',
      'El sistema obtiene el precio vigente automáticamente'
    ]);
    document.querySelectorAll('textarea[name="observaciones"]').forEach(el => el.rows = 2);

    if (location.pathname.includes('/tanques')) {
      document.querySelectorAll('.tarjeta').forEach(card => {
        const h = card.querySelector('h2'); if (!h) return;
        const titulo = txt(h).toLowerCase();
        card.querySelectorAll('small,.texto-secundario').forEach(el => {
          const t = txt(el).toLowerCase();
          if (t && titulo.includes(t.replace(/^tanque\s+/,''))) el.remove();
        });
      });
    }
  });
})();
