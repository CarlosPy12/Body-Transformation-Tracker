const state = { user: null, csrf: null, active: 'riepilogo', range: '3m', metric: 'peso', charts: {}, deferredInstall: null };
const sections = [
  ['riepilogo', 'Riepilogo', '⌂'],
  ['iniezioni', 'Iniezioni', '⌁'],
  ['risultati', 'Risultati', '▥'],
  ['calendario', 'Calendario', '□'],
  ['impostazioni', 'Impostazioni', '⚙']
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
  const json = await res.json();
  if (!json.success) throw new Error(json.error?.message || 'Errore inatteso');
  return json.data;
}

function initNav() {
  const markup = sections.map(([id, label, icon]) => `<button class="nav-button ${id === state.active ? 'active' : ''}" data-view="${id}" aria-label="${label}"><span>${icon}</span><span>${label}</span></button>`).join('');
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
  $('#resultKpis').innerHTML = Object.entries(data.kpi || {}).map(([key, value]) => card(labelize(key), typeof value === 'number' ? fmt.format(value) : 'N/D')).join('');
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
  $('#monthPicker').addEventListener('change', loadCalendar);
  $('#dashboardRange').addEventListener('change', loadDashboard);
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
function labelize(key) { return key.replaceAll('_', ' '); }
function statusIt(s) { return ({ scheduled: 'Programmata', completed: 'Effettuata', missed: 'Mancata', skipped: 'Saltata', cancelled: 'Annullata' })[s] || s; }
function eventLabel(e) { return ({ misurazione: 'Misurazione corporea', iniezione: 'GLP-1', allenamento: 'Allenamento' })[e.type]; }
function eventText(e) { if (e.type === 'misurazione') return `${fmt.format(e.weight_kg)} kg`; if (e.type === 'iniezione') return `${fmt.format(e.planned_dose_mg)} mg · ${statusIt(e.status)}`; return `${e.workout_type} · ${statusIt(e.status)}`; }
function urlBase64ToUint8Array(base64String) { const padding = '='.repeat((4 - base64String.length % 4) % 4); const rawData = atob((base64String + padding).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...rawData].map(c => c.charCodeAt(0))); }

window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); state.deferredInstall = e; $('#installBtn').hidden = false; });
$('#installBtn')?.addEventListener('click', async () => { await state.deferredInstall?.prompt(); $('#installBtn').hidden = true; });

(async function init() {
  setupLoginForm();
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js');
  const data = await api('auth/me');
  if (data.user) boot(data); else $('#loginView').hidden = false;
})();
