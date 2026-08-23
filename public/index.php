<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Support\Env;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#071011">
  <meta name="vapid-public-key" content="<?= htmlspecialchars((string) Env::get('VAPID_PUBLIC_KEY', ''), ENT_QUOTES) ?>">
  <title>Body Tracker</title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/app.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
  <script src="/assets/app.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#main">Vai al contenuto</a>
  <div id="loginView" class="login-shell" hidden>
    <form id="loginForm" class="login-card">
      <div class="brand-mark" aria-hidden="true">BT</div>
      <h1>Accedi</h1>
      <p>Monitora peso, GLP-1, allenamenti e passi in un unico posto.</p>
      <label>Email <input name="email" type="email" autocomplete="email" required></label>
      <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
      <button type="submit">Entra</button>
      <output id="loginError" role="alert"></output>
    </form>
  </div>

  <div id="appShell" class="app-shell" hidden>
    <aside class="sidebar" aria-label="Navigazione principale">
      <div class="brand-row"><span class="brand-mark">BT</span><strong>Body Tracker</strong></div>
      <nav id="sideNav"></nav>
      <button id="logoutBtn" class="ghost-button">Esci</button>
    </aside>

    <main id="main" class="main-panel">
      <header class="topbar">
        <div>
          <p id="todayLabel" class="muted"></p>
          <h1 id="pageTitle">Riepilogo</h1>
        </div>
        <button id="installBtn" class="ghost-button" hidden>Installa PWA</button>
      </header>

      <section id="riepilogo" class="view active-view" aria-labelledby="pageTitle">
        <div class="metric-grid" id="dashboardCards"></div>
        <section class="panel wide">
          <div class="section-head">
            <h2>Andamento peso</h2>
            <select id="dashboardRange" aria-label="Intervallo grafico riepilogo">
              <option value="1m">1 mese</option>
              <option value="3m" selected>3 mesi</option>
              <option value="6m">6 mesi</option>
              <option value="1y">1 anno</option>
              <option value="all">Sempre</option>
            </select>
          </div>
          <canvas id="overviewChart" height="130"></canvas>
        </section>
      </section>

      <section id="iniezioni" class="view">
        <div class="two-column">
          <form id="injectionForm" class="panel form-panel">
            <h2>Programma iniezione</h2>
            <label>Farmaco <input name="medication_name" value="GLP-1"></label>
            <label>Data e ora <input name="scheduled_at" type="datetime-local" required></label>
            <label>Dose prevista (mg) <input name="planned_dose_mg" type="number" min="0" step="0.1" required></label>
            <label>Note <textarea name="notes" rows="3"></textarea></label>
            <button type="submit">Programma iniezione</button>
          </form>
          <div class="panel">
            <h2>Storico</h2>
            <div id="injectionList" class="timeline"></div>
          </div>
        </div>
      </section>

      <section id="risultati" class="view">
        <div class="filters panel">
          <div class="segmented" id="rangeTabs" role="tablist" aria-label="Intervallo risultati"></div>
          <label>Metrica <select id="metricSelect"></select></label>
        </div>
        <div class="metric-grid compact" id="resultKpis"></div>
        <section class="panel wide">
          <h2 id="resultChartTitle">Grafico</h2>
          <canvas id="resultChart" height="150"></canvas>
        </section>
        <section class="panel">
          <h2>Importa CSV bilancia</h2>
          <form id="importForm" class="inline-form" enctype="multipart/form-data">
            <input name="file" type="file" accept=".csv,text/csv,text/plain" required>
            <button type="submit">Analizza</button>
          </form>
          <div id="importPreview"></div>
        </section>
      </section>

      <section id="calendario" class="view">
        <div class="section-head">
          <h2 id="calendarTitle">Calendario</h2>
          <input id="monthPicker" type="month" aria-label="Mese calendario">
        </div>
        <div id="calendarGrid" class="calendar-grid"></div>
        <div id="dayEvents" class="panel"></div>
      </section>

      <section id="impostazioni" class="view">
        <div class="two-column">
          <form id="goalForm" class="panel form-panel">
            <h2>Obiettivi</h2>
            <label>Metrica <select name="metric_key"></select></label>
            <label>Target <input name="target_value" type="number" step="0.1" required></label>
            <label>Data target <input name="target_date" type="date"></label>
            <button type="submit">Salva obiettivo</button>
          </form>
          <form id="workoutForm" class="panel form-panel">
            <h2>Pianifica allenamento</h2>
            <label>Tipo <input name="workout_type" list="workoutTypes" required></label>
            <datalist id="workoutTypes"><option>Forza</option><option>Cardio</option><option>Corsa</option><option>Camminata</option><option>Mobilità</option><option>Altro</option></datalist>
            <label>Data e ora <input name="scheduled_at" type="datetime-local" required></label>
            <label>Durata (minuti) <input name="duration_minutes" type="number" min="1"></label>
            <button type="submit">Pianifica</button>
          </form>
        </div>
        <div class="panel">
          <div class="section-head"><h2>Notifiche push</h2><button id="pushBtn">Attiva notifiche</button></div>
          <p class="muted">Promemoria reali per iniezioni e allenamenti programmati.</p>
        </div>
        <div id="adminPanel" class="panel" hidden>
          <h2>Amministrazione</h2>
          <div class="admin-tabs">Utenti · Importazioni · Sincronizzazione · Notifiche · Configurazione</div>
          <div id="adminUsers"></div>
        </div>
      </section>
    </main>

    <nav id="bottomNav" class="bottom-nav" aria-label="Navigazione principale"></nav>
  </div>
</body>
</html>
