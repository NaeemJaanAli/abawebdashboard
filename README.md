# ABA Data Collection Monitor — C-ARTS

A PHP + MySQL dashboard for ABA timer sessions and red-LED behaviour events, paired with an ESP32 device. Includes session start/stop, live status, dashboard-triggered red LED events, session history, date-filtered CSV export, and Wi-Fi status LED (GPIO 13).

## Important: GitHub is the source repository, not the PHP server

**GitHub Pages cannot execute PHP or connect to MySQL.** Push this project to GitHub for version control, then deploy the PHP files to a PHP-capable host (or run locally with XAMPP). The ESP32 and browser must be able to reach that host. For a local XAMPP setup, the ESP32 and computer should be on the same LAN/Wi-Fi.

## Run the interactive demo immediately (no setup)

Open `demo.html` in a modern browser. It is a self-contained UI demo: start/stop sessions, record red-LED events, search/filter history, and export CSV. Demo records are stored only in that browser using local storage. **This demo does not connect to MySQL or an ESP32.** Use the PHP dashboard (`index.php`) for the real backend after completing the setup below.

## Requirements

- PHP 8.1+ with PDO MySQL enabled
- MySQL 5.7+/MariaDB with InnoDB
- Apache or another PHP-capable web server
- Arduino IDE with ESP32 board support
- ArduinoJson library (install through Library Manager)

## Local XAMPP setup

1. Create a MySQL database named `esp32_timer` in phpMyAdmin.
2. Import `schema.sql` into that database.
3. Copy `config.example.php` to `config.php` and enter your database username/password and a long random API key.
4. In `index.php`, set `API_KEY` to exactly the same value as `API_KEY` in `config.php`.
5. Copy the project files into `C:\xampp\htdocs\esp32_timer\` (or your Apache document root). Keep `api.php`, `index.php`, and `config.php` together.
6. Open `http://localhost/esp32_timer/` on the computer.
7. Configure the ESP32 sketch below and flash it to your board.

## ESP32 configuration

Edit `esp32_timer/esp32_timer.ino` before uploading:

- Replace each `YOUR_WIFI_SSID_n` / `YOUR_WIFI_PASSWORD_n` with the actual network credentials. Keep real credentials out of GitHub.
- Replace `YOUR_SERVER_HOST` in `API_BASE` with the computer/server IP or domain. For XAMPP, use the computer's LAN IPv4 address, for example `http://192.168.0.10/esp32_timer/api.php` (not `localhost`).
- Set `API_KEY` to the same value used in `config.php` and `index.php`.
- If hosting over HTTPS, note that this sketch currently uses plain HTTPClient configuration; do not expose it to the public internet without adding TLS and stronger authentication.

### GPIO defaults

- Green LED: GPIO 25
- Red LED: GPIO 26
- Wi-Fi status LED: GPIO 13
- Start button: GPIO 12
- Stop button: GPIO 14
- Red trigger button: GPIO 27

Buttons use `INPUT_PULLUP`: wire each button between its GPIO and GND. Use appropriate current-limiting resistors for LEDs and verify your board's pin constraints before wiring.

## Deploy to a PHP host

1. Push the source repository to GitHub (steps below).
2. Create a PHP/MySQL site on your hosting provider.
3. Create a database and database user; import `schema.sql`.
4. Upload `index.php`, `api.php`, and a locally configured `config.php` to the same web directory. Do not upload `config.php` to GitHub.
5. Update `API_KEY` in `index.php` and the ESP32 sketch. Set the ESP32 `API_BASE` to the hosted API URL.
6. Ensure the host permits the ESP32 to reach the API. Shared hosting firewalls, TLS certificates, and outbound restrictions may affect connectivity.

**Security note:** This project uses a shared API key that is embedded in browser JavaScript, so it is suitable only for a trusted/private demo network. Anyone who can load the page can inspect that key. Do not use it to protect sensitive or identifiable client data on a public internet deployment. Add proper user authentication, authorization, HTTPS, rate limiting, and an ESP32-specific credential before production use. Avoid storing identifiable participant data unless your organization has approved the privacy and security controls.

## Push to GitHub

From this folder, run:

```bash
git init
git add .
git status   # confirm config.php and real Wi-Fi credentials are NOT staged
git commit -m "Prepare ABA timer dashboard for deployment"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/aba-data-collection-monitor.git
git push -u origin main
```

Create the empty repository on GitHub first and replace the remote URL. Never commit `config.php` or real Wi-Fi credentials. If credentials were ever committed in an earlier version, rotate them; deleting them in a later commit does not remove them from Git history.

## API overview

- `GET api.php?action=status&api_key=...` — current timer state
- `GET api.php?action=sessions&api_key=...` — latest 500 sessions
- `GET api.php?action=sessions&api_key=...&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` — date-filtered session records
- `POST api.php?action=start` — start session
- `POST api.php?action=stop` — stop session
- `POST api.php?action=red_event` — record a red-LED event from ESP32
- `POST api.php?action=queue_red` — queue a red LED trigger from dashboard
- `GET api.php?action=next_command&api_key=...` — ESP32 polls queued commands

POST requests send JSON containing `action` and `api_key`.

## Notes

- All display and event times use Pakistan Standard Time (`Asia/Karachi`, UTC+05:00).
- Date filtering is based on session start date.
- The dashboard displays up to 500 sessions; CSV export can query the full date-filtered result set.
- This package contains source code, not a hosted/live deployment. Hosting credentials and a GitHub repository URL must be supplied by the owner.
