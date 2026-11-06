/* Pulsante tema: automatico (segue il sistema) → chiaro → scuro. Scelta salvata nel browser. */
(function () {
  const ordine = ['auto', 'light', 'dark'];
  const nomi = { auto: 'Tema: automatico', light: 'Tema: chiaro', dark: 'Tema: scuro' };
  const leggi = () => { try { return localStorage.getItem('tema') || 'auto'; } catch (e) { return 'auto'; } };
  const scrivi = (t) => { try { t === 'auto' ? localStorage.removeItem('tema') : localStorage.setItem('tema', t); } catch (e) {} };
  const applica = (t) => {
    if (t === 'auto') delete document.documentElement.dataset.theme; else document.documentElement.dataset.theme = t;
    document.querySelectorAll('[data-tema-etichetta]').forEach((el) => { el.textContent = nomi[t]; });
    document.dispatchEvent(new CustomEvent('tema-cambiato'));
  };
  applica(leggi());
  document.querySelectorAll('[data-tema-pulsante]').forEach((b) => b.addEventListener('click', () => {
    const t = ordine[(ordine.indexOf(leggi()) + 1) % ordine.length];
    scrivi(t);
    applica(t);
  }));
  // In automatico, segue anche il cambio di tema del sistema operativo
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (leggi() === 'auto') applica('auto'); });
})();
