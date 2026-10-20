#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>
#include <sys/time.h>

// ==========================================
// WIFI NETWORKS
// SSID/password must match router settings exactly.
// Note: "Admin Room" has one space here.
// ==========================================
struct WiFiCredential {
  const char* ssid;
  const char* password;
};

WiFiCredential wifiNetworks[] = {
{"YOUR_WIFI_SSID_1", "YOUR_WIFI_PASSWORD_1"},
  {"YOUR_WIFI_SSID_2", "YOUR_WIFI_PASSWORD_2"},
  {"YOUR_WIFI_SSID_3", "YOUR_WIFI_PASSWORD_3"},
  {"YOUR_WIFI_SSID_4", "YOUR_WIFI_PASSWORD_4"}
};

const int WIFI_NETWORK_COUNT =
  sizeof(wifiNetworks) / sizeof(wifiNetworks[0]);

// XAMPP PC IPv4 address (connected to Router 1)
const char* API_BASE =
  "http://YOUR_SERVER_HOST/esp32_timer/api.php"; // Use your server IP/domain and correct path

const char* API_KEY = "REPLACE_WITH_YOUR_API_KEY"; // Must match config.php and index.php

// ==========================================
// GPIO PINS
// ==========================================
const int GREEN_LED = 25;
const int RED_LED = 26;
const int WIFI_STATUS_LED = 13;  // ON when WiFi is connected, OFF when disconnected

const int START_BUTTON = 12;
const int STOP_BUTTON = 14;
const int RED_BUTTON = 27;

// INPUT_PULLUP: each button between GPIO and GND.
// Use suitable current-limiting resistors with LEDs.

// ==========================================
// TIMING
// ==========================================
const unsigned long RED_PULSE_MS = 500;
const unsigned long DEBOUNCE_MS = 35;
const unsigned long STATUS_POLL_MS = 1000;
const unsigned long WIFI_RETRY_MS = 5000;
const unsigned long WIFI_SCAN_MS = 20000;
const int WIFI_CONNECT_TIMEOUT_MS = 12000;
const int WIFI_ROAM_MARGIN_DBM = 10;
const unsigned long NTP_RETRY_MS = 30000;

// ==========================================
// TIMER STATE
// ==========================================
bool timerRunning = false;
bool redLedActive = false;
bool redEventPending = false;

unsigned long redStartedAt = 0;
unsigned long lastStatusPoll = 0;
unsigned long lastWiFiAttempt = 0;
unsigned long lastWiFiScan = 0;
unsigned long lastNtpAttempt = 0;

int64_t redEventEpochMs = 0;
int64_t redEventSessionId = 0;
int64_t activeSessionId = 0;

// ==========================================
// BUTTON DEBOUNCE
// ==========================================
struct ButtonState {
  int pin;
  bool stableLevel;
  bool lastRawLevel;
  unsigned long lastChange;
};

ButtonState startButton = {START_BUTTON, HIGH, HIGH, 0};
ButtonState stopButton  = {STOP_BUTTON, HIGH, HIGH, 0};
ButtonState redButton   = {RED_BUTTON, HIGH, HIGH, 0};

bool clockSynced = false;

// Forward declarations
void triggerRedLed();
void updateWiFiStatusLed();

// ==========================================
// EPOCH TIME IN MILLISECONDS
// ==========================================
int64_t getEpochMs() {
  struct timeval tv;
  gettimeofday(&tv, nullptr);

  return (int64_t)tv.tv_sec * 1000LL
       + tv.tv_usec / 1000;
}

bool isClockValid() {
  return getEpochMs() >= 1700000000000LL;
}

// ==========================================
// URL
// ==========================================
String makeUrl(const char* action) {
  return String(API_BASE)
       + "?action=" + action
       + "&api_key=" + API_KEY;
}

// ==========================================
// HTTP POST JSON
// ==========================================
bool postAction(
  const char* action,
  const String& extraJson = ""
) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("POST failed: WiFi disconnected");
    return false;
  }

  HTTPClient http;
  http.setConnectTimeout(1500);
  http.setTimeout(2500);

  if (!http.begin(API_BASE)) {
    Serial.println("HTTP begin failed");
    return false;
  }

  http.addHeader("Content-Type", "application/json");

  String body = "{\"action\":\"";
  body += action;
  body += "\",\"api_key\":\"";
  body += API_KEY;
  body += "\"";

  if (extraJson.length() > 0) {
    body += ",";
    body += extraJson;
  }

  body += "}";

  int code = http.POST(body);
  String response = http.getString();
  http.end();

  Serial.printf("POST %s => HTTP %d\n", action, code);
  Serial.println(response);

  if (code < 200 || code >= 300) {
    return false;
  }

  DynamicJsonDocument doc(2048);

  if (deserializeJson(doc, response)) {
    Serial.println("Invalid JSON response");
    return false;
  }

  return doc["ok"] | false;
}

// ==========================================
// GET SERVER STATUS
// ==========================================
void pollServerStatus() {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }

  HTTPClient http;
  http.setConnectTimeout(1500);
  http.setTimeout(2500);

  if (!http.begin(makeUrl("status"))) {
    Serial.println("Status HTTP begin failed");
    return;
  }

  int code = http.GET();
  String response = http.getString();
  http.end();

  if (code != HTTP_CODE_OK) {
    Serial.printf("Status HTTP error: %d\n", code);
    return;
  }

  DynamicJsonDocument doc(2048);

  if (deserializeJson(doc, response)) {
    Serial.println("Invalid status JSON");
    return;
  }

  if (!(doc["ok"] | false)) {
    Serial.println("Server status returned error");
    return;
  }

  timerRunning = doc["running"] | false;

  if (doc["session_id"].isNull()) {
    activeSessionId = 0;
  } else {
    activeSessionId = doc["session_id"].as<int64_t>();
  }

  digitalWrite(GREEN_LED, timerRunning ? HIGH : LOW);

  if (!timerRunning && redLedActive) {
    digitalWrite(RED_LED, LOW);
    redLedActive = false;
    // Do not discard a completed event awaiting upload.
  }

  Serial.printf(
    "Timer: %s | Session ID: %lld\n",
    timerRunning ? "RUNNING" : "STOPPED",
    (long long)activeSessionId
  );
}

// ==========================================
// POLL COMMANDS FROM DASHBOARD
// ==========================================
void pollDashboardCommand() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.setConnectTimeout(1500);
  http.setTimeout(2500);

  if (!http.begin(makeUrl("next_command"))) {
    Serial.println("Command HTTP begin failed");
    return;
  }

  int code = http.GET();
  String response = http.getString();
  http.end();

  if (code != HTTP_CODE_OK) {
    Serial.printf("Command poll HTTP error: %d\n", code);
    return;
  }

  DynamicJsonDocument doc(1024);
  if (deserializeJson(doc, response) || !(doc["ok"] | false)) {
    Serial.println("Invalid command response");
    return;
  }

  if (doc["command"].isNull()) return;

  const char* commandName = doc["command"]["name"] | "";
  const long long commandId = doc["command"]["id"] | 0LL;

  Serial.printf(
    "Dashboard command #%lld: %s\n",
    commandId, commandName
  );

  if (String(commandName) == "red") {
    triggerRedLed();
  }
}

// ==========================================
// WIFI STATUS LED
// ==========================================
void updateWiFiStatusLed() {
  digitalWrite(WIFI_STATUS_LED, WiFi.status() == WL_CONNECTED ? HIGH : LOW);
}

// ==========================================
// WIFI HELPERS
// ==========================================
int findCredentialIndex(const String& foundSSID) {
  for (int i = 0; i < WIFI_NETWORK_COUNT; i++) {
    if (foundSSID == wifiNetworks[i].ssid) {
      return i;
    }
  }
  return -1;
}

// Scans known networks and selects the one with the strongest RSSI.
// If already connected, switch only when another known network is
// stronger by WIFI_ROAM_MARGIN_DBM to reduce unnecessary switching.
void scanAndConnectWiFi(bool allowRoaming) {
  Serial.println("\nScanning for saved WiFi networks...");

  int currentRSSI = -1000;
  String currentSSID = "";

  if (WiFi.status() == WL_CONNECTED) {
    currentRSSI = WiFi.RSSI();
    currentSSID = WiFi.SSID();
  }

  int networksFound = WiFi.scanNetworks();

  if (networksFound < 0) {
    Serial.println("WiFi scan failed");
    WiFi.scanDelete();
    return;
  }

  int bestIndex = -1;
  int bestRSSI = -1000;

  for (int i = 0; i < networksFound; i++) {
    String foundSSID = WiFi.SSID(i);
    int credentialIndex = findCredentialIndex(foundSSID);

    if (credentialIndex >= 0) {
      int rssi = WiFi.RSSI(i);

      Serial.printf(
        "Found saved network: %s | RSSI: %d dBm\n",
        foundSSID.c_str(), rssi
      );

      if (rssi > bestRSSI) {
        bestRSSI = rssi;
        bestIndex = credentialIndex;
      }
    }
  }

  WiFi.scanDelete();

  if (bestIndex < 0) {
    Serial.println("None of the saved WiFi networks found.");
    return;
  }

  const char* bestSSID = wifiNetworks[bestIndex].ssid;

  if (WiFi.status() == WL_CONNECTED) {
    if (currentSSID == bestSSID) {
      Serial.printf(
        "Already connected to strongest detected network: %s (%d dBm)\n",
        bestSSID, currentRSSI
      );
      return;
    }

    if (!allowRoaming) {
      Serial.printf("Currently connected to %s\n", currentSSID.c_str());
      return;
    }

    if (bestRSSI < currentRSSI + WIFI_ROAM_MARGIN_DBM) {
      Serial.printf(
        "Staying on %s (%d dBm); candidate %s (%d dBm) is not sufficiently stronger.\n",
        currentSSID.c_str(), currentRSSI, bestSSID, bestRSSI
      );
      return;
    }

    Serial.printf(
      "Switching WiFi: %s (%d dBm) -> %s (%d dBm)\n",
      currentSSID.c_str(), currentRSSI, bestSSID, bestRSSI
    );
  } else {
    Serial.printf(
      "Best available network: %s (%d dBm)\n",
      bestSSID, bestRSSI
    );
  }

  WiFi.disconnect();
  delay(200);

  Serial.printf("Connecting to %s", bestSSID);
  WiFi.begin(
    wifiNetworks[bestIndex].ssid,
    wifiNetworks[bestIndex].password
  );

  unsigned long started = millis();

  while (
    WiFi.status() != WL_CONNECTED &&
    millis() - started < WIFI_CONNECT_TIMEOUT_MS
  ) {
    delay(250);
    updateWiFiStatusLed();
    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected successfully.");
    Serial.print("Connected SSID: ");
    Serial.println(WiFi.SSID());
    Serial.print("ESP32 IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("Gateway: ");
    Serial.println(WiFi.gatewayIP());
    Serial.print("Signal strength: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
    Serial.print("API: ");
    Serial.println(API_BASE);
  } else {
    Serial.printf("Could not connect to %s; will retry.\n", bestSSID);
  }
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(false);
  WiFi.setSleep(false);

  scanAndConnectWiFi(false);
}

// ==========================================
// NTP CLOCK
// ==========================================
void syncClock() {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }

  configTime(
    5 * 3600,
    0,
    "pool.ntp.org",
    "time.google.com"
  );

  struct tm timeInfo;

  if (getLocalTime(&timeInfo, 1500)) {
    clockSynced = true;
    Serial.println("NTP clock synchronized (PKT)");
  } else {
    clockSynced = isClockValid();
    Serial.println("NTP clock not ready; will retry");
  }

  lastNtpAttempt = millis();
}

// ==========================================
// DEBOUNCED BUTTON PRESS
// ==========================================
bool wasPressed(ButtonState &b) {
  bool raw = digitalRead(b.pin);

  if (raw != b.lastRawLevel) {
    b.lastRawLevel = raw;
    b.lastChange = millis();
  }

  if (
    millis() - b.lastChange >= DEBOUNCE_MS &&
    raw != b.stableLevel
  ) {
    b.stableLevel = raw;

    // INPUT_PULLUP: LOW = pressed
    if (b.stableLevel == LOW) {
      return true;
    }
  }

  return false;
}

// ==========================================
// START TIMER
// ==========================================
void startTimer() {
  if (timerRunning) {
    Serial.println("Timer already running");
    return;
  }

  Serial.println("START requested");

  if (postAction("start")) {
    pollServerStatus();
  } else {
    Serial.println("START failed; check API/WiFi");
  }
}

// ==========================================
// STOP TIMER
// ==========================================
void stopTimer() {
  if (!timerRunning) {
    Serial.println("Timer already stopped");
    return;
  }

  Serial.println("STOP requested");

  timerRunning = false;
  digitalWrite(GREEN_LED, LOW);

  if (redLedActive) {
    digitalWrite(RED_LED, LOW);
    redLedActive = false;
  }

  if (postAction("stop")) {
    pollServerStatus();
  } else {
    Serial.println("STOP failed; server may still be running");
  }
}

// ==========================================
// RED LED BUTTON
// ==========================================
void triggerRedLed() {
  if (!timerRunning) {
    Serial.println("Red press ignored: timer stopped");
    return;
  }

  if (redLedActive) {
    Serial.println("Red press ignored: LED pulse in progress");
    return;
  }

  if (redEventPending) {
    Serial.println("Red press ignored: previous event awaiting upload");
    return;
  }

  if (activeSessionId == 0) {
    Serial.println("Red press ignored: no active session ID");
    return;
  }

  if (!isClockValid()) {
    Serial.println(
      "Red press ignored: clock is not valid. "
      "Internet/NTP or another time source may be required after reboot."
    );
    return;
  }

  redEventEpochMs = getEpochMs();
  redEventSessionId = activeSessionId;

  redStartedAt = millis();
  redLedActive = true;

  digitalWrite(RED_LED, HIGH);

  Serial.printf(
    "RED ON | Session %lld | Epoch ms %lld\n",
    (long long)redEventSessionId,
    (long long)redEventEpochMs
  );
}

// ==========================================
// RED LED PULSE + DATABASE UPLOAD
// ==========================================
void serviceRedLed() {
  // Keep the 500 ms pulse non-blocking.
  if (
    redLedActive &&
    millis() - redStartedAt >= RED_PULSE_MS
  ) {
    digitalWrite(RED_LED, LOW);
    redLedActive = false;
    redEventPending = true;

    Serial.println("RED OFF after 500 ms");
  }

  // Upload only after the physical LED turns off.
  if (redEventPending && !redLedActive) {
    String extra =
      "\"epoch_ms\":" +
      String((long long)redEventEpochMs) +
      ",\"session_id\":" +
      String((long long)redEventSessionId);

    if (postAction("red_event", extra)) {
      Serial.println("Red LED timestamp saved");

      redEventPending = false;
      redEventEpochMs = 0;
      redEventSessionId = 0;
    } else {
      // Keep this event and retry on the next loop.
      Serial.println("Event upload failed; retrying");
    }
  }
}

// ==========================================
// SETUP
// ==========================================
void setup() {
  Serial.begin(115200);

  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  pinMode(WIFI_STATUS_LED, OUTPUT);

  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  digitalWrite(WIFI_STATUS_LED, LOW);

  pinMode(START_BUTTON, INPUT_PULLUP);
  pinMode(STOP_BUTTON, INPUT_PULLUP);
  pinMode(RED_BUTTON, INPUT_PULLUP);

  connectWiFi();

  if (WiFi.status() == WL_CONNECTED) {
    syncClock();
    pollServerStatus();
  }

  lastStatusPoll = millis();
  lastWiFiScan = millis();
  lastWiFiAttempt = millis();
}

// ==========================================
// MAIN LOOP
// ==========================================
void loop() {
  unsigned long now = millis();

  // WiFi indicator: ON while connected to any saved WiFi network.
  updateWiFiStatusLed();

  // Reconnect if WiFi is disconnected.
  if (
    WiFi.status() != WL_CONNECTED &&
    now - lastWiFiAttempt >= WIFI_RETRY_MS
  ) {
    lastWiFiAttempt = now;
    Serial.println("WiFi disconnected; scanning saved networks...");
    scanAndConnectWiFi(false);

    if (WiFi.status() == WL_CONNECTED) {
      syncClock();
      pollServerStatus();
    }
  }

  // Periodically check whether another known router is significantly
  // stronger. Different SSIDs do not automatically roam like one mesh.
  if (
    WiFi.status() == WL_CONNECTED &&
    now - lastWiFiScan >= WIFI_SCAN_MS
  ) {
    lastWiFiScan = now;
    scanAndConnectWiFi(true);
  }

  // Retry NTP when needed. NTP generally needs internet access.
  if (
    WiFi.status() == WL_CONNECTED &&
    (!clockSynced || !isClockValid()) &&
    now - lastNtpAttempt >= NTP_RETRY_MS
  ) {
    syncClock();
  }

  // Hardware buttons
  if (wasPressed(startButton)) {
    startTimer();
  }

  if (wasPressed(stopButton)) {
    stopTimer();
  }

  if (wasPressed(redButton)) {
    triggerRedLed();
  }

  // Service LED before polling server
  serviceRedLed();

  // Dashboard and hardware state synchronization
  if (
    WiFi.status() == WL_CONNECTED &&
    now - lastStatusPoll >= STATUS_POLL_MS
  ) {
    lastStatusPoll = now;
    pollServerStatus();

    if (
      timerRunning &&
      !redLedActive &&
      !redEventPending &&
      isClockValid()
    ) {
      pollDashboardCommand();
    }
  }

  delay(1);
}
