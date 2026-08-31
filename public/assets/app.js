const state = { user: null, csrf: null, active: 'riepilogo', range: '3m', metric: 'peso', charts: {}, deferredInstall: null, sidebarCollapsed: false };
const sections = [
  ['riepilogo', 'Riepilogo', 'home'],
  ['iniezioni', 'Iniezioni', 'syringe'],
  ['risultati', 'Risultati', 'chart'],
  ['calendario', 'Calendario', 'calendar'],
  ['impostazioni', 'Impostazioni', 'settings']
];
const metrics = {
  peso: 'Peso', bmi: 'BMI', massa_grassa: 'Massa grassa', acqua: 'Acqua', muscoli: 'Muscoli', ossa: 'Ossa',
  grasso_viscerale: 'Grasso viscerale', eta_metabolica: 'Età metabolica', battito: 'Battito',
  braccio_sx_massa_grassa: 'Braccio SX - massa grassa', braccio_sx_muscoli: 'Braccio SX - muscoli',
  braccio_dx_massa_grassa: 'Braccio DX - massa grassa', braccio_dx_muscoli: 'Braccio DX - muscoli',
  gamba_sx_massa_grassa: 'Gamba SX - massa grassa', gamba_sx_muscoli: 'Gamba SX - muscoli',
  gamba_dx_massa_grassa: 'Gamba DX - massa grassa', gamba_dx_muscoli: 'Gamba DX - muscoli',
  tronco_massa_grassa: 'Tronco - massa grassa', tronco_muscoli: 'Tronco - muscoli', passi: 'Passi'
};
const metricUnits = {
  peso: 'kg', bmi: '', massa_grassa: '%', acqua: '%', muscoli: '', ossa: '', grasso_viscerale: '',
  eta_metabolica: 'anni', battito: 'bpm', braccio_sx_massa_grassa: '%', braccio_dx_massa_grassa: '%',
  gamba_sx_massa_grassa: '%', gamba_dx_massa_grassa: '%', tronco_massa_grassa: '%', passi: 'passi'
};
const measurementColumns = [
  ['measured_at', 'Data e ora', 'datetime-local'],
  ['weight_kg', 'Peso (kg)', 'number'],
  ['bmi', 'BMI', 'number'],
  ['body_fat', 'Massa grassa (%)', 'number'],
  ['body_water', 'Acqua (%)', 'number'],
  ['muscle', 'Muscoli', 'number'],
  ['bone', 'Ossa', 'number'],
  ['visceral_fat', 'Grasso viscerale', 'number'],
  ['metabolic_age', 'Età metabolica', 'number'],
  ['heart_rate_bpm', 'Battito (bpm)', 'number'],
  ['left_arm_body_fat', 'Braccio SX grasso', 'number'],
  ['left_arm_muscle', 'Braccio SX muscoli', 'number'],
  ['right_arm_body_fat', 'Braccio DX grasso', 'number'],
  ['right_arm_muscle', 'Braccio DX muscoli', 'number'],
  ['left_leg_body_fat', 'Gamba SX grasso', 'number'],
  ['left_leg_muscle', 'Gamba SX muscoli', 'number'],
  ['right_leg_body_fat', 'Gamba DX grasso', 'number'],
  ['right_leg_muscle', 'Gamba DX muscoli', 'number'],
  ['trunk_body_fat', 'Tronco grasso', 'number'],
  ['trunk_muscle', 'Tronco muscoli', 'number']
];

const fmt = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 });
const fmtInt = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 });
const $ = (sel) => document.querySelector(sel);

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
  const markup = sections.map(([id, label, icon]) => `<button class="nav-button ${id === state.active ? 'active' : ''}" data-view="${id}" aria-label="${label}"><span class="nav-icon">${iconSvg(icon)}</span><span class="nav-label">${label}</span></button>`).join('');
  $('#sideNav').innerHTML = markup;
  $('#bottomNav').innerHTML = markup;
  document.querySelectorAll('.nav-button').forEach(btn => btn.addEventListener('click', () => showView(btn.dataset.view)));
}

function showView(id) {
  state.active = id;
  document.querySelectorAll('.view').forEach(v => v.classList.toggle('active-view', v.id === id));
  document.querySelectorAll('.nav-button').forEach(b => b.classList.toggle('active', b.dataset.view === id));
  $('#pageTitle').textContent = sections.find(s => s[0] === id)?.[1] || 'Riepilogo';
  if (id === 'risultati') loadResults();
  if (id === 'iniezioni') loadInjections();
  if (id === 'calendario') loadCalendar();
  if (id === 'impostazioni') loadAdmin();
}

function card(label, value, delta = '', accent = 'var(--teal)') {
  return `<article class="card" style="--accent:${accent}"><div class="label">${label}</div><div class="value">${value}</div><div class="delta">${delta}</div></article>`;
}

async function loadDashboard() {
  const data = await api('dashboard');
  const m = data.latest_measurement || {};
  $('#dashboardCards').innerHTML = [
    card('Peso corrente', m.weight_kg ? `${fmt.format(m.weight_kg)} kg` : 'N/D', 'Ultima rilevazione', 'var(--teal)'),
    card('BMI', m.bmi ? fmt.format(m.bmi) : 'N/D', 'Valore corrente', 'var(--teal)'),
    card('Massa grassa', m.body_fat ? `${fmt.format(m.body_fat)} %` : 'N/D', 'Da bilancia', 'var(--coral)'),
    card('Muscoli', m.muscle ? fmt.format(m.muscle) : 'N/D', 'Semantica originale', 'var(--lime)'),
    card('Dose GLP-1 attuale', data.next_injection ? `${fmt.format(data.next_injection.planned_dose_mg)} mg` : 'N/D', 'Prossima programmata', 'var(--violet)'),
    card('Prossima iniezione', data.next_injection ? dateTime(data.next_injection.scheduled_at) : 'Nessuna', '', 'var(--violet)'),
    card('Passi oggi', fmtInt.format(data.steps_today), 'Obiettivo 10.000', 'var(--teal)'),
    card('Allenamenti settimana', fmtInt.format(data.completed_workouts_week), 'Completati', 'var(--lime)')
  ].join('');
  drawMetricChart('overviewChart', await api('results&metric=peso&range=3m'), 'Peso');
}

async function loadResults() {
  const data = await api(`results&metric=${state.metric}&range=${state.range}`);
  $('#resultChartTitle').textContent = metrics[state.metric];
  $('#resultKpis').innerHTML = Object.entries(data.kpi || {}).map(([key, value]) => card(labelize(key), formatKpi(key, value, state.metric))).join('');
  drawMetricChart('resultChart', data, metrics[state.metric]);
}

function drawMetricChart(canvasId, data, label) {
  if (!window.Chart) return;
  state.charts[canvasId]?.destroy();
  const labels = (data.series || []).map(p => shortDate(p.date));
  const values = (data.series || []).map(p => Number(p.value));
  state.charts[canvasId] = new Chart(document.getElementById(canvasId), {
    type: 'line',
    data: { labels, datasets: [{ label, data: values, borderColor: '#22d8cf', backgroundColor: 'rgba(34,216,207,.12)', tension: .32, pointRadius: 3, fill: true }] },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: '#cfe8e7' } }, tooltip: { intersect: false, mode: 'index' } },
      scales: { x: { ticks: { color: '#9dacb1', maxRotation: 0 }, grid: { color: 'rgba(255,255,255,.06)' } }, y: { ticks: { color: '#9dacb1' }, grid: { color: 'rgba(255,255,255,.08)' } } }
    }
  });
}

async function loadInjections() {
  const rows = await api('injections');
  $('#injectionList').innerHTML = rows.map(r => `<div class="timeline-item"><strong>${r.medication_name}</strong><br>${fmt.format(r.planned_dose_mg)} mg · ${dateTime(r.scheduled_at)}<br><span class="muted">${statusIt(r.status)}</span> ${r.status === 'scheduled' ? `<button data-complete="${r.id}">Segna come effettuata</button>` : ''}</div>`).join('') || '<p class="muted">Nessuna iniezione registrata.</p>';
  document.querySelectorAll('[data-complete]').forEach(btn => btn.addEventListener('click', async () => {
    await api(`injections/${btn.dataset.complete}/complete`, { method: 'POST', body: JSON.stringify({ administered_at: new Date().toISOString().slice(0, 19).replace('T', ' '), administered_dose_mg: null }) });
    loadInjections();
  }));
}

async function loadCalendar() {
  const month = $('#monthPicker').value || new Date().toISOString().slice(0, 7);
  $('#monthPicker').value = month;
  const rows = await api(`calendar&month=${month}`);
  const byDay = rows.reduce((acc, row) => ((acc[row.event_date] ||= []).push(row), acc), {});
  const first = new Date(`${month}-01T00:00:00`);
  const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
  $('#calendarGrid').innerHTML = Array.from({ length: days }, (_, i) => {
    const day = String(i + 1).padStart(2, '0');
    const key = `${month}-${day}`;
    const dots = (byDay[key] || []).map(e => `<span class="dot ${e.type}"></span>`).join('');
    return `<div class="day"><button data-day="${key}"><strong>${i + 1}</strong><span class="dots">${dots}</span></button></div>`;
  }).join('');
  document.querySelectorAll('[data-day]').forEach(btn => btn.addEventListener('click', () => {
    const events = byDay[btn.dataset.day] || [];
    $('#dayEvents').innerHTML = `<h2>${dateIt(btn.dataset.day)}</h2>` + (events.map(e => `<p><strong>${eventLabel(e)}</strong><br><span class="muted">${eventText(e)}</span></p>`).join('') || '<p class="muted">Nessun evento.</p>');
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
  $('#injectionForm').addEventListener('submit', submitJson('injections', loadInjections));
  $('#workoutForm').addEventListener('submit', submitJson('workouts', loadCalendar));
  $('#goalForm').addEventListener('submit', submitJson('goals', () => alert('Obiettivo salvato.')));
  $('#importForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await api('imports/preview', { method: 'POST', body: form });
    $('#importPreview').innerHTML = `<p>${data.rows_found} righe trovate, ${data.rows_flagged} da verificare.</p><button id="confirmImport">Conferma importazione</button>`;
    $('#confirmImport').onclick = async () => {
      const confirmForm = new FormData(e.target);
      confirmForm.append('accepted_hashes', JSON.stringify(data.preview.filter(r => r.flagged).map(r => r.measurement_hash)));
      const done = await api('imports/confirm', { method: 'POST', body: confirmForm });
      $('#importPreview').innerHTML = `<p>${done.rows_found} righe trovate · ${done.rows_imported} importate · ${done.rows_duplicates} duplicati · ${done.rows_rejected} ignorate.</p>`;
      loadResults();
      loadDashboard();
    };
  });
  $('#pushBtn').addEventListener('click', subscribePush);
  $('#loadMeasurementsBtn').addEventListener('click', loadMeasurementsTable);
  $('#monthPicker').addEventListener('change', loadCalendar);
  $('#dashboardRange').addEventListener('change', loadDashboard);
}

function toggleSidebar() {
  state.sidebarCollapsed = !state.sidebarCollapsed;
  document.body.classList.toggle('sidebar-collapsed', state.sidebarCollapsed);
  $('#sidebarToggle').textContent = state.sidebarCollapsed ? '›' : '‹';
  $('#sidebarToggle').setAttribute('aria-label', state.sidebarCollapsed ? 'Espandi menu' : 'Comprimi menu');
  $('#sidebarToggle').setAttribute('aria-expanded', String(!state.sidebarCollapsed));
}

function submitJson(path, after) {
  return async (e) => {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(e.target));
    await api(path, { method: 'POST', body: JSON.stringify(body) });
    e.target.reset();
    after();
  };
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
  $('#metricSelect').innerHTML = Object.entries(metrics).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
  $('#goalForm select[name="metric_key"]').innerHTML = $('#metricSelect').innerHTML;
  $('#metricSelect').addEventListener('change', e => { state.metric = e.target.value; loadResults(); });
  const ranges = [['1m', '1 mese'], ['3m', '3 mesi'], ['6m', '6 mesi'], ['1y', '1 anno'], ['all', 'Sempre']];
  $('#rangeTabs').innerHTML = ranges.map(([k, v]) => `<button data-range="${k}" class="${k === state.range ? 'active' : ''}">${v}</button>`).join('');
  document.querySelectorAll('[data-range]').forEach(btn => btn.addEventListener('click', () => {
    state.range = btn.dataset.range;
    document.querySelectorAll('[data-range]').forEach(b => b.classList.toggle('active', b === btn));
    loadResults();
  }));
}

async function loadMeasurementsTable() {
  $('#measurementsStatus').textContent = 'Caricamento misurazioni...';
  const rows = await api('measurements&limit=500');
  $('#measurementsTable').hidden = false;
  $('#measurementsTable').innerHTML = renderMeasurementsTable(rows);
  $('#measurementsStatus').textContent = `${rows.length} misurazioni visualizzate. Le modifiche aggiornano anche la deduplica.`;
  document.querySelectorAll('[data-save-measurement]').forEach(btn => btn.addEventListener('click', saveMeasurementRow));
  document.querySelectorAll('[data-delete-measurement]').forEach(btn => btn.addEventListener('click', deleteMeasurementRow));
}

function renderMeasurementsTable(rows) {
  const head = `<thead><tr>${measurementColumns.map(([, label]) => `<th>${label}</th>`).join('')}<th>Azioni</th></tr></thead>`;
  const body = rows.map(row => `<tr data-measurement-row="${row.id}">
    ${measurementColumns.map(([key, label, type]) => `<td><input aria-label="${label}" name="${key}" type="${type}" step="0.1" value="${inputValue(row[key], type)}"></td>`).join('')}
    <td class="row-actions"><button type="button" data-save-measurement="${row.id}">Salva</button><button type="button" class="danger-button" data-delete-measurement="${row.id}">Elimina</button></td>
  </tr>`).join('');
  return `${head}<tbody>${body || '<tr><td colspan="21">Nessuna misurazione importata.</td></tr>'}</tbody>`;
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
  $('#todayLabel').textContent = new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).format(new Date());
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
      $('#importPreview').innerHTML = `<p>${done.rows_found} righe trovate · ${done.rows_imported} importate · ${done.rows_duplicates} duplicati · ${done.rows_rejected} ignorate.</p>`;
      loadDashboard();
      loadResults();
    };
  } catch (err) {
    $('#importPreview').innerHTML = `<p class="muted">${err.message}</p>`;
  }
}

function dateTime(value) { return value ? new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value.replace(' ', 'T'))) : ''; }
function shortDate(value) { return value ? new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short' }).format(new Date(value.replace(' ', 'T'))) : ''; }
function dateIt(value) { return new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`)); }
function labelize(key) {
  return ({
    valore_corrente: 'Valore corrente',
    valore_iniziale: 'Valore iniziale',
    variazione_totale: 'Variazione totale',
    variazione_percentuale: 'Variazione percentuale',
    media_periodo: 'Media nel periodo',
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
function inputValue(value, type) {
  if (value === null || value === undefined) return '';
  if (type === 'datetime-local') return String(value).slice(0, 16).replace(' ', 'T');
  return value;
}
function iconSvg(name) {
  const icons = {
    home: '<svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-6h6v6"></path></svg>',
    syringe: '<svg viewBox="0 0 24 24"><path d="m18 2 4 4"></path><path d="m17 7 2-2"></path><path d="M4 20l7-7"></path><path d="m9 11 4 4 6-6-4-4-6 6Z"></path><path d="m5 19 3 3"></path></svg>',
    chart: '<svg viewBox="0 0 24 24"><path d="M4 19V5"></path><path d="M4 19h17"></path><path d="M8 16v-5"></path><path d="M13 16V8"></path><path d="M18 16v-9"></path></svg>',
    calendar: '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"></rect><path d="M8 3v4"></path><path d="M16 3v4"></path><path d="M4 10h16"></path></svg>',
    settings: '<svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-2 .4 1.7 1.7 0 0 0-.5 1.4H9a1.7 1.7 0 0 0-.5-1.4 1.7 1.7 0 0 0-2-.4l-.2.1-2-3.4.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.2-1.1V10a1.7 1.7 0 0 0 1.2-1.1A1.7 1.7 0 0 0 4.4 7l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 2-.4A1.7 1.7 0 0 0 9 1.8h6a1.7 1.7 0 0 0 .5 1.4 1.7 1.7 0 0 0 2 .4l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 20.5 10v3.9a1.7 1.7 0 0 0-1.1 1.1Z"></path></svg>'
  };
  return icons[name] || '';
}
function statusIt(s) { return ({ scheduled: 'Programmata', completed: 'Effettuata', missed: 'Mancata', skipped: 'Saltata', cancelled: 'Annullata' })[s] || s; }
function eventLabel(e) { return ({ misurazione: 'Misurazione corporea', iniezione: 'GLP-1', allenamento: 'Allenamento' })[e.type]; }
function eventText(e) { if (e.type === 'misurazione') return `${fmt.format(e.weight_kg)} kg`; if (e.type === 'iniezione') return `${fmt.format(e.planned_dose_mg)} mg · ${statusIt(e.status)}`; return `${e.workout_type} · ${statusIt(e.status)}`; }
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
