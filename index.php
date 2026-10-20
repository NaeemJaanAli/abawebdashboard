
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ABA Data Collection Monitor-CARTS</title>

<style>
:root {
  color-scheme: dark;
  --bg: #0b1220;
  --panel: #151f31;
  --panel2: #111c2d;
  --border: #29384e;
  --text: #f1f5f9;
  --muted: #94a9c6;
  --blue: #38bdf8;
  --green: #22c55e;
  --red: #ef4444;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  padding: 24px;
  background: var(--bg);
  color: var(--text);
  font-family: Inter, "Segoe UI", Arial, sans-serif;
}

main { max-width: 1500px; margin: auto; }

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 28px;
}

h1 { margin: 0; font-size: clamp(25px, 3vw, 34px); }
h2 { margin: 0; font-size: 19px; }
h3 { margin: 0 0 8px; font-size: 17px; }

.subtitle { color: var(--muted); margin-top: 7px; }
.muted { color: var(--muted); font-size: 13px; }
.header-right { display: flex; gap: 10px; align-items: center; }

.pill {
  padding: 9px 13px;
  border: 1px solid var(--border);
  background: #172337;
  border-radius: 22px;
  font-size: 13px;
}

.dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #64748b;
  margin-right: 7px;
}

.dot.online { background: var(--green); }
.dot.red { background: var(--red); }

.metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 22px;
}

.card {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 17px;
  padding: 22px;
  min-width: 0;
}

.metric-label {
  color: var(--muted);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .6px;
}

.metric-value {
  color: var(--blue);
  font-size: 31px;
  font-weight: 750;
  margin-top: 10px;
  font-variant-numeric: tabular-nums;
}

.main-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 22px;
}

.status-title {
  font-size: 13px;
  color: var(--muted);
  letter-spacing: .5px;
}

.timer-status {
  font-size: 35px;
  font-weight: 800;
  margin-top: 12px;
}

.timer-display {
  font-size: clamp(34px, 5vw, 57px);
  letter-spacing: -1px;
  font-variant-numeric: tabular-nums;
  margin: 12px 0 18px;
  white-space: nowrap;
}

button, input {
  font: inherit;
}

button {
  border: 0;
  border-radius: 9px;
  padding: 12px 17px;
  cursor: pointer;
  font-weight: 700;
  transition: opacity .15s ease;
}

button:hover { opacity: .88; }
button:disabled { opacity: .4; cursor: not-allowed; }

.btn-start { background: var(--green); color: #052e16; }
.btn-stop { background: var(--red); color: white; }
.btn-red-led { background: #b91c1c; color: #fff; }

input[type="date"] {
  background: var(--panel2);
  color: var(--text);
  border: 1px solid var(--border);
  padding: 10px 11px;
  border-radius: 9px;
  font: inherit;
}
.btn-export { background: #3b82f6; color: white; }
.btn-secondary {
  background: #243249;
  color: var(--text);
  border: 1px solid var(--border);
}

.hardware-row {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 16px 0;
  border-bottom: 1px solid var(--border);
}

.hardware-row:last-child { border-bottom: 0; }

.led-indicator {
  width: 15px;
  height: 15px;
  border-radius: 50%;
  background: #526178;
  flex-shrink: 0;
}

.led-indicator.green-on {
  background: var(--green);
  box-shadow: 0 0 13px #22c55e77;
}

.led-indicator.red-on {
  background: var(--red);
  box-shadow: 0 0 13px #ef444477;
}

.hardware-name { font-weight: 700; font-size: 18px; }
.hardware-detail { color: var(--muted); font-size: 13px; margin-top: 5px; }

.table-card { padding: 22px; }

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}

.table-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

input[type="search"] {
  background: var(--panel2);
  color: var(--text);
  border: 1px solid var(--border);
  padding: 11px 13px;
  border-radius: 9px;
  min-width: 220px;
  outline: none;
}

input[type="search"]:focus { border-color: var(--blue); }

.table-wrap {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: 10px;
}

table {
  width: 100%;
  min-width: 920px;
  border-collapse: collapse;
  font-size: 13px;
}

thead { background: #101a2b; }

th {
  text-align: left;
  color: var(--muted);
  font-weight: 600;
  white-space: nowrap;
}

th, td {
  padding: 15px 13px;
  border-bottom: 1px solid var(--border);
  vertical-align: top;
}

tbody tr:last-child td { border-bottom: 0; }
tbody tr:hover { background: #1b2a40; }

.event-time {
  color: #7dd3fc;
  white-space: nowrap;
  padding: 3px 0;
  font-variant-numeric: tabular-nums;
}

.event-count {
  display: inline-block;
  background: #17364a;
  color: #7dd3fc;
  border-radius: 15px;
  padding: 4px 8px;
}

.state {
  display: inline-block;
  padding: 5px 9px;
  border-radius: 15px;
  font-size: 11px;
  background: #263449;
}

.state.running { background: #14532d; color: #bbf7d0; }
.state.stopped { background: #273449; color: #cbd5e1; }

.empty {
  color: var(--muted);
  text-align: center;
  padding: 28px;
}

.footer {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 15px;
}

.message { color: var(--muted); font-size: 13px; margin-top: 14px; }
.message.error { color: #fca5a5; }
.message.success { color: #86efac; }

@media (max-width: 850px) {
  body { padding: 15px; }
  .metrics { grid-template-columns: 1fr; gap: 12px; }
  .main-grid { grid-template-columns: 1fr; gap: 12px; }
  .card { padding: 18px; }
}

@media (max-width: 480px) {
  .header-right { width: 100%; flex-wrap: wrap; }
  .timer-status { font-size: 29px; }
  .timer-display { font-size: 35px; }
  input[type="search"] { min-width: 0; width: 100%; }
  .table-actions { width: 100%; }
}
</style>
</head>

<body>
<main>

<header>
  <div>
<h1>ABA Data Collection Monitor-CARTS</h1>
    <div class="subtitle">Unified Session &amp; Event Analytics</div>
  </div>

  <div class="header-right">
    <span class="pill">
      <span class="dot online"></span>Local Device Monitor
    </span>
    <span class="pill" id="connectionStatus">Connecting...</span>
  </div>
</header>

<section class="metrics">
  <div class="card">
    <div class="metric-label">Total Sessions Loaded</div>
    <div class="metric-value" id="totalSessions">0</div>
  </div>

  <div class="card">
    <div class="metric-label">Total Red LED Presses</div>
    <div class="metric-value" id="totalPresses">0</div>
  </div>

  <div class="card">
    <div class="metric-label">Total Session Duration</div>
    <div class="metric-value" id="totalDuration">00:00:00</div>
  </div>
</section>

<section class="main-grid">

  <div class="card">
    <div class="status-title">TIMER STATUS</div>

    <div class="timer-status" id="timerStatus">STOPPED</div>
    <div class="muted" id="sessionInfo">No session recorded</div>

    <div class="timer-display" id="timerDisplay">
      00:00:00.000
    </div>

    <button class="btn-start" id="startBtn"
      onclick="sendCommand('start')">
      Session START
    </button>

    <button class="btn-stop" id="stopBtn"
      onclick="sendCommand('stop')">
      Session STOP
    </button>

    <button class="btn-red-led" id="redTriggerBtn"
      onclick="triggerDashboardRed()" disabled>
      TRIGGER Behaviour
    </button>
  </div>

  <div class="card">
    <div class="status-title">HARDWARE STATUS</div>

    <div class="hardware-row">
      <span class="led-indicator" id="greenIndicator"></span>
      <div>
        <div class="hardware-name">Green LED</div>
        <div class="hardware-detail" id="greenText">
          Waiting for server
        </div>
      </div>
    </div>

    <div class="hardware-row">
      <span class="led-indicator" id="redIndicator"></span>
      <div>
        <div class="hardware-name">Red LED</div>
        <div class="hardware-detail" id="redText">
          No recorded trigger
        </div>
      </div>
    </div>

    <div class="muted">
      Red LED pulse duration: 500 milliseconds
    </div>
  </div>

</section>

<section class="card table-card">
  <div class="table-header">
    <div>
      <h2>Session History &amp; Data Logs</h2>
      <div class="muted" style="margin-top:6px">
        One row per session, with all Attempted Behaviours recorded
      </div>
    </div>

    <div class="table-actions">
      <input type="search" id="searchInput"
        placeholder="Search session or timestamp...">

      <label class="muted" for="csvStartDate">From</label>
      <input type="date" id="csvStartDate" aria-label="CSV start date">
      <label class="muted" for="csvEndDate">To</label>
      <input type="date" id="csvEndDate" aria-label="CSV end date">

      <button class="btn-export" onclick="exportCSV()">
        Export CSV
      </button>

      <button class="btn-secondary" onclick="refreshAll()">
        Refresh
      </button>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Session Start Time</th>
          <th>Session End Time</th>
          <th>Session Duration</th>
          <th>Behaviours Time</th>
          <th>Behaviours Frequencey</th>
          <th>Status</th>
        </tr>
      </thead>

      <tbody id="sessionsBody">
        <tr>
          <td colspan="7" class="empty">Loading session data...</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <span class="muted" id="recordInfo">0 records</span>
    <span class="muted" id="lastUpdated">Waiting for data...</span>
  </div>

  <div id="message" class="message"></div>
</section>

</main>

<script>
// Must match config.php.
// Must match API_KEY in config.php. For a public deployment, add real user authentication.
const API_KEY = 'aba1234';
const API_URL = 'api.php';

let allSessions = [];
let timerRunning = false;
let elapsedMs = 0;
let syncTime = Date.now();
let activeSessionId = null;
let refreshBusy = false;

async function api(action, method = 'GET', params = {}) {
  let url = API_URL + '?action=' + encodeURIComponent(action);
  if (method === 'GET') {
    url += '&api_key=' + encodeURIComponent(API_KEY);
    for (const [key, value] of Object.entries(params)) {
      if (value !== '' && value != null) {
        url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
      }
    }
  }
  const options = { method, cache: 'no-store' };

  if (method !== 'GET') {
    options.headers = {
      'Content-Type': 'application/json'
    };

    options.body = JSON.stringify({
      action,
      api_key: API_KEY
    });
  }

  const response = await fetch(url, options);
  const data = await response.json();

  if (!response.ok || !data.ok) {
    throw new Error(data.error || 'API request failed');
  }

  return data;
}

function formatDuration(ms, decimals = true) {
  ms = Math.max(0, Math.floor(Number(ms) || 0));

  const hours = Math.floor(ms / 3600000);
  const mins = Math.floor(ms / 60000) % 60;
  const secs = Math.floor(ms / 1000) % 60;
  const millis = ms % 1000;

  let result =
    String(hours).padStart(2, '0') + ':' +
    String(mins).padStart(2, '0') + ':' +
    String(secs).padStart(2, '0');

  if (decimals) {
    result += '.' + String(millis).padStart(3, '0');
  }

  return result;
}

function setText(id, value) {
  document.getElementById(id).textContent = value ?? '—';
}

function setLed(id, active, type) {
  const element = document.getElementById(id);
  element.className = 'led-indicator';

  if (active) {
    element.classList.add(type + '-on');
  }
}

function parseEventTimes(value) {
  if (!value) return [];
  return String(value)
    .split(' | ')
    .map(t => t.trim())
    .filter(Boolean);
}

function renderSessions() {
  const query = document.getElementById('searchInput')
    .value.trim().toLowerCase();

  const filtered = allSessions.filter(s => {
    const content = [
      s.id,
      s.start_time,
      s.end_time,
      s.status,
      s.red_led_on_times,
      s.red_press_count
    ].join(' ').toLowerCase();

    return content.includes(query);
  });

  const tbody = document.getElementById('sessionsBody');
  tbody.replaceChildren();

  if (filtered.length === 0) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'empty';
    td.textContent = allSessions.length
      ? 'No matching sessions found.'
      : 'No sessions recorded yet.';
    tr.appendChild(td);
    tbody.appendChild(tr);
  }

  for (const s of filtered) {
    const tr = document.createElement('tr');

    function cell(value) {
      const td = document.createElement('td');
      td.textContent = value ?? '—';
      tr.appendChild(td);
      return td;
    }

    cell(s.id);
    cell(s.start_time || '—');
    cell(s.end_time || 'In progress');

    cell(
      s.duration_ms == null
        ? 'In progress'
        : formatDuration(s.duration_ms)
    );

    const timeCell = document.createElement('td');
    const times = parseEventTimes(s.red_led_on_times);

    if (times.length) {
      for (const time of times) {
        const line = document.createElement('div');
        line.className = 'event-time';
        line.textContent = time;
        timeCell.appendChild(line);
      }
    } else {
      timeCell.textContent = 'No Red LED presses';
      timeCell.className = 'muted';
    }

    tr.appendChild(timeCell);

    const countCell = cell(s.red_press_count);
    const count = document.createElement('span');
    count.className = 'event-count';
    count.textContent = s.red_press_count;
    countCell.replaceChildren(count);

    const statusCell = document.createElement('td');
    const badge = document.createElement('span');
    badge.className = 'state ' +
      (s.status === 'running' ? 'running' : 'stopped');
    badge.textContent = String(s.status || 'unknown').toUpperCase();
    statusCell.appendChild(badge);
    tr.appendChild(statusCell);

    tbody.appendChild(tr);
  }

  setText(
    'recordInfo',
    filtered.length + ' displayed / ' +
    allSessions.length + ' loaded sessions'
  );
}

async function sendCommand(action) {
  const start = document.getElementById('startBtn');
  const stop = document.getElementById('stopBtn');

  start.disabled = true;
  stop.disabled = true;

  setMessage('Sending ' + action.toUpperCase() + ' command...');

  try {
    await api(action, 'POST');
    setMessage(action.toUpperCase() + ' command accepted.', false);
    await refreshAll();
  } catch (error) {
    setMessage(error.message, true);
  } finally {
    start.disabled = timerRunning;
    stop.disabled = !timerRunning;
  }
}

async function triggerDashboardRed() {
  const button = document.getElementById('redTriggerBtn');
  button.disabled = true;
  setMessage('Sending Red LED trigger to ESP32...');
  try {
    await api('queue_red', 'POST');
    setMessage('Red LED command queued. ESP32 will trigger it while the timer is running.', false);
  } catch (error) {
    setMessage(error.message, true);
  } finally {
    button.disabled = !timerRunning;
  }
}

function setMessage(message, isError = false) {
  const element = document.getElementById('message');
  element.textContent = message;
  element.className = 'message' +
    (isError ? ' error' : ' success');
}

async function refreshAll() {
  if (refreshBusy) return;
  refreshBusy = true;

  try {
    const [status, sessionsResult] = await Promise.all([
      api('status'),
      api('sessions')
    ]);

    timerRunning = status.running;
    elapsedMs = Number(status.elapsed_ms || 0);
    syncTime = Date.now();
    activeSessionId = status.session_id;

    setText('connectionStatus', 'Server connected');
    setText('timerStatus', timerRunning ? 'RUNNING' : 'STOPPED');

    setText(
      'sessionInfo',
      activeSessionId
        ? 'Session #' + activeSessionId
        : 'No active session'
    );

    setText('timerDisplay', formatDuration(elapsedMs));

    document.getElementById('startBtn').disabled = timerRunning;
    document.getElementById('stopBtn').disabled = !timerRunning;
    document.getElementById('redTriggerBtn').disabled = !timerRunning;

    setLed('greenIndicator', timerRunning, 'green');
    setText('greenText', timerRunning
      ? 'ON — timer running'
      : 'OFF — timer stopped');

    allSessions = sessionsResult.sessions || [];

    // Latest session first, so summary reflects loaded data.
    const totalPresses = allSessions.reduce(
      (sum, s) => sum + Number(s.red_press_count || 0), 0
    );

    const totalDuration = allSessions.reduce(
      (sum, s) => sum + Number(s.duration_ms || 0), 0
    );

    setText('totalSessions', allSessions.length);
    setText('totalPresses', totalPresses);
    setText('totalDuration', formatDuration(totalDuration, false));

    // Show whether the most recent recorded trigger occurred
    // within the last 500 ms. This is event-based, not a
    // hardware telemetry/heartbeat signal.
    const latestSession = allSessions.find(
      s => s.red_led_on_times
    );

    const latestTimes = latestSession
      ? parseEventTimes(latestSession.red_led_on_times)
      : [];

    if (latestTimes.length) {
      const latestTime = latestTimes[latestTimes.length - 1];
      const timestamp = new Date(
        latestTime.replace(' ', 'T') + '+05:00'
      ).getTime();

      const age = Date.now() - timestamp;
      const recentlyTriggered = age >= 0 && age < 500;

      setLed('redIndicator', recentlyTriggered, 'red');
      setText('redText',
        'Last trigger: ' + latestTime
      );
    } else {
      setLed('redIndicator', false, 'red');
      setText('redText', 'No recorded trigger');
    }

    renderSessions();

    setText(
      'lastUpdated',
      'Last updated: ' + new Date().toLocaleTimeString()
    );

  } catch (error) {
    setText('connectionStatus', 'Connection error');
    setMessage(error.message, true);
  } finally {
    refreshBusy = false;
  }
}

// Export sessions from the database using an optional inclusive date range.
async function exportCSV() {
  const startDate = document.getElementById('csvStartDate').value;
  const endDate = document.getElementById('csvEndDate').value;

  if (startDate && endDate && startDate > endDate) {
    setMessage('From date must not be after To date.', true);
    return;
  }

  try {
    setMessage('Preparing CSV export...');
    const result = await api('sessions', 'GET', {
      start_date: startDate,
      end_date: endDate
    });
    const sessions = result.sessions || [];

    const headers = [
      'ID', 'Session Start Time', 'Session End Time',
      'Session Duration (ms)', 'Red LED ON Time',
      'Red LED Press Count', 'Status'
    ];

    const rows = sessions.map(s => [
      s.id, s.start_time, s.end_time, s.duration_ms,
      parseEventTimes(s.red_led_on_times).join(' | '),
      s.red_press_count, s.status
    ]);

    function escapeCSV(value) {
      const text = value == null ? '' : String(value);
      return '"' + text.replace(/"/g, '""') + '"';
    }

    const csv = [headers, ...rows]
      .map(row => row.map(escapeCSV).join(','))
      .join('\r\n');

    const blob = new Blob(
      ['\uFEFF' + csv],
      { type: 'text/csv;charset=utf-8;' }
    );

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;

    let filename = 'aba_session_data';
    if (startDate) filename += '_from_' + startDate;
    if (endDate) filename += '_to_' + endDate;
    link.download = filename + '.csv';

    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);

    setMessage('CSV exported: ' + sessions.length + ' session(s).', false);
  } catch (error) {
    setMessage(error.message, true);
  }
}

document.getElementById('searchInput')
  .addEventListener('input', renderSessions);

// Smooth timer display between server refreshes.
setInterval(() => {
  if (timerRunning) {
    setText(
      'timerDisplay',
      formatDuration(elapsedMs + Date.now() - syncTime)
    );
  }
}, 50);

// Refresh database/status once per second.
refreshAll();
setInterval(refreshAll, 1000);
</script>
</body>
</html>
