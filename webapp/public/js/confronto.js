/* Barre affiancate: ore in funzione medie al giorno, periodo A contro periodo B, per macchina. */
(function () {
  const el = document.getElementById('grafico-confronto');
  if (!el) return;
  const dati = JSON.parse(document.getElementById('dati-confronto').textContent);
  const v = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  const fmt = (x) => (x === null || x === undefined) ? 'n.d.' : x.toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' h';
  let grafico = null;
  const disegna = () => {
    if (grafico) grafico.dispose();
    grafico = echarts.init(el, null, { renderer: 'svg' });
    const serie = (nome, valori, colore) => ({
      name: nome, type: 'bar', data: valori, barMaxWidth: 36, barGap: '8%',
      itemStyle: { color: v(colore), borderRadius: [4, 4, 0, 0], borderColor: v('--superficie'), borderWidth: 1 },
      label: { show: true, position: 'top', color: v('--testo-2'), formatter: (p) => fmt(p.value) },
    });
    grafico.setOption({
      animation: false,
      grid: { left: 40, right: 12, top: 24, bottom: 28 },
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: fmt,
        backgroundColor: v('--superficie'), borderColor: v('--bordo'), textStyle: { color: v('--testo') } },
      xAxis: { type: 'category', data: dati.macchine, axisLabel: { color: v('--testo-2') }, axisLine: { lineStyle: { color: v('--bordo') } } },
      yAxis: { type: 'value', min: 0, axisLabel: { color: v('--testo-3'), formatter: '{value} h' }, splitLine: { lineStyle: { color: v('--bordo') } } },
      series: [serie('Periodo A', dati.a, '--serie-1'), serie('Periodo B', dati.b, '--serie-2')],
    });
  };
  disegna();
  window.addEventListener('resize', () => grafico && grafico.resize());
  document.addEventListener('tema-cambiato', disegna);
})();
