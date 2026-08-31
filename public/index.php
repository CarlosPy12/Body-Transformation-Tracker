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
      <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Comprimi menu" aria-expanded="true"></button>
      <div class="brand-row">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 48 48" focusable="false">
            <circle cx="24" cy="24" r="18"></circle>
            <path d="M10 25h8l4-10 8 20 5-13 3 3h5"></path>
          </svg>
        </span>
        <strong>Body Tracker</strong>
      </div>
      <nav id="sideNav"></nav>
      <button id="logoutBtn" class="ghost-button">Esci</button>
    </aside>

    <main id="main" class="main-panel">
      <header class="topbar">
        <div>
          <h1 id="pageTitle">Riepilogo</h1>
          <p id="todayLabel" class="muted"></p>
        </div>
        <div class="topbar-actions">
          <div class="segmented top-presets" id="topRangeTabs" role="tablist" aria-label="Filtri periodo"></div>
          <div class="date-range" aria-label="Periodo dati">
            <label>Da <input id="rangeStart" type="date"></label>
            <label>A <input id="rangeEnd" type="date"></label>
          </div>
          <button id="installBtn" class="ghost-button" hidden>Installa PWA</button>
        </div>
      </header>

      <section id="aggiungi" class="view">
        <div class="quick-grid entry-launcher">
          <button class="entry-card panel" type="button" data-dialog="quickInjectionDialog">
            <strong>Iniezione Mounjaro</strong>
            <span>Programma o registra una dose effettuata.</span>
          </button>
          <button class="entry-card panel" type="button" data-dialog="quickWorkoutDialog">
            <strong>Allenamento</strong>
            <span>Programma o registra una sessione effettuata.</span>
          </button>
          <button class="entry-card panel" type="button" data-dialog="quickGoalDialog">
            <strong>Target</strong>
            <span>Aggiorna obiettivi di peso, BMI o composizione.</span>
          </button>
          <button class="entry-card panel" type="button" data-dialog="quickStepsDialog">
            <strong>Passi</strong>
            <span>Correggi o inserisci il totale giornaliero.</span>
          </button>
          <button class="entry-card panel" type="button" data-dialog="quickMeasurementDialog">
            <strong>Misurazione corporea</strong>
            <span>Aggiungi peso e composizione corporea.</span>
          </button>
        </div>

        <dialog id="quickInjectionDialog" class="entry-dialog">
          <form id="quickInjectionForm" class="form-panel" method="dialog">
            <div class="dialog-head">
              <h2>Iniezione Mounjaro</h2>
              <button class="ghost-button icon-button" type="button" data-close-dialog>×</button>
            </div>
            <label>Farmaco <input name="medication_name" value="Mounjaro"></label>
            <label>Data e ora <input name="scheduled_at" type="datetime-local" required></label>
            <label>Dose (mg) <input name="planned_dose_mg" type="number" min="0" step="0.1" required></label>
            <label class="check-row"><input name="completed" type="checkbox" value="1" checked> Segna anche come effettuata</label>
            <button type="submit">Salva iniezione</button>
          </form>
        </dialog>

        <dialog id="quickWorkoutDialog" class="entry-dialog">
          <form id="quickWorkoutForm" class="form-panel" method="dialog">
            <div class="dialog-head">
              <h2>Allenamento</h2>
              <button class="ghost-button icon-button" type="button" data-close-dialog>×</button>
            </div>
            <label>Tipo <input name="workout_type" list="workoutTypes" required></label>
            <label>Data e ora <input name="scheduled_at" type="datetime-local" required></label>
            <label>Durata (minuti) <input name="duration_minutes" type="number" min="1"></label>
            <label class="check-row"><input name="completed" type="checkbox" value="1" checked> Segna anche come effettuato</label>
            <button type="submit">Salva allenamento</button>
          </form>
        </dialog>

        <dialog id="quickGoalDialog" class="entry-dialog">
          <form id="quickGoalForm" class="form-panel" method="dialog">
            <div class="dialog-head">
              <h2>Target</h2>
              <button class="ghost-button icon-button" type="button" data-close-dialog>×</button>
            </div>
            <label>Metrica <select name="metric_key"></select></label>
            <label>Target <input name="target_value" type="number" step="0.1" required></label>
            <label>Data target <input name="target_date" type="date"></label>
            <button type="submit">Aggiorna target</button>
          </form>
        </dialog>

        <dialog id="quickStepsDialog" class="entry-dialog">
          <form id="quickStepsForm" class="form-panel" method="dialog">
            <div class="dialog-head">
              <h2>Passi</h2>
              <button class="ghost-button icon-button" type="button" data-close-dialog>×</button>
            </div>
            <label>Data <input name="step_date" type="date" required></label>
            <label>Passi <input name="steps" type="number" min="0" step="1" required></label>
            <button type="submit">Salva passi</button>
          </form>
        </dialog>

        <dialog id="quickMeasurementDialog" class="entry-dialog">
          <form id="quickMeasurementForm" class="form-panel" method="dialog">
            <div class="dialog-head">
              <h2>Misurazione corporea</h2>
              <button class="ghost-button icon-button" type="button" data-close-dialog>×</button>
            </div>
            <label>Data e ora <input name="measured_at" type="datetime-local" required></label>
            <label>Peso (kg) <input name="weight_kg" type="number" step="0.1" required></label>
            <label>BMI <input name="bmi" type="number" step="0.1"></label>
            <label>Massa grassa (%) <input name="body_fat" type="number" step="0.1"></label>
            <label>Acqua (%) <input name="body_water" type="number" step="0.1"></label>
            <label>Muscoli (%) <input name="muscle" type="number" step="0.1"></label>
            <button type="submit">Salva misurazione</button>
          </form>
        </dialog>
      </section>

      <section id="riepilogo" class="view active-view" aria-labelledby="pageTitle">
        <div class="summary-stack" id="dashboardCards"></div>
        <section class="panel wide chart-panel">
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
        <div class="injection-layout">
          <form id="injectionForm" class="panel form-panel horizontal-form">
            <h2>Programma iniezione</h2>
            <div class="injection-form-grid">
              <label>Farmaco <input name="medication_name" value="Mounjaro"></label>
              <label>Data partenza <input name="start_date" type="date" required></label>
              <label>Ora <input name="start_time" type="time" required></label>
              <label>Ricorrenza settimanale fino a <input name="recurrence_until" type="date"></label>
              <label>Dose prevista (mg) <input name="planned_dose_mg" type="number" min="0" step="0.1" required></label>
              <label class="notes-field">Note <textarea name="notes" rows="1"></textarea></label>
              <button type="submit">Programma calendario</button>
            </div>
          </form>
          <div class="panel measurements-panel">
            <h2>Storico</h2>
            <div class="table-wrap">
              <table id="injectionTable" class="data-table"></table>
            </div>
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
        <section class="panel measurements-panel">
          <div class="section-head">
            <div>
              <h2>Misurazioni</h2>
              <p class="muted">Visualizza, correggi o cancella le rilevazioni importate.</p>
            </div>
            <button id="loadMeasurementsBtn" class="ghost-button" type="button">Visualizza tabella</button>
          </div>
          <div id="measurementsStatus" class="muted"></div>
          <div class="table-wrap">
            <table id="measurementsTable" class="data-table" hidden></table>
          </div>
        </section>
      </section>

      <section id="calendario" class="view">
        <div class="calendar-head">
          <h2 id="calendarTitle">Calendario</h2>
          <div class="calendar-controls">
            <button id="prevMonthBtn" class="ghost-button icon-button" type="button" aria-label="Mese precedente"></button>
            <input id="monthPicker" type="month" aria-label="Mese calendario">
            <button id="nextMonthBtn" class="ghost-button icon-button" type="button" aria-label="Mese successivo"></button>
          </div>
        </div>
        <div id="calendarLegend" class="calendar-legend"></div>
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
            <label class="check-row"><input name="completed" type="checkbox" value="1"> Segna come effettuato</label>
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
