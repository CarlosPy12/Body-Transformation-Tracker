const state = { user: null, csrf: null, active: 'riepilogo', range: '3m', rangeStart: '', rangeEnd: '', metric: 'peso', dashboardMetric: 'peso', charts: {}, deferredInstall: null, sidebarCollapsed: false, theme: localStorage.getItem('kinetica-theme') || 'dark' };
const sections = [
  ['aggiungi', 'Aggiungi attività', 'plus', 'Aggiungi'],
  ['riepilogo', 'Riepilogo', 'home'],
  ['iniezioni', 'Iniezioni', 'syringe'],
  ['attivita', 'Attività fisica', 'dumbbell', 'Attività'],
  ['risultati', 'Risultati', 'chart'],
  ['calendario', 'Calendario', 'calendar'],
  ['impostazioni', 'Impostazioni', 'settings']
];
const navGroups = [
  { label: 'Azioni', items: ['aggiungi'] },
  { label: 'Panoramica', items: ['riepilogo'] },
  { label: 'Monitoraggio', items: ['iniezioni', 'attivita', 'risultati', 'calendario'] },
  { label: 'Sistema', items: ['impostazioni'] }
];
const metrics = {
  peso: 'Peso', bmi: 'BMI', massa_grassa: 'Massa grassa', muscoli: 'Muscoli', passi: 'Passi',
  acqua: 'Acqua',
  eta_metabolica: 'Età metabolica',
  braccio_sx_massa_grassa: 'Braccio SX - massa grassa', braccio_sx_muscoli: 'Braccio SX - muscoli',
  braccio_dx_massa_grassa: 'Braccio DX - massa grassa', braccio_dx_muscoli: 'Braccio DX - muscoli',
  gamba_sx_massa_grassa: 'Gamba SX - massa grassa', gamba_sx_muscoli: 'Gamba SX - muscoli',
  gamba_dx_massa_grassa: 'Gamba DX - massa grassa', gamba_dx_muscoli: 'Gamba DX - muscoli',
  tronco_massa_grassa: 'Tronco - massa grassa', tronco_muscoli: 'Tronco - muscoli'
};
const metricUnits = {
  peso: 'kg', bmi: '', massa_grassa: '%', acqua: '%', muscoli: '%',
  eta_metabolica: 'anni',
  braccio_sx_massa_grassa: '%', braccio_sx_muscoli: '%',
  braccio_dx_massa_grassa: '%', braccio_dx_muscoli: '%',
  gamba_sx_massa_grassa: '%', gamba_sx_muscoli: '%',
  gamba_dx_massa_grassa: '%', gamba_dx_muscoli: '%',
  tronco_massa_grassa: '%', tronco_muscoli: '%',
  passi: 'passi'
};
const dashboardMetrics = ['peso', 'bmi', 'massa_grassa', 'muscoli'];
const dashboardMetricAccents = { peso: 'var(--teal)', bmi: 'var(--teal)', massa_grassa: 'var(--coral)', muscoli: 'var(--lime)' };
const measurementFieldMap = {
  peso: 'weight_kg',
  bmi: 'bmi',
  massa_grassa: 'body_fat',
  acqua: 'body_water',
  muscoli: 'muscle',
  eta_metabolica: 'metabolic_age',
  braccio_sx_massa_grassa: 'left_arm_body_fat',
  braccio_sx_muscoli: 'left_arm_muscle',
  braccio_dx_massa_grassa: 'right_arm_body_fat',
  braccio_dx_muscoli: 'right_arm_muscle',
  gamba_sx_massa_grassa: 'left_leg_body_fat',
  gamba_sx_muscoli: 'left_leg_muscle',
  gamba_dx_massa_grassa: 'right_leg_body_fat',
  gamba_dx_muscoli: 'right_leg_muscle',
  tronco_massa_grassa: 'trunk_body_fat',
  tronco_muscoli: 'trunk_muscle'
};
const measurementColumns = [
  ['measured_at', 'Data e ora', 'datetime-local'],
  ['weight_kg', 'Peso (kg)', 'number'],
  ['bmi', 'BMI', 'number'],
  ['body_fat', 'Massa grassa (%)', 'number'],
  ['body_water', 'Acqua (%)', 'number'],
  ['muscle', 'Muscoli (%)', 'number'],
  ['bone', 'Ossa', 'number'],
  ['visceral_fat', 'Grasso viscerale', 'number'],
  ['metabolic_age', 'Età metabolica', 'number'],
  ['heart_rate_bpm', 'Battito (bpm)', 'number'],
  ['left_arm_body_fat', 'Braccio SX grasso', 'number'],
  ['left_arm_muscle', 'Braccio SX muscoli (%)', 'number'],
  ['right_arm_body_fat', 'Braccio DX grasso', 'number'],
  ['right_arm_muscle', 'Braccio DX muscoli (%)', 'number'],
  ['left_leg_body_fat', 'Gamba SX grasso', 'number'],
  ['left_leg_muscle', 'Gamba SX muscoli (%)', 'number'],
  ['right_leg_body_fat', 'Gamba DX grasso', 'number'],
  ['right_leg_muscle', 'Gamba DX muscoli (%)', 'number'],
  ['trunk_body_fat', 'Tronco grasso', 'number'],
  ['trunk_muscle', 'Tronco muscoli (%)', 'number']
];
const eventTypes = {
  misurazione: { label: 'Misurazione corporea', shortLabel: 'Misurazione', icon: 'scale', className: 'misurazione' },
  iniezione: { label: 'GLP-1', shortLabel: 'Iniezione', icon: 'syringe', className: 'iniezione' },
  allenamento: { label: 'Allenamento', shortLabel: 'Allenamento', icon: 'dumbbell', className: 'allenamento' },
  passi: { label: 'Passi', shortLabel: 'Passi', icon: 'footsteps', className: 'passi' }
};
const workoutTypes = {
  forza: { label: 'Allenamento forza', icon: 'dumbbell' },
  fisioterapia: { label: 'Fisioterapia', icon: 'physio' },
  basket: { label: 'Basket', icon: 'basketball' },
  altro: { label: 'Altro', icon: 'target' }
};

const fmt = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 });
const fmtInt = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 });
const $ = (sel) => document.querySelector(sel);
const calendarEventStore = new Map();
const doseColors = ['#8f8f8f', '#8d38e8', '#22d8cf', '#e23295', '#98e33f', '#ff9b50'];
const doseBadgePlugin = {
  id: 'doseBadges',
  afterDatasetsDraw(chart) {
    const badges = chart.config.options.plugins?.doseBadges?.badges || [];
    if (!badges.length) return;
    const meta = chart.getDatasetMeta(0);
    const { ctx, chartArea } = chart;
    ctx.save();
    badges.forEach(badge => {
      const point = meta.data[badge.index];
      if (!point) return;
      const text = `${fmt.format(badge.dose)} mg`;
      const x = Math.min(Math.max(point.x - 12, chartArea.left + 4), chartArea.right - 86);
      const y = Math.max(point.y - 48, chartArea.top + 6);
      ctx.font = '700 12px ui-sans-serif, system-ui, sans-serif';
      const width = Math.max(56, ctx.measureText(text).width + 18);
      roundRect(ctx, x, y, width, 30, 8);
      ctx.fillStyle = badge.color;
      ctx.fill();
      ctx.fillStyle = '#ffffff';
      ctx.fillText(text, x + 9, y + 20);
    });
    ctx.restore();
  }
};

async function api(path, options = {}) {
  const headers = { ...(options.headers || {}) };
  if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
  if (state.csrf) headers['X-CSRF-Token'] = state.csrf;
  const amp = path.indexOf('&');
  const route = amp === -1 ? path : path.slice(0, amp);
  const query = amp === -1 ? '' : path.slice(amp + 1);
  const res = await fetch(`/api/index.php?path=${encodeURIComponent(route)}${query ? `&${query}` : ''}`, { ...options, headers });
  const text = await res.text();
  if (!text.trim()) {
    throw new Error(`Risposta vuota dal server (${res.status}). Controlla configurazione PHP/MySQL.`);
  }
  let json;
  try {
    json = JSON.parse(text);
  } catch (error) {
    throw new Error(`Risposta non valida dal server (${res.status}): ${text.slice(0, 180)}`);
  }
  if (!json.success) throw new Error(json.error?.message || 'Errore inatteso');
  return json.data;
}

function initNav() {
  hoistEntryDialogs();
  $('#sideNav').innerHTML = navGroups.map(group => `<div class="nav-group"><span class="nav-group-title">${group.label}</span>${group.items.map(id => {
    const section = sections.find(item => item[0] === id);
    return section ? navButton(section[0], section[1], section[2]) : '';
  }).join('')}</div>`).join('');
  $('#bottomNav').innerHTML = sections.map(([id, label, icon, shortLabel]) => navButton(id, shortLabel || label, icon, label)).join('');
  $('#sidebarToggle').innerHTML = iconSvg('chevronLeft');
  $('#prevMonthBtn').innerHTML = iconSvg('chevronLeft');
  $('#nextMonthBtn').innerHTML = iconSvg('chevronRight');
  applyTheme(state.theme, false);
  document.querySelectorAll('.nav-button').forEach(btn => btn.addEventListener('click', () => showView(btn.dataset.view)));
}

function hoistEntryDialogs() {
  const shell = $('#appShell');
  document.querySelectorAll('dialog.entry-dialog').forEach(dialog => {
    if (shell && dialog.parentElement !== shell) shell.appendChild(dialog);
  });
}

function navButton(id, label, icon, ariaLabel = label) {
  return `<button class="nav-button ${id === state.active ? 'active' : ''}" data-view="${id}" aria-label="${ariaLabel}"><span class="nav-icon">${iconSvg(icon)}</span><span class="nav-label">${label}</span></button>`;
}

function showView(id) {
  state.active = id;
  document.querySelectorAll('.view').forEach(v => v.classList.toggle('active-view', v.id === id));
  document.querySelectorAll('.nav-button').forEach(b => b.classList.toggle('active', b.dataset.view === id));
  $('#pageTitle').textContent = sections.find(s => s[0] === id)?.[1] || 'Riepilogo';
  document.body.classList.toggle('calendar-mode', id === 'calendario');
  $('#topMetricField').hidden = id !== 'risultati';
  if (id === 'aggiungi') loadEntryDefaults();
  if (id === 'risultati') loadResults();
  if (id === 'iniezioni') loadInjections();
  if (id === 'attivita') loadActivities();
  if (id === 'calendario') loadCalendar();
  if (id === 'impostazioni') loadAdmin();
}

function card(label, value, delta = '', accent = 'var(--teal)', icon = '') {
  const iconMarkup = icon ? `<span class="card-icon" aria-hidden="true">${iconSvg(icon)}</span>` : '';
  return `<article class="card metric-card" style="--accent:${accent}">
    <div class="card-head">${iconMarkup}<div class="label">${label}</div></div>
    <div class="value">${value}</div>
    <div class="delta">${delta}</div>
  </article>`;
}

async function loadDashboard() {
  const data = await api(`dashboard${rangeQuery()}`);
  const summary = data.metric_summary || {};
  const selectedMetric = state.dashboardMetric || 'peso';
  const selectedResults = await api(`results&metric=${selectedMetric}&range=${state.range}${rangeQuery()}`);
  $('#dashboardCards').innerHTML = [
    metricRow(metrics[selectedMetric], selectedMetric, summary[selectedMetric], selectedResults.kpi || {}, dashboardMetricAccents[selectedMetric] || 'var(--teal)'),
    combinedGlpCard(data),
    combinedActivityCard(data)
  ].join('');
  $('#dashboardChartTitle').textContent = `Andamento ${metrics[selectedMetric].toLocaleLowerCase('it-IT')}`;
  drawMetricChart('overviewChart', selectedResults, metrics[selectedMetric]);
}

function combinedGlpCard(data) {
  const counts = data.injection_counts || [];
  const countText = counts.length
    ? counts.map(row => `${fmt.format(row.dose_mg)} mg: ${fmtInt.format(row.total)}`).join(' · ')
    : 'Nessuna dose effettuata';
  return `<article class="panel combo-card glp-card">
    <div class="combo-cell">${miniLabel('Dose GLP-1 attuale', data.current_injection ? `${fmt.format(data.current_injection.dose_mg)} mg` : 'N/D', 'Ultima effettuata', 'syringe', 'var(--violet)')}</div>
    <div class="combo-cell">${miniLabel('Prossima iniezione', data.next_injection ? dateTime(data.next_injection.scheduled_at) : 'Nessuna', data.next_injection ? `${fmt.format(data.next_injection.planned_dose_mg)} mg programmati` : '', 'calendar', 'var(--violet)')}</div>
    <div class="combo-cell">${miniLabel('Iniezioni fatte', countText, 'Conteggio per dosaggio', 'chart', 'var(--violet)')}</div>
  </article>`;
}

function combinedActivityCard(data) {
  const stepsTarget = Number(data.steps_target || 10000);
  const stepsToday = Number(data.steps_today || 0);
  const stepProgress = Math.max(0, Math.min(100, stepsTarget ? (stepsToday / stepsTarget) * 100 : 0));
  const workoutCounts = data.workout_counts_week || [];
  return `<article class="panel combo-card activity-card">
    <div class="combo-cell">${miniLabel('Passi oggi', fmtInt.format(stepsToday), `Obiettivo ${fmtInt.format(stepsTarget)}`, 'footsteps', 'var(--teal)')}<span class="progress-track"><i style="width:${stepProgress}%"></i></span></div>
    <div class="workout-kpi-strip">${renderWorkoutKpis(workoutCounts)}</div>
  </article>`;
}

function renderWorkoutKpis(rows) {
  const byKey = new Map();
  rows.forEach(row => {
    const key = workoutTypeKey(row.workout_type);
    const current = byKey.get(key) || { completed: 0, scheduled: 0 };
    current.completed += Number(row.completed || 0);
    current.scheduled += Number(row.scheduled || 0);
    byKey.set(key, current);
  });
  return ['forza', 'basket', 'fisioterapia'].map(key => {
    const meta = workoutTypes[key];
    const row = byKey.get(key) || {};
    const completed = Number(row.completed || 0);
    const scheduled = Number(row.scheduled || 0);
    const total = Math.max(completed, scheduled);
    return `<div class="workout-kpi" style="--accent:var(--lime)"><span class="event-icon allenamento">${iconSvg(meta.icon)}</span><span>${meta.label}</span><strong>${fmtInt.format(completed)} / ${fmtInt.format(total)}</strong></div>`;
  }).join('');
}

function miniLabel(label, value, detail, icon, accent) {
  return `<div class="mini-metric" style="--accent:${accent}"><span class="card-icon" aria-hidden="true">${iconSvg(icon)}</span><div><span class="label">${label}</span><strong>${value}</strong>${detail ? `<small>${detail}</small>` : ''}</div></div>`;
}

function metricRow(title, metric, data = {}, kpi = {}, accent = 'var(--teal)') {
  const unit = metricUnits[metric] || '';
  const current = formatMetricValue(data.current, metric);
  const target = formatMetricValue(data.target, metric);
  const delta = formatSignedMetricValue(data.target_delta, metric);
  const start = formatMetricValue(kpi.valore_iniziale ?? data.initial, metric);
  const percent = formatKpi('variazione_percentuale', kpi.variazione_percentuale, metric);
  const weekly = formatSignedMetricValue(kpi.media_settimanale, metric);
  const goalProgress = targetProgress(data.initial, data.current, data.target, metric);
  return `<section class="dashboard-metric-row four" aria-label="${title}">
    ${card(`${title} corrente`, current, `Partenza ${start} · ${percent}`, accent, metricIcon(metric))}
    ${card(`${title} target`, target, data.target === null || data.target === undefined ? 'Imposta un obiettivo' : 'Obiettivo attivo', accent, metricIcon(metric))}
    ${card(goalDistanceLabel(metric, unit), delta, goalProgress === null ? 'Imposta un obiettivo' : `<span class="progress-track card-progress"><i style="width:${goalProgress}%"></i></span>${fmt.format(goalProgress)}% completato`, accent, 'target')}
    ${card(metric === 'peso' ? 'Perdita settimanale' : 'Variazione settimanale', weekly, 'Media sul periodo selezionato', accent, 'chart')}
  </section>`;
}

function goalDistanceLabel(metric, unit) {
  if (metric === 'peso') return 'kg a obiettivo';
  if (metric === 'bmi') return 'BMI a obiettivo';
  if (metric === 'massa_grassa') return 'Massa grassa a obiettivo';
  if (metric === 'muscoli') return 'Muscoli a obiettivo';
  return `${unit || 'Valore'} a obiettivo`;
}

function targetProgress(initial, current, target, metric) {
  const start = Number(initial);
  const now = Number(current);
  const goal = Number(target);
  if (![start, now, goal].every(Number.isFinite) || start === goal) return null;
  const direction = metric === 'muscoli' ? 1 : -1;
  const progress = direction === 1 ? ((now - start) / (goal - start)) * 100 : ((start - now) / (start - goal)) * 100;
  return Math.max(0, Math.min(100, progress));
}

async function loadResults() {
  const data = await api(`results&metric=${state.metric}&range=${state.range}${rangeQuery()}`);
  $('#resultChartTitle').textContent = metrics[state.metric];
  $('#resultKpis').innerHTML = Object.entries(data.kpi || {}).map(([key, value]) => card(labelize(key, state.metric), formatKpi(key, value, state.metric), '', 'var(--teal)', kpiIcon(key))).join('');
  drawMetricChart('resultChart', data, metrics[state.metric]);
}

function drawMetricChart(canvasId, data, label) {
  if (!window.Chart) return;
  if (!window.__bodyTrackerDoseBadgesRegistered) {
    Chart.register(doseBadgePlugin);
    window.__bodyTrackerDoseBadgesRegistered = true;
  }
  state.charts[canvasId]?.destroy();
  const labels = (data.series || []).map(p => shortDate(p.date));
  const values = (data.series || []).map(p => Number(p.value));
  const doseTimeline = buildDoseTimeline(data.series || [], data.glp1_overlay || []);
  state.charts[canvasId] = new Chart(document.getElementById(canvasId), {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label,
        data: values,
        borderColor: '#22d8cf',
        backgroundColor: 'rgba(34,216,207,.12)',
        tension: .32,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: doseTimeline.pointColors,
        pointBorderColor: doseTimeline.pointColors,
        segment: {
          borderColor: ctx => doseTimeline.pointColors[ctx.p1DataIndex] || '#22d8cf',
          backgroundColor: ctx => hexToRgba(doseTimeline.pointColors[ctx.p1DataIndex] || '#22d8cf', .12)
        },
        fill: true
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: cssVar('--text') } }, tooltip: { intersect: false, mode: 'index' }, doseBadges: { badges: doseTimeline.badges } },
      scales: { x: { ticks: { color: cssVar('--muted'), maxRotation: 0 }, grid: { color: chartGridColor() } }, y: { ticks: { color: cssVar('--muted') }, grid: { color: chartGridColor() } } }
    }
  });
}

function buildDoseTimeline(series, overlay) {
  const base = '#22d8cf';
  const parsedSeries = series.map((point, index) => ({ index, date: new Date(String(point.date).replace(' ', 'T')).getTime() }));
  const events = overlay
    .map(item => ({
      date: new Date(String(item.administered_at || item.scheduled_at).replace(' ', 'T')).getTime(),
      dose: Number(item.administered_dose_mg || item.planned_dose_mg)
    }))
    .filter(item => Number.isFinite(item.date) && Number.isFinite(item.dose))
    .sort((a, b) => a.date - b.date);
  const doseColor = new Map();
  const colorForDose = dose => {
    const key = dose.toFixed(2);
    if (!doseColor.has(key)) doseColor.set(key, doseColors[doseColor.size % doseColors.length]);
    return doseColor.get(key);
  };
  const doseChanges = [];
  let lastDose = null;
  events.forEach(event => {
    const key = event.dose.toFixed(2);
    if (key !== lastDose) {
      doseChanges.push({ ...event, color: colorForDose(event.dose) });
      lastDose = key;
    }
  });
  const pointColors = parsedSeries.map(point => {
    const active = [...doseChanges].reverse().find(event => event.date <= point.date);
    return active?.color || base;
  });
  const badges = [];
  doseChanges.forEach(event => {
    const point = parsedSeries.find(item => item.date >= event.date) || parsedSeries[parsedSeries.length - 1];
    if (point) badges.push({ index: point.index, dose: event.dose, color: event.color });
  });
  return { pointColors, badges };
}

function hexToRgba(hex, alpha) {
  const value = hex.replace('#', '');
  const bigint = parseInt(value.length === 3 ? value.split('').map(c => c + c).join('') : value, 16);
  const r = (bigint >> 16) & 255;
  const g = (bigint >> 8) & 255;
  const b = bigint & 255;
  return `rgba(${r},${g},${b},${alpha})`;
}

function roundRect(ctx, x, y, width, height, radius) {
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.arcTo(x + width, y, x + width, y + height, radius);
  ctx.arcTo(x + width, y + height, x, y + height, radius);
  ctx.arcTo(x, y + height, x, y, radius);
  ctx.arcTo(x, y, x + width, y, radius);
  ctx.closePath();
}

async function loadInjections() {
  const rows = await api(`injections${rangeQuery()}&include_future=1`);
  $('#injectionTable').innerHTML = renderInjectionTable(rows);
  makeSortable($('#injectionTable'));
  setupTableTools($('#injectionTable'), {
    placeholder: 'Cerca farmaco, dose, data, stato...',
    actions: [
      ['complete-injections', 'Segna effettuate'],
      ['delete-injections', 'Elimina']
    ]
  });
  document.querySelectorAll('[data-complete]').forEach(btn => btn.addEventListener('click', async () => {
    await api(`injections/${btn.dataset.complete}/complete`, { method: 'POST', body: JSON.stringify({ administered_at: new Date().toISOString().slice(0, 19).replace('T', ' ') }) });
    loadInjections();
    loadDashboard();
  }));
  document.querySelectorAll('[data-delete-injection]').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Eliminare questa iniezione?')) return;
    await api(`injections/${btn.dataset.deleteInjection}`, { method: 'DELETE', body: '{}' });
    loadInjections();
    loadDashboard();
    if (state.active === 'calendario') loadCalendar();
  }));
}

async function loadActivities() {
  const [workouts, steps] = await Promise.all([
    api(`workouts${rangeQuery()}&include_future=1`),
    api(`steps${rangeQuery()}`)
  ]);
  $('#activityWorkoutTable').innerHTML = renderWorkoutTable(workouts);
  $('#activityStepsTable').innerHTML = renderStepsTable(steps);
  makeSortable($('#activityWorkoutTable'));
  makeSortable($('#activityStepsTable'));
  setupTableTools($('#activityWorkoutTable'), {
    placeholder: 'Cerca tipo, data, ora, durata, stato...',
    actions: [
      ['complete-workouts', 'Segna effettuati'],
      ['delete-workouts', 'Elimina']
    ]
  });
  setupTableTools($('#activityStepsTable'), { placeholder: 'Cerca data, passi, origine...', actions: [] });
  document.querySelectorAll('[data-complete-workout]').forEach(btn => btn.addEventListener('click', async () => {
    await api(`workouts/${btn.dataset.completeWorkout}/complete`, { method: 'POST', body: JSON.stringify({ completed_at: new Date().toISOString().slice(0, 19).replace('T', ' ') }) });
    loadActivities();
    loadDashboard();
    if (state.active === 'calendario') loadCalendar();
  }));
  document.querySelectorAll('[data-delete-workout]').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Eliminare questo allenamento?')) return;
    await api(`workouts/${btn.dataset.deleteWorkout}`, { method: 'DELETE', body: '{}' });
    loadActivities();
    loadDashboard();
    if (state.active === 'calendario') loadCalendar();
  }));
}

function setupTableTools(table, config) {
  if (!table) return;
  const wrap = table.closest('.table-wrap');
  if (!wrap) return;
  const toolbarId = `${table.id}Tools`;
  document.getElementById(toolbarId)?.remove();
  const actions = config.actions || [];
  const toolbar = document.createElement('div');
  toolbar.id = toolbarId;
  toolbar.className = 'table-tools';
  toolbar.innerHTML = `<label>Cerca <input type="search" data-table-search="${table.id}" placeholder="${escapeAttr(config.placeholder || 'Cerca...')}"></label>
    <div class="table-actions">
      ${actions.length ? `<span class="muted" data-selection-count="${table.id}">0 selezionati</span>` : ''}
      ${actions.map(([action, label]) => `<button type="button" class="${action.includes('delete') ? 'danger-button' : 'ghost-button'}" data-bulk-action="${action}" data-table="${table.id}">${label}</button>`).join('')}
    </div>`;
  wrap.before(toolbar);
  toolbar.querySelector('[data-table-search]')?.addEventListener('input', event => filterTableRows(table, event.target.value));
  table.querySelector('[data-select-all]')?.addEventListener('change', event => {
    table.querySelectorAll('tbody tr:not([hidden]) [data-row-select]').forEach(input => { input.checked = event.target.checked; });
    updateSelectionCount(table);
  });
  table.querySelectorAll('[data-row-select]').forEach(input => input.addEventListener('change', () => updateSelectionCount(table)));
  toolbar.querySelectorAll('[data-bulk-action]').forEach(button => button.addEventListener('click', () => bulkTableAction(table, button.dataset.bulkAction)));
  updateSelectionCount(table);
}

function filterTableRows(table, query) {
  const needle = String(query || '').trim().toLocaleLowerCase('it-IT');
  table.querySelectorAll('tbody tr').forEach(row => {
    const text = (row.dataset.search || row.textContent || '').toLocaleLowerCase('it-IT');
    row.hidden = needle !== '' && !text.includes(needle);
    if (row.hidden) row.querySelector('[data-row-select]') && (row.querySelector('[data-row-select]').checked = false);
  });
  const all = table.querySelector('[data-select-all]');
  if (all) all.checked = false;
  updateSelectionCount(table);
}

function selectedTableIds(table) {
  return [...table.querySelectorAll('tbody tr:not([hidden]) [data-row-select]:checked')]
    .map(input => input.closest('tr')?.dataset.bulkId)
    .filter(Boolean);
}

function updateSelectionCount(table) {
  const count = selectedTableIds(table).length;
  const target = document.querySelector(`[data-selection-count="${table.id}"]`);
  if (target) target.textContent = `${fmtInt.format(count)} selezionati`;
}

async function bulkTableAction(table, action) {
  const ids = selectedTableIds(table);
  if (!ids.length) return alert('Seleziona almeno una riga visibile.');
  const destructive = action.includes('delete');
  if (!confirm(`${destructive ? 'Eliminare' : 'Aggiornare'} ${ids.length} righe selezionate?`)) return;
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
  for (const id of ids) {
    if (action === 'complete-injections') await api(`injections/${id}/complete`, { method: 'POST', body: JSON.stringify({ administered_at: now }) });
    if (action === 'delete-injections') await api(`injections/${id}`, { method: 'DELETE', body: '{}' });
    if (action === 'complete-workouts') await api(`workouts/${id}/complete`, { method: 'POST', body: JSON.stringify({ completed_at: now }) });
    if (action === 'delete-workouts') await api(`workouts/${id}`, { method: 'DELETE', body: '{}' });
    if (action === 'delete-measurements') await api(`measurements/${id}`, { method: 'DELETE', body: '{}' });
  }
  if (table.id === 'injectionTable') loadInjections();
  if (table.id === 'activityWorkoutTable') loadActivities();
  if (table.id === 'measurementsTable') loadMeasurementsTable();
  loadDashboard();
  if (state.active === 'calendario') loadCalendar();
}

function renderWorkoutTable(rows) {
  const head = `<thead><tr>
    <th class="select-cell"><input type="checkbox" data-select-all aria-label="Seleziona tutti gli allenamenti visibili"></th>
    <th data-sort="text">Tipo</th>
    <th data-sort="date">Data</th>
    <th data-sort="text">Ora</th>
    <th data-sort="number">Durata</th>
    <th data-sort="text">Stato</th>
    <th>Azioni</th>
  </tr></thead>`;
  const body = rows.map(row => {
    const scheduled = new Date(row.scheduled_at.replace(' ', 'T'));
    const searchable = `${workoutTypeMeta(row.workout_type).label} ${dateOnly(row.scheduled_at)} ${timeOnly(scheduled)} ${row.duration_minutes || ''} ${statusIt(row.status)}`;
    return `<tr data-bulk-id="${row.id}" data-search="${escapeAttr(searchable)}">
      <td class="select-cell"><input type="checkbox" data-row-select aria-label="Seleziona allenamento"></td>
      <td>${workoutTypeMeta(row.workout_type).label}</td>
      <td data-value="${row.scheduled_at}">${dateOnly(row.scheduled_at)}</td>
      <td>${timeOnly(scheduled)}</td>
      <td data-value="${Number(row.duration_minutes || 0)}">${row.duration_minutes ? `${fmtInt.format(row.duration_minutes)} min` : 'N/D'}</td>
      <td>${statusIt(row.status)}</td>
      <td class="row-actions">
        ${row.status === 'scheduled' ? `<button type="button" data-complete-workout="${row.id}">Segna effettuato</button>` : ''}
        <button type="button" class="danger-button" data-delete-workout="${row.id}">Cancella</button>
      </td>
    </tr>`;
  }).join('');
  return `${head}<tbody>${body || '<tr><td colspan="7">Nessun allenamento nel periodo.</td></tr>'}</tbody>`;
}

function renderStepsTable(rows) {
  const head = `<thead><tr>
    <th data-sort="date">Data</th>
    <th data-sort="number">Passi</th>
    <th data-sort="text">Origine</th>
  </tr></thead>`;
  const body = rows.map(row => `<tr data-search="${escapeAttr(`${dateOnly(row.step_date)} ${row.steps || 0} ${row.source || 'manual'}`)}">
    <td data-value="${row.step_date}">${dateOnly(row.step_date)}</td>
    <td data-value="${Number(row.steps || 0)}">${fmtInt.format(row.steps)} passi</td>
    <td>${row.source || 'manual'}</td>
  </tr>`).join('');
  return `${head}<tbody>${body || '<tr><td colspan="3">Nessun dato passi nel periodo.</td></tr>'}</tbody>`;
}

function renderInjectionTable(rows) {
  const head = `<thead><tr>
    <th class="select-cell"><input type="checkbox" data-select-all aria-label="Seleziona tutte le iniezioni visibili"></th>
    <th data-sort="text">Farmaco</th>
    <th data-sort="number">Dosaggio</th>
    <th data-sort="date">Data</th>
    <th data-sort="text">Ora</th>
    <th data-sort="text">Stato</th>
    <th>Azioni</th>
  </tr></thead>`;
  const body = rows.map(row => {
    const scheduled = new Date(row.scheduled_at.replace(' ', 'T'));
    const status = statusIt(row.status);
    const searchable = `${row.medication_name} ${fmt.format(row.planned_dose_mg)} ${dateOnly(row.scheduled_at)} ${timeOnly(scheduled)} ${status}`;
    return `<tr data-bulk-id="${row.id}" data-search="${escapeAttr(searchable)}">
      <td class="select-cell"><input type="checkbox" data-row-select aria-label="Seleziona iniezione"></td>
      <td>${row.medication_name}</td>
      <td data-value="${Number(row.planned_dose_mg)}">${fmt.format(row.planned_dose_mg)} mg</td>
      <td data-value="${row.scheduled_at}">${dateOnly(row.scheduled_at)}</td>
      <td>${timeOnly(scheduled)}</td>
      <td>${status}</td>
      <td class="row-actions">
        ${row.status === 'scheduled' ? `<button type="button" data-complete="${row.id}">Segna effettuata</button>` : ''}
        <button type="button" class="danger-button" data-delete-injection="${row.id}">Cancella</button>
      </td>
    </tr>`;
  }).join('');
  return `${head}<tbody>${body || '<tr><td colspan="7">Nessuna iniezione registrata.</td></tr>'}</tbody>`;
}

async function loadCalendar() {
  const month = $('#monthPicker').value || new Date().toISOString().slice(0, 7);
  $('#monthPicker').value = month;
  $('#calendarTitle').textContent = capitalizeFirst(new Intl.DateTimeFormat('it-IT', { month: 'long', year: 'numeric' }).format(new Date(`${month}-01T00:00:00`)));
  const rows = uniqueCalendarEvents(await api(`calendar&month=${month}`));
  const byDay = rows.reduce((acc, row) => ((acc[row.event_date] ||= []).push(row), acc), {});
  const first = new Date(`${month}-01T00:00:00`);
  const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
  const mondayOffset = (first.getDay() + 6) % 7;
  const weekdayHeaders = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica']
    .map(day => `<div class="weekday">${day}</div>`)
    .join('');
  const leadingDays = Array.from({ length: mondayOffset }, () => '<div class="day empty-day" aria-hidden="true"></div>').join('');
  const monthDays = Array.from({ length: days }, (_, i) => {
    const day = String(i + 1).padStart(2, '0');
    const key = `${month}-${day}`;
    const events = byDay[key] || [];
    const icons = events.map(e => eventIcon(e.type, eventText(e), e)).join('');
    return `<div class="day"><button data-day="${key}"><strong>${i + 1}</strong><span class="calendar-events">${icons}</span></button></div>`;
  }).join('');
  $('#calendarGrid').innerHTML = weekdayHeaders + leadingDays + monthDays;
  document.querySelectorAll('[data-day]').forEach(btn => btn.addEventListener('click', () => {
    const events = byDay[btn.dataset.day] || [];
    renderDayDetails(btn.dataset.day, events);
  }));
}

function renderDayDetails(day, events) {
  calendarEventStore.clear();
  $('#dayEvents').innerHTML = `<div class="section-head day-head"><h2>${dateIt(day)}</h2><div class="day-actions">
    <button type="button" class="ghost-button" data-day-dialog="quickMeasurementDialog" data-day="${day}">${eventIcon('misurazione', 'Misurazione')} Misurazione</button>
    <button type="button" class="ghost-button" data-day-dialog="quickInjectionDialog" data-day="${day}">${eventIcon('iniezione', 'Iniezione')} Iniezione</button>
    <button type="button" class="ghost-button" data-day-dialog="quickWorkoutDialog" data-day="${day}">${eventIcon('allenamento', 'Allenamento')} Allenamento</button>
    <button type="button" class="ghost-button" data-day-dialog="quickStepsDialog" data-day="${day}">${eventIcon('passi', 'Passi')} Passi</button>
  </div></div><div class="day-event-list">` + (events.map(e => {
    const key = `${e.type}-${e.id}`;
    calendarEventStore.set(key, e);
    return `<div class="day-event">
      <button type="button" class="day-event-main" data-edit-event="${key}">${eventIcon(e.type, eventLabel(e), e)}<span><strong>${eventLabel(e, e)}</strong><br><span class="muted">${eventText(e)}</span></span></button>
      ${calendarEventAction(e)}
    </div>`;
  }).join('') || '<p class="muted">Nessun evento.</p>') + '</div>';
  document.querySelectorAll('[data-day-dialog]').forEach(btn => btn.addEventListener('click', () => openDayDialog(btn.dataset.dayDialog, btn.dataset.day)));
  document.querySelectorAll('[data-edit-event]').forEach(btn => btn.addEventListener('click', () => openEventEditDialog(calendarEventStore.get(btn.dataset.editEvent))));
  document.querySelectorAll('[data-calendar-complete]').forEach(btn => btn.addEventListener('click', e => {
    e.stopPropagation();
    completeCalendarEvent(btn.dataset.calendarComplete, btn.dataset.eventId);
  }));
}

async function loadAdmin() {
  $('#adminPanel').hidden = state.user?.role !== 'super_admin';
  if (state.user?.role === 'super_admin') {
    const users = await api('admin/users');
    $('#adminUsers').innerHTML = users.map(u => `<p><strong>${u.name}</strong> · ${u.email} · ${u.role} · ${u.is_active ? 'attivo' : 'disabilitato'}</p>`).join('');
  }
}

function setupLoginForm() {
  $('#loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const data = await api('auth/login', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(e.target))) });
      boot(data);
    } catch (err) { $('#loginError').textContent = err.message; }
  });
}

function setupAppForms() {
  $('#sidebarToggle').addEventListener('click', toggleSidebar);
  $('#logoutBtn').addEventListener('click', async () => { await api('auth/logout', { method: 'POST', body: '{}' }); location.reload(); });
  document.querySelectorAll('[data-dialog]').forEach(btn => btn.addEventListener('click', () => {
    const dialog = document.getElementById(btn.dataset.dialog);
    resetDialogForm(dialog);
    loadEntryDefaults();
    safeShowDialog(dialog);
  }));
  document.querySelectorAll('[data-close-dialog]').forEach(btn => btn.addEventListener('click', () => btn.closest('dialog')?.close()));
  $('#injectionForm').addEventListener('submit', submitJson('injections', afterInjectionsSaved));
  $('#activityWorkoutForm').addEventListener('submit', submitJson('workouts', afterWorkoutsSaved, formDataWithMultiSelect));
  $('#workoutForm').addEventListener('submit', submitJson('workouts', loadCalendar));
  $('#goalForm').addEventListener('submit', submitJson('goals', afterGoalSaved));
  $('#quickMeasurementForm').addEventListener('submit', submitJson('measurements', afterQuickSave));
  $('#quickStepsForm').addEventListener('submit', submitJson('steps', afterQuickSave));
  $('#quickInjectionForm').addEventListener('submit', submitJson('injections', afterInjectionsSaved));
  $('#quickWorkoutForm').addEventListener('submit', submitJson('workouts', afterQuickSave));
  $('#quickGoalForm').addEventListener('submit', submitJson('goals', afterGoalSaved));
  $('#importForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await api('imports/preview', { method: 'POST', body: form });
    $('#importPreview').innerHTML = `<p>${data.rows_found} righe nel CSV · ${data.rows_importable} nuove da importare · ${data.rows_skipped_existing_period} già consolidate${data.latest_existing_measurement_date ? ` fino al ${dateIt(data.latest_existing_measurement_date)}` : ''} · ${data.rows_flagged} da verificare.</p><button id="confirmImport">Conferma importazione</button>`;
    $('#confirmImport').onclick = async () => {
      const confirmForm = new FormData(e.target);
      confirmForm.append('accepted_hashes', JSON.stringify(data.preview.filter(r => r.flagged).map(r => r.measurement_hash)));
      const done = await api('imports/confirm', { method: 'POST', body: confirmForm });
      $('#importPreview').innerHTML = `<p>${done.rows_found} righe nel CSV · ${done.rows_imported} importate · ${done.rows_skipped_existing_period} già consolidate · ${done.rows_duplicates} duplicati · ${done.rows_rejected} ignorate.</p>`;
      loadResults();
      loadDashboard();
    };
  });
  $('#pushBtn').addEventListener('click', subscribePush);
  $('#loadMeasurementsBtn').addEventListener('click', loadMeasurementsTable);
  $('#monthPicker').addEventListener('change', loadCalendar);
  $('#prevMonthBtn').addEventListener('click', () => shiftCalendarMonth(-1));
  $('#nextMonthBtn').addEventListener('click', () => shiftCalendarMonth(1));
  $('#todayMonthBtn').addEventListener('click', goToTodayMonth);
  $('#dashboardChartMetric').addEventListener('change', e => {
    state.dashboardMetric = e.target.value;
    syncDashboardMetricControls();
    loadDashboard();
  });
  $('#rangeStart').addEventListener('change', updateDateRange);
  $('#rangeEnd').addEventListener('change', updateDateRange);
  $('#themeToggle')?.addEventListener('click', toggleTheme);
  $('#themeToggleSettings')?.addEventListener('click', toggleTheme);
}

function toggleSidebar() {
  state.sidebarCollapsed = !state.sidebarCollapsed;
  document.body.classList.toggle('sidebar-collapsed', state.sidebarCollapsed);
  $('#sidebarToggle').innerHTML = iconSvg(state.sidebarCollapsed ? 'chevronRight' : 'chevronLeft');
  $('#sidebarToggle').setAttribute('aria-label', state.sidebarCollapsed ? 'Espandi menu' : 'Comprimi menu');
  $('#sidebarToggle').setAttribute('aria-expanded', String(!state.sidebarCollapsed));
}

function toggleTheme() {
  applyTheme(state.theme === 'dark' ? 'light' : 'dark');
}

function applyTheme(theme, refreshCharts = true) {
  state.theme = theme === 'light' ? 'light' : 'dark';
  localStorage.setItem('kinetica-theme', state.theme);
  document.body.classList.toggle('theme-light', state.theme === 'light');
  const label = state.theme === 'light' ? 'Tema scuro' : 'Tema chiaro';
  $('#themeToggle') && ($('#themeToggle').textContent = label);
  $('#themeToggleSettings') && ($('#themeToggleSettings').textContent = label);
  if (refreshCharts && state.user) {
    loadDashboard();
    if (state.active === 'risultati') loadResults();
  }
}

function cssVar(name) {
  return getComputedStyle(document.body).getPropertyValue(name).trim() || getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#f3fbfa';
}

function chartGridColor() {
  return document.body.classList.contains('theme-light') ? 'rgba(16,32,34,.1)' : 'rgba(255,255,255,.08)';
}

async function openDayDialog(dialogId, day) {
  const dialog = document.getElementById(dialogId);
  if (!dialog) return;
  resetDialogForm(dialog);
  await loadEntryDefaults();
  const dateTime = `${day}T${new Date().toTimeString().slice(0, 5)}`;
  const measuredAt = dialog.querySelector('input[name="measured_at"]');
  const scheduledAt = dialog.querySelector('input[name="scheduled_at"]');
  const stepDate = dialog.querySelector('input[name="step_date"]');
  if (measuredAt) measuredAt.value = dateTime;
  if (scheduledAt) scheduledAt.value = dateTime;
  if (stepDate) stepDate.value = day;
  safeShowDialog(dialog);
}

async function openEventEditDialog(event) {
  if (!event) return;
  await loadEntryDefaults();
  const dialogMap = {
    misurazione: 'quickMeasurementDialog',
    iniezione: 'quickInjectionDialog',
    allenamento: 'quickWorkoutDialog',
    passi: 'quickStepsDialog'
  };
  const dialog = document.getElementById(dialogMap[event.type]);
  if (!dialog) return;
  resetDialogForm(dialog);
  populateEventForm(dialog.querySelector('form'), event);
  safeShowDialog(dialog);
}

function safeShowDialog(dialog) {
  if (!dialog) return;
  document.querySelectorAll('dialog[open]').forEach(openDialog => {
    if (openDialog !== dialog) openDialog.close();
  });
  if (!dialog.open) dialog.showModal();
}

function resetDialogForm(dialog) {
  const form = dialog?.querySelector('form');
  form?.reset();
  const id = form?.querySelector('input[name="id"]');
  if (id) id.value = '';
}

function populateEventForm(form, event) {
  if (!form) return;
  setFormValue(form, 'id', event.id || '');
  if (event.type === 'misurazione') {
    setFormValue(form, 'measured_at', inputDateTime(event.measured_at));
    measurementColumns.forEach(([key]) => setFormValue(form, key, event[key] ?? ''));
  }
  if (event.type === 'iniezione') {
    setFormValue(form, 'medication_name', event.medication_name || 'Mounjaro');
    setFormValue(form, 'scheduled_at', inputDateTime(event.scheduled_at));
    setFormValue(form, 'planned_dose_mg', event.planned_dose_mg ?? '');
    setFormValue(form, 'reminder_minutes_before', event.reminder_minutes_before ?? 1440);
    setFormValue(form, 'reminder_repeat_minutes', event.reminder_repeat_minutes ?? 0);
    setFormChecked(form, 'completed', event.status === 'completed');
  }
  if (event.type === 'allenamento') {
    setFormValue(form, 'workout_type', workoutTypeMeta(event.workout_type).label);
    setFormValue(form, 'scheduled_at', inputDateTime(event.scheduled_at));
    setFormValue(form, 'duration_minutes', event.duration_minutes ?? '');
    setFormValue(form, 'reminder_minutes_before', event.reminder_minutes_before ?? 60);
    setFormValue(form, 'reminder_repeat_minutes', event.reminder_repeat_minutes ?? 0);
    setFormChecked(form, 'completed', event.status === 'completed');
  }
  if (event.type === 'passi') {
    setFormValue(form, 'step_date', event.step_date || event.event_date || '');
    setFormValue(form, 'steps', event.steps ?? '');
  }
}

function setFormValue(form, name, value) {
  const input = form.querySelector(`[name="${name}"]`);
  if (input) input.value = value ?? '';
}

function setFormChecked(form, name, checked) {
  const input = form.querySelector(`[name="${name}"]`);
  if (input) input.checked = Boolean(checked);
}

async function completeCalendarEvent(type, id) {
  if (!id) return;
  const route = type === 'iniezione' ? `injections/${id}/complete` : type === 'allenamento' ? `workouts/${id}/complete` : null;
  if (!route) return;
  try {
    await api(route, { method: 'POST', body: JSON.stringify({ administered_at: new Date().toISOString().slice(0, 19).replace('T', ' '), completed_at: new Date().toISOString().slice(0, 19).replace('T', ' ') }) });
    loadCalendar();
    loadDashboard();
    if (state.active === 'iniezioni') loadInjections();
    if (state.active === 'attivita') loadActivities();
  } catch (err) {
    alert(err.message);
  }
}

function submitJson(path, after, serializer = form => Object.fromEntries(new FormData(form))) {
  return async (e) => {
    e.preventDefault();
    const body = serializer(e.target);
    const id = body.id;
    delete body.id;
    try {
      const result = await api(id ? `${path}/${id}` : path, { method: id ? 'PUT' : 'POST', body: JSON.stringify(body) });
      e.target.closest('dialog')?.close();
      e.target.reset();
      after(result, e.target);
    } catch (err) {
      alert(err.message);
    }
  };
}

function formDataWithMultiSelect(form) {
  const body = Object.fromEntries(new FormData(form));
  form.querySelectorAll('select[multiple]').forEach(select => {
    body[select.name] = [...select.selectedOptions].map(option => option.value);
  });
  return body;
}

function afterQuickSave() {
  hydrateDefaultDates();
  loadDashboard();
  if (state.active === 'risultati') loadResults();
  if (state.active === 'calendario') loadCalendar();
  if (state.active === 'attivita') loadActivities();
  alert('Inserimento salvato.');
}

function afterGoalSaved() {
  hydrateDefaultDates();
  loadDashboard();
  loadResults();
  alert('Target aggiornato.');
}

function afterInjectionsSaved(result) {
  hydrateDefaultDates();
  loadEntryDefaults();
  loadInjections();
  loadDashboard();
  if (state.active === 'calendario') loadCalendar();
  if (state.active === 'attivita') loadActivities();
  alert(`${result.created ?? 1} iniezioni programmate. ${result.skipped ? `${result.skipped} già presenti.` : ''}`.trim());
}

function afterWorkoutsSaved(result) {
  hydrateDefaultDates();
  loadActivities();
  loadDashboard();
  if (state.active === 'calendario') loadCalendar();
  alert(`${result.created ?? 1} allenamenti programmati. ${result.skipped ? `${result.skipped} già presenti.` : ''}`.trim());
}

async function subscribePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return alert('Notifiche push non supportate su questo dispositivo.');
  const reg = await navigator.serviceWorker.ready;
  const vapid = document.querySelector('meta[name="vapid-public-key"]')?.content || '';
  if (!vapid) return alert('Configura VAPID_PUBLIC_KEY nel server prima di attivare le notifiche.');
  const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(vapid) });
  await api('push/subscribe', { method: 'POST', body: JSON.stringify(sub) });
  alert('Notifiche attivate.');
}

function populateControls() {
  const metricOptions = Object.entries(metrics).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
  $('#metricSelect').innerHTML = metricOptions;
  $('#dashboardChartMetric').innerHTML = dashboardMetrics.map(metric => `<option value="${metric}">${metrics[metric]}</option>`).join('');
  $('#dashboardChartMetric').value = state.dashboardMetric;
  $('#goalForm select[name="metric_key"]').innerHTML = $('#metricSelect').innerHTML;
  $('#quickGoalForm select[name="metric_key"]').innerHTML = metricOptions;
  $('#metricSelect').addEventListener('change', e => { state.metric = e.target.value; loadResults(); });
  $('#dashboardMetricToggle').innerHTML = dashboardMetrics.map(metric => `<button type="button" data-dashboard-metric="${metric}" class="${metric === state.dashboardMetric ? 'active' : ''}">${metrics[metric]}</button>`).join('');
  document.querySelectorAll('[data-dashboard-metric]').forEach(btn => btn.addEventListener('click', () => {
    state.dashboardMetric = btn.dataset.dashboardMetric;
    syncDashboardMetricControls();
    loadDashboard();
  }));
  const ranges = [['1w', '1 settimana'], ['1m', '1 mese'], ['3m', '3 mesi'], ['6m', '6 mesi'], ['1y', '1 anno'], ['all', 'Sempre']];
  $('#topRangeTabs').innerHTML = ranges.map(([k, v]) => `<button type="button" data-top-range="${k}" class="${k === state.range ? 'active' : ''}">${v}</button>`).join('');
  document.querySelectorAll('[data-top-range]').forEach(btn => btn.addEventListener('click', () => {
    if (btn.dataset.topRange !== 'custom') {
      state.range = btn.dataset.topRange;
      applyPresetRange(state.range);
    }
    setRangeButtonState(btn.dataset.topRange || 'custom');
    loadDashboard();
    if (state.active === 'risultati') loadResults();
    if (state.active === 'iniezioni') loadInjections();
    if (state.active === 'attivita') loadActivities();
  }));
  $('#calendarLegend').innerHTML = Object.entries(eventTypes).map(([type, meta]) => `<span>${eventIcon(type, meta.shortLabel)} ${meta.shortLabel}</span>`).join('');
}

function hydrateDefaultDates() {
  const now = new Date();
  const today = isoDate(now);
  const time = now.toTimeString().slice(0, 5);
  document.querySelectorAll('input[type="date"]').forEach(input => { if (!input.value && input.id !== 'rangeStart' && input.id !== 'rangeEnd') input.value = today; });
  document.querySelectorAll('input[type="time"]').forEach(input => { if (!input.value) input.value = time; });
  document.querySelectorAll('input[type="datetime-local"]').forEach(input => { if (!input.value) input.value = `${today}T${time}`; });
  if (!$('#monthPicker').value) $('#monthPicker').value = today.slice(0, 7);
  if (!state.rangeStart || !state.rangeEnd) applyPresetRange(state.range);
}

async function loadEntryDefaults() {
  hydrateDefaultDates();
  try {
    const defaults = await api('injections/defaults');
    document.querySelectorAll('input[name="medication_name"]').forEach(input => { if (!input.value || input.value === 'GLP-1') input.value = defaults.medication_name || 'Mounjaro'; });
    document.querySelectorAll('input[name="planned_dose_mg"]').forEach(input => { if (!input.value) input.value = defaults.planned_dose_mg || 7.5; });
  } catch {}
}

function updateDateRange() {
  state.rangeStart = $('#rangeStart').value;
  state.rangeEnd = $('#rangeEnd').value;
  setRangeButtonState('custom');
  loadDashboard();
  if (state.active === 'risultati') loadResults();
  if (state.active === 'iniezioni') loadInjections();
  if (state.active === 'attivita') loadActivities();
}

function applyPresetRange(range) {
  const end = new Date();
  const start = new Date(end);
  if (range === '1m') start.setMonth(start.getMonth() - 1);
  if (range === '1w') start.setDate(start.getDate() - 7);
  if (range === '3m') start.setMonth(start.getMonth() - 3);
  if (range === '6m') start.setMonth(start.getMonth() - 6);
  if (range === '1y') start.setFullYear(start.getFullYear() - 1);
  if (range === 'all') start.setFullYear(1970, 0, 1);
  state.rangeStart = isoDate(start);
  state.rangeEnd = isoDate(end);
  $('#rangeStart').value = state.rangeStart;
  $('#rangeEnd').value = state.rangeEnd;
  setRangeButtonState(range);
}

function setRangeButtonState(activeRange) {
  document.querySelectorAll('[data-top-range]').forEach(btn => btn.classList.toggle('active', btn.dataset.topRange === activeRange));
}

function syncDashboardMetricControls() {
  $('#dashboardChartMetric').value = state.dashboardMetric;
  document.querySelectorAll('[data-dashboard-metric]').forEach(item => item.classList.toggle('active', item.dataset.dashboardMetric === state.dashboardMetric));
}

function uniqueCalendarEvents(rows) {
  const seen = new Set();
  return rows.filter(row => {
    const key = row.type === 'misurazione'
      ? [
          row.type, row.event_date, row.weight_kg, row.bmi, row.body_fat, row.body_water, row.muscle,
          row.bone, row.left_arm_body_fat, row.left_arm_muscle, row.right_arm_body_fat, row.right_arm_muscle,
          row.left_leg_body_fat, row.left_leg_muscle, row.right_leg_body_fat, row.right_leg_muscle,
          row.trunk_body_fat, row.trunk_muscle, row.metabolic_age, row.heart_rate_bpm, row.visceral_fat
        ].join('|')
      : `${row.type}|${row.id}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function rangeQuery() {
  return state.rangeStart && state.rangeEnd ? `&start=${state.rangeStart}&end=${state.rangeEnd}` : '';
}

function shiftCalendarMonth(delta) {
  const current = $('#monthPicker').value || new Date().toISOString().slice(0, 7);
  const date = new Date(`${current}-01T00:00:00`);
  date.setMonth(date.getMonth() + delta);
  $('#monthPicker').value = isoDate(date).slice(0, 7);
  loadCalendar();
}

function goToTodayMonth() {
  $('#monthPicker').value = isoDate(new Date()).slice(0, 7);
  loadCalendar();
}

async function loadMeasurementsTable() {
  $('#measurementsStatus').textContent = 'Caricamento misurazioni...';
  const rows = await api('measurements&limit=500');
  $('#measurementsTable').hidden = false;
  $('#measurementsTable').innerHTML = renderMeasurementsTable(rows);
  makeSortable($('#measurementsTable'));
  setupTableTools($('#measurementsTable'), {
    placeholder: 'Cerca data, peso, BMI, massa grassa...',
    actions: [['delete-measurements', 'Elimina']]
  });
  $('#measurementsStatus').textContent = `${rows.length} misurazioni visualizzate. Le modifiche aggiornano anche la deduplica.`;
  document.querySelectorAll('[data-save-measurement]').forEach(btn => btn.addEventListener('click', saveMeasurementRow));
  document.querySelectorAll('[data-delete-measurement]').forEach(btn => btn.addEventListener('click', deleteMeasurementRow));
}

function renderMeasurementsTable(rows) {
  const head = `<thead><tr><th class="select-cell"><input type="checkbox" data-select-all aria-label="Seleziona tutte le misurazioni visibili"></th>${measurementColumns.map(([key, label, type]) => `<th data-sort="${type === 'number' ? 'number' : type === 'datetime-local' ? 'date' : 'text'}">${label}</th>`).join('')}<th>Azioni</th></tr></thead>`;
  const body = rows.map(row => `<tr data-measurement-row="${row.id}" data-bulk-id="${row.id}" data-search="${escapeAttr(measurementColumns.map(([key]) => row[key] ?? '').join(' '))}">
    <td class="select-cell"><input type="checkbox" data-row-select aria-label="Seleziona misurazione"></td>
    ${measurementColumns.map(([key, label, type]) => `<td><input aria-label="${label}" name="${key}" type="${type}" step="0.1" value="${inputValue(row[key], type)}"></td>`).join('')}
    <td class="row-actions"><button type="button" data-save-measurement="${row.id}">Salva</button><button type="button" class="danger-button" data-delete-measurement="${row.id}">Elimina</button></td>
  </tr>`).join('');
  return `${head}<tbody>${body || '<tr><td colspan="22">Nessuna misurazione importata.</td></tr>'}</tbody>`;
}

function makeSortable(table) {
  if (!table) return;
  table.querySelectorAll('th[data-sort]').forEach((th, index) => {
    th.tabIndex = 0;
    th.addEventListener('click', () => sortTable(table, index, th.dataset.sort || 'text', th));
    th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') sortTable(table, index, th.dataset.sort || 'text', th); });
  });
}

function sortTable(table, columnIndex, type, th) {
  const tbody = table.tBodies[0];
  if (!tbody) return;
  const direction = th.dataset.direction === 'asc' ? 'desc' : 'asc';
  table.querySelectorAll('th').forEach(header => delete header.dataset.direction);
  th.dataset.direction = direction;
  const rows = [...tbody.rows];
  rows.sort((a, b) => {
    const av = sortableCellValue(a.cells[columnIndex], type);
    const bv = sortableCellValue(b.cells[columnIndex], type);
    if (av < bv) return direction === 'asc' ? -1 : 1;
    if (av > bv) return direction === 'asc' ? 1 : -1;
    return 0;
  });
  rows.forEach(row => tbody.appendChild(row));
}

function sortableCellValue(cell, type) {
  const input = cell?.querySelector('input, select, textarea');
  const raw = cell?.dataset.value ?? input?.value ?? cell?.textContent ?? '';
  if (type === 'number') return Number(String(raw).replace(',', '.')) || 0;
  if (type === 'date') return new Date(String(raw).replace(' ', 'T')).getTime() || 0;
  return String(raw).trim().toLocaleLowerCase('it-IT');
}

async function saveMeasurementRow(event) {
  const id = event.currentTarget.dataset.saveMeasurement;
  const row = document.querySelector(`[data-measurement-row="${id}"]`);
  const payload = Object.fromEntries([...row.querySelectorAll('input')].map(input => [input.name, input.value]));
  await api(`measurements/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
  $('#measurementsStatus').textContent = 'Misurazione salvata.';
  loadDashboard();
  loadResults();
}

async function deleteMeasurementRow(event) {
  const id = event.currentTarget.dataset.deleteMeasurement;
  if (!confirm('Eliminare questa misurazione? L’operazione non può essere annullata.')) return;
  await api(`measurements/${id}`, { method: 'DELETE', body: '{}' });
  document.querySelector(`[data-measurement-row="${id}"]`)?.remove();
  $('#measurementsStatus').textContent = 'Misurazione eliminata.';
  loadDashboard();
  loadResults();
}

function boot(data) {
  state.user = data.user;
  state.csrf = data.csrf_token;
  $('#loginView').hidden = true;
  $('#appShell').hidden = false;
  initNav();
  populateControls();
  setupAppForms();
  hydrateDefaultDates();
  loadEntryDefaults();
  $('#todayLabel').innerHTML = `Panoramica <span>Aggiornato oggi, ${new Intl.DateTimeFormat('it-IT', { hour: '2-digit', minute: '2-digit' }).format(new Date())}</span> <i aria-hidden="true"></i>`;
  loadDashboard();
  if (new URLSearchParams(location.search).get('share') === 'csv') loadSharedImport();
}

async function loadSharedImport() {
  showView('risultati');
  try {
    const data = await api('imports/shared-preview', { method: 'POST', body: '{}' });
    $('#importPreview').innerHTML = `<p>CSV condiviso ricevuto: ${data.rows_found} righe trovate, ${data.rows_flagged} da verificare.</p><button id="confirmSharedImport">Conferma importazione</button>`;
    $('#confirmSharedImport').onclick = async () => {
      const done = await api('imports/shared-confirm', { method: 'POST', body: JSON.stringify({ accepted_hashes: data.preview.filter(r => r.flagged).map(r => r.measurement_hash) }) });
      $('#importPreview').innerHTML = `<p>${done.rows_found} righe nel CSV · ${done.rows_imported} importate · ${done.rows_skipped_existing_period} già consolidate · ${done.rows_duplicates} duplicati · ${done.rows_rejected} ignorate.</p>`;
      loadDashboard();
      loadResults();
    };
  } catch (err) {
    $('#importPreview').innerHTML = `<p class="muted">${err.message}</p>`;
  }
}

function dateTime(value) { return value ? new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value.replace(' ', 'T'))) : ''; }
function dateOnly(value) { return value ? new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value.replace(' ', 'T'))) : ''; }
function timeOnly(date) { return date instanceof Date && !Number.isNaN(date.getTime()) ? new Intl.DateTimeFormat('it-IT', { hour: '2-digit', minute: '2-digit' }).format(date) : ''; }
function shortDate(value) { return value ? new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short' }).format(new Date(value.replace(' ', 'T'))) : ''; }
function dateIt(value) { return new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`)); }
function labelize(key, metric = state.metric) {
  return ({
    valore_corrente: 'Valore corrente',
    valore_iniziale: 'Valore iniziale',
    variazione_totale: 'Variazione totale',
    variazione_percentuale: 'Variazione percentuale',
    media_settimanale: metric === 'peso' ? 'Perdita settimanale' : 'Media settimanale',
    delta_target: 'Delta da target',
    media_giornaliera: 'Media giornaliera',
    totale_periodo: 'Totale periodo',
    giorni_sopra_obiettivo: 'Giorni sopra obiettivo',
    percentuale_giorni_target: 'Giorni target raggiunto',
    minimo: 'Minimo',
    massimo: 'Massimo'
  })[key] || key.replaceAll('_', ' ');
}
function formatKpi(key, value, metric) {
  if (typeof value !== 'number') return 'N/D';
  const unit = metricUnits[metric] || '';
  const prefix = key.includes('variazione') && value > 0 ? '+' : '';
  if (key.includes('percentuale')) return `${prefix}${fmt.format(value)} %`;
  if (metric === 'passi' || key.includes('giorni') || key.includes('totale_periodo')) return `${fmtInt.format(value)}${unit === 'passi' ? ' passi' : ''}`;
  return `${prefix}${fmt.format(value)}${unit ? ` ${unit}` : ''}`;
}
function formatMetricValue(value, metric) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return 'N/D';
  const unit = metricUnits[metric] || '';
  return `${fmt.format(Number(value))}${unit ? ` ${unit}` : ''}`;
}
function formatSignedMetricValue(value, metric) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return 'N/D';
  const unit = metricUnits[metric] || '';
  const number = Number(value);
  return `${number > 0 ? '+' : ''}${fmt.format(number)}${unit ? ` ${unit}` : ''}`;
}
function metricIcon(metric) {
  if (metric === 'peso') return 'weight';
  if (metric === 'bmi') return 'bmi';
  if (metric === 'massa_grassa') return 'fat';
  return 'target';
}
function kpiIcon(key) {
  if (key.includes('target')) return 'target';
  if (key.includes('settimanale')) return 'chart';
  if (key.includes('iniziale')) return 'calendar';
  if (key.includes('minimo') || key.includes('massimo')) return 'chart';
  return 'weight';
}
function workoutTypeKey(value) {
  const text = String(value || '').trim().toLocaleLowerCase('it-IT');
  if (text.includes('fisi')) return 'fisioterapia';
  if (text.includes('basket')) return 'basket';
  if (text.includes('forza') || text === 'forza') return 'forza';
  return 'altro';
}
function workoutTypeMeta(value) {
  return workoutTypes[workoutTypeKey(value)] || workoutTypes.altro;
}
function inputValue(value, type) {
  if (value === null || value === undefined) return '';
  if (type === 'datetime-local') return String(value).slice(0, 16).replace(' ', 'T');
  return value;
}
function inputDateTime(value) { return value ? String(value).slice(0, 16).replace(' ', 'T') : ''; }
function escapeAttr(value) { return String(value).replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
function isoDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function iconSvg(name) {
  const icons = {
    plus: '<svg viewBox="0 0 24 24"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
    home: '<svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-6h6v6"></path></svg>',
    syringe: '<svg viewBox="0 0 24 24"><path d="m18 2 4 4"></path><path d="m17 7 2-2"></path><path d="M4 20l7-7"></path><path d="m9 11 4 4 6-6-4-4-6 6Z"></path><path d="m5 19 3 3"></path></svg>',
    chart: '<svg viewBox="0 0 24 24"><path d="M4 19V5"></path><path d="M4 19h17"></path><path d="M8 16v-5"></path><path d="M13 16V8"></path><path d="M18 16v-9"></path></svg>',
    calendar: '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"></rect><path d="M8 3v4"></path><path d="M16 3v4"></path><path d="M4 10h16"></path></svg>',
    chevronLeft: '<svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"></path></svg>',
    chevronRight: '<svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"></path></svg>',
    settings: '<svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-2 .4 1.7 1.7 0 0 0-.5 1.4H9a1.7 1.7 0 0 0-.5-1.4 1.7 1.7 0 0 0-2-.4l-.2.1-2-3.4.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.2-1.1V10a1.7 1.7 0 0 0 1.2-1.1A1.7 1.7 0 0 0 4.4 7l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 2-.4A1.7 1.7 0 0 0 9 1.8h6a1.7 1.7 0 0 0 .5 1.4 1.7 1.7 0 0 0 2 .4l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 20.5 10v3.9a1.7 1.7 0 0 0-1.1 1.1Z"></path></svg>',
    weight: '<svg viewBox="0 0 24 24"><rect x="6" y="4" width="12" height="16" rx="3"></rect><path d="M9 8.5a4 4 0 0 1 6 0"></path><path d="M12 8.5V11"></path></svg>',
    scale: '<svg viewBox="0 0 24 24"><path d="M7 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z"></path><path d="M8.7 9.2a5 5 0 0 1 6.6 0"></path><path d="m12 9 1.2 2.2"></path></svg>',
    bmi: '<svg viewBox="0 0 24 24"><path d="M12 3v18"></path><path d="M7 8h10"></path><path d="M6 15h12"></path><path d="M9 3h6"></path><path d="M8 21h8"></path></svg>',
    fat: '<svg viewBox="0 0 24 24"><path d="M12 3c3 3.2 6 6.8 6 10.5a6 6 0 0 1-12 0C6 9.8 9 6.2 12 3Z"></path><path d="M9.5 13.5h5"></path><path d="M12 11v5"></path></svg>',
    target: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="3"></circle><path d="M12 2v3"></path><path d="M12 19v3"></path><path d="M2 12h3"></path><path d="M19 12h3"></path></svg>',
    footsteps: '<svg viewBox="0 0 24 24"><path d="M7.5 13.5c1.5 0 2.5 1.2 2.5 2.8 0 2-1.1 3.7-2.8 3.7-1.4 0-2.4-1.1-2.4-2.7 0-1.9 1-3.8 2.7-3.8Z"></path><path d="M16.5 4c1.5 0 2.5 1.2 2.5 2.8 0 2-1.1 3.7-2.8 3.7-1.4 0-2.4-1.1-2.4-2.7C13.8 5.9 14.8 4 16.5 4Z"></path></svg>',
    dumbbell: '<svg viewBox="0 0 24 24"><path d="M6 7v10"></path><path d="M18 7v10"></path><path d="M3 9v6"></path><path d="M21 9v6"></path><path d="M6 12h12"></path></svg>',
    basketball: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"></circle><path d="M4.2 9.2c4.2 1.2 8.4 1.2 15.6 0"></path><path d="M4.2 14.8c4.2-1.2 8.4-1.2 15.6 0"></path><path d="M12 3.5c-2 2.1-3 5-3 8.5s1 6.4 3 8.5"></path><path d="M12 3.5c2 2.1 3 5 3 8.5s-1 6.4-3 8.5"></path></svg>',
    physio: '<svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="2.2"></circle><path d="M6 21c1.1-3.8 3.1-6 6-6s4.9 2.2 6 6"></path><path d="M8 11h8"></path><path d="M12 7.5V15"></path><path d="m9 14-3 3"></path><path d="m15 14 3 3"></path></svg>'
  };
  return icons[name] || '';
}
function statusIt(s) { return ({ scheduled: 'Programmata', completed: 'Effettuata', missed: 'Mancata', skipped: 'Saltata', cancelled: 'Annullata' })[s] || s; }
function eventIcon(type, label, event = null) {
  const meta = eventTypes[type] || eventTypes.misurazione;
  const icon = type === 'allenamento' && event ? workoutTypeMeta(event.workout_type).icon : meta.icon;
  return `<span class="event-icon ${meta.className}" title="${label}" aria-label="${label}">${iconSvg(icon)}</span>`;
}
function eventLabel(e) { return e?.type === 'allenamento' ? workoutTypeMeta(e.workout_type).label : ((eventTypes[e.type] || {}).label || e.type); }
function eventText(e) {
  if (e.type === 'misurazione') return `${fmt.format(e.weight_kg)} kg`;
  if (e.type === 'iniezione') return `${fmt.format(e.planned_dose_mg)} mg · ${statusIt(e.status)}`;
  if (e.type === 'passi') return `${fmtInt.format(e.steps)} passi`;
  return `${workoutTypeMeta(e.workout_type).label} · ${statusIt(e.status)}`;
}
function calendarEventAction(e) {
  if ((e.type === 'iniezione' || e.type === 'allenamento') && e.status !== 'completed') {
    return `<button type="button" class="ghost-button complete-inline" data-calendar-complete="${e.type}" data-event-id="${e.id}">Segna effettuata</button>`;
  }
  return '';
}
function capitalizeFirst(value) { return value ? value.charAt(0).toLocaleUpperCase('it-IT') + value.slice(1) : value; }
function urlBase64ToUint8Array(base64String) { const padding = '='.repeat((4 - base64String.length % 4) % 4); const rawData = atob((base64String + padding).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...rawData].map(c => c.charCodeAt(0))); }

window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); state.deferredInstall = e; $('#installBtn').hidden = false; });
$('#installBtn')?.addEventListener('click', async () => { await state.deferredInstall?.prompt(); $('#installBtn').hidden = true; });

(async function init() {
  setupLoginForm();
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js');
  try {
    const data = await api('auth/me');
    if (data.user) boot(data); else $('#loginView').hidden = false;
  } catch (err) {
    $('#loginView').hidden = false;
    $('#loginError').textContent = err.message;
  }
})();
