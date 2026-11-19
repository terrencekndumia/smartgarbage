/*
  Smart Bin - ESP32
  - IR/PIR sensor detects a person approaching  -> servo opens the lid
  - Lid closes a few seconds after the person leaves
  - Ultrasonic sensor (HC-SR04) measures the fill level (only while the lid is closed)
  - Level is sent to the web system (api/reading.php) over WiFi
*/
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ESP32Servo.h>

// ---------- SETTINGS: change these ----------
const char* WIFI_SSID  = "Wokwi-GUEST";   // your WiFi name ("Wokwi-GUEST" in the simulator)
const char* WIFI_PASS  = "";              // your WiFi password
// Copy this from the web app: log in as admin -> IoT Device page (shows the exact address)
const char* SERVER_URL = "http://192.168.1.10/smart-garbage/api/reading.php";  // or your https://xxxx.ngrok-free.app/smart-garbage/api/reading.php
const char* DEVICE_KEY = "bin-device-secret";   // must match DEVICE_KEY in config.php
const int   BIN_ID     = 1;                     // the Bin ID shown on the admin Bins / IoT Device page

// ---------- PINS ----------
const int IR_PIN = 27, TRIG_PIN = 5, ECHO_PIN = 18, SERVO_PIN = 13;
const bool IR_ACTIVE_HIGH = true;   // PIR / Wokwi = true. FC-51 IR module = false (LOW when it sees something)

// ---------- BIN SIZE ----------
const float EMPTY_CM = 40;   // sensor-to-bottom distance when the bin is empty
const float FULL_CM  = 5;    // sensor-to-rubbish distance when the bin is full

// ---------- TIMING ----------
const int LID_CLOSED = 0, LID_OPEN = 90;      // servo angles
const unsigned long LID_HOLD_MS = 4000;       // keep lid open this long after last detection
const unsigned long REPORT_MS   = 10000;      // send at least every 10 s
const int REPORT_DELTA = 5;                   // or when the level changes by 5 %

Servo lid;
bool lidOpen = false;
unsigned long lastSeen = 0, measureAt = 0, lastReport = 0;
int lastSent = -1;

float readDistanceCm() {
  digitalWrite(TRIG_PIN, LOW);  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long us = pulseIn(ECHO_PIN, HIGH, 30000);
  if (us == 0) return -1;
  return us * 0.0343 / 2.0;
}

// Median of 5 readings -> fill level 0-100 %, or -1 if the sensor failed
int readLevel() {
  float d[5]; int n = 0;
  for (int i = 0; i < 5; i++) { float x = readDistanceCm(); if (x > 0) d[n++] = x; delay(30); }
  if (n == 0) return -1;
  for (int i = 0; i < n - 1; i++)
    for (int j = i + 1; j < n; j++)
      if (d[j] < d[i]) { float t = d[i]; d[i] = d[j]; d[j] = t; }
  float pct = (EMPTY_CM - d[n / 2]) / (EMPTY_CM - FULL_CM) * 100.0;
  return (int)constrain(pct, 0, 100);
}

void sendLevel(int level) {
  if (WiFi.status() != WL_CONNECTED) { Serial.println("No WiFi - level not sent"); return; }
  HTTPClient http;
  WiFiClientSecure secure; WiFiClient plain;
  String url = SERVER_URL;
  if (url.startsWith("https")) { secure.setInsecure(); http.begin(secure, url); }  // e.g. ngrok
  else http.begin(plain, url);                                                    // e.g. http://192.168.x.x
  http.addHeader("ngrok-skip-browser-warning", "true");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Key", DEVICE_KEY);
  int code = http.POST("{\"binId\":" + String(BIN_ID) + ",\"level\":" + String(level) + "}");
  Serial.printf("Sent level %d%% -> HTTP %d\n", level, code);
  http.end();
}

void setup() {
  Serial.begin(115200);
  pinMode(IR_PIN, INPUT);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  lid.setPeriodHertz(50);
  lid.attach(SERVO_PIN, 500, 2400);
  lid.write(LID_CLOSED);

  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long t = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t < 8000) { delay(250); Serial.print("."); }
  Serial.println(WiFi.status() == WL_CONNECTED ? "\nWiFi connected" : "\nWiFi not connected (running offline)");
}

void loop() {
  unsigned long now = millis();
  bool detected = (digitalRead(IR_PIN) == HIGH) == IR_ACTIVE_HIGH;

  if (detected) {
    lastSeen = now;
    if (!lidOpen) { lid.write(LID_OPEN); lidOpen = true; Serial.println("Person detected -> lid OPEN"); }
  } else if (lidOpen && now - lastSeen > LID_HOLD_MS) {
    lid.write(LID_CLOSED); lidOpen = false;
    measureAt = now + 1500;                 // let the lid settle, then measure
    Serial.println("Person gone -> lid CLOSED");
  }

  // Measure the level only while the lid is closed
  if (!lidOpen && now >= measureAt) {
    int level = readLevel();
    if (level >= 0) {
      Serial.printf("Fill level: %d%%\n", level);
      if (lastSent < 0 || abs(level - lastSent) >= REPORT_DELTA || now - lastReport >= REPORT_MS) {
        sendLevel(level); lastSent = level; lastReport = now;
      }
    } else Serial.println("Level sensor error");
    measureAt = now + 2000;
  }
  delay(50);
}
