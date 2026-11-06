/* Grafico a barre impilate: ore per stato per giorno. Colori dalle variabili CSS (chiaro/scuro). */
(function () {
  const STATI = [
    ['MARCIA', 'In funzione', '--s-marcia'],
    ['RISCALDAMENTO', 'Riscaldamento', '--s-riscaldamento'],
    ['RAFFREDDAMENTO', 'Raffreddamento', '--s-raffreddamento'],
    ['AVVIO', 'Avvio / setup', '--s-avvio'],
    ['ALLARME', 'Allarme', '--s-allarme'],
    ['FERMO', 'Ferma', '--s-fermo'],
    ['NON_CLASSIFICATO', 'Non classificato', '--s-non-classificato'],
    ['SENZA_DATO', 'Senza dato', '--s-senza-dato'],
  ];
  const v = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  const fmt = (x) => x.toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

  const grafici = [];
  const disegna = () => {
  grafici.splice(0).forEach((g) => g.dispose());
  document.querySelectorAll('.grafico[id^="grafico-"]').forEach((el) => {
    const n = el.id.split('-')[1];
    const dati = JSON.parse(document.getElementById('dati-' + n).textContent);
    const serie = STATI.filter(([k]) => (dati[k] || []).some((x) => x > 0)).map(([k, nome, colore]) => ({
      name: nome, type: 'bar', stack: 'ore', data: dati[k], barMaxWidth: 28,
      itemStyle: { color: v(colore), borderColor: v('--superficie'), borderWidth: 1,
        decal: k === 'SENZA_DATO' || k === 'NON_CLASSIFICATO' ? { symbol: 'rect', dashArrayX: [1, 0], dashArrayY: [2, 3], rotation: k === 'SENZA_DATO' ? -Math.PI / 4 : Math.PI / 4, color: v('--superficie') } : null },
      emphasis: { focus: 'series' },
    }));
    const grafico = echarts.init(el, null, { renderer: 'svg' });
    grafico.setOption({
      animation: false,
      grid: { left: 40, right: 12, top: 12, bottom: 28 },
      tooltip: {
        trigger: 'axis', axisPointer: { type: 'shadow' },
        valueFormatter: (x) => fmt(x) + ' h',
        backgroundColor: v('--superficie'), borderColor: v('--bordo'), textStyle: { color: v('--testo') },
      },
      xAxis: { type: 'category', data: dati.giorni, axisLabel: { color: v('--testo-3') }, axisLine: { lineStyle: { color: v('--bordo') } } },
      yAxis: { type: 'value', max: 24, interval: 6, axisLabel: { color: v('--testo-3'), formatter: '{value} h' }, splitLine: { lineStyle: { color: v('--bordo') } } },
      series: serie,
    });
    grafici.push(grafico);
  });
  };
  disegna();
  window.addEventListener('resize', () => grafici.forEach((g) => g.resize()));
  // Cambio tema: si ridisegna con i colori del nuovo tema
  document.addEventListener('tema-cambiato', disegna);
})();
