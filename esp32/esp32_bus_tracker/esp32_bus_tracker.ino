/********************************************************************
    SMART IoT BUS TRACKING SYSTEM (PRODUCTION VERSION)

    ESP32-WROOM-32 + SIM900 + GPS + IR SEATS

    ARCHITECTURE:
    v WiFi (ESP32 built-in) → HTTPS → Render API (GPS + seat data)
    v SIM900 → GPS module (serial NMEA) + SMS delivery
********************************************************************/

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <TinyGPS++.h>
#include <HardwareSerial.h>
#include <ArduinoJson.h>

/********************************************************************
                    WIFI CREDENTIALS — SET THESE
********************************************************************/

const char* WIFI_SSID = "YourWiFiName";
const char* WIFI_PASS = "YourWiFiPassword";

/********************************************************************
                    HARDWARE CONFIG
********************************************************************/

HardwareSerial gpsSerial(1);
HardwareSerial sim900(2);
TinyGPSPlus gps;

/********************************************************************
                    PIN CONFIGURATION
********************************************************************/

#define GPS_RX 16
#define GPS_TX 17

#define SIM_RX 26
#define SIM_TX 27

#define SEAT1 32
#define SEAT2 33
#define SEAT3 34
#define SEAT4 35

/********************************************************************
                    SYSTEM CONFIG
********************************************************************/

String BUS_ID = "BUS001";

/********************************************************************
                    SERVER ENDPOINTS
********************************************************************/

const char* SERVER = "https://bus-tracking-tbyl.onrender.com";

String URL_UPDATE  = String(SERVER) + "/api/update.php";
String URL_SMS     = String(SERVER) + "/api/get_pending_sms.php";
String URL_CONFIRM = String(SERVER) + "/api/confirm_sms.php";

/********************************************************************
                    GLOBAL VARIABLES
********************************************************************/

String lat = "0.0";
String lng = "0.0";

int seat1, seat2, seat3, seat4;

bool wifiConnected = false;

/********************************************************************
                    SETUP
********************************************************************/

void setup()
{
    Serial.begin(115200);

    gpsSerial.begin(9600, SERIAL_8N1, GPS_RX, GPS_TX);
    sim900.begin(9600, SERIAL_8N1, SIM_RX, SIM_TX);

    pinMode(SEAT1, INPUT);
    pinMode(SEAT2, INPUT);
    pinMode(SEAT3, INPUT);
    pinMode(SEAT4, INPUT);

    delay(3000);

    initWiFi();
    initSIM900();

    Serial.println("SYSTEM READY");
    Serial.println("[GPS] Waiting for satellite fix (may take 5-15 min on cold start)...");
}

/********************************************************************
                    MAIN LOOP
********************************************************************/

void loop()
{
    readGPS();
    readSeats();

    if (WiFi.status() != WL_CONNECTED) {
        reconnectWiFi();
    }

    if (wifiConnected) {
        sendBusData();
        checkPendingSMS();
    }

    delay(8000);
}

/********************************************************************
                    GPS HANDLER
********************************************************************/

unsigned long lastGpsDebug = 0;

void readGPS()
{
    while (gpsSerial.available()) {
        gps.encode(gpsSerial.read());
    }

    if (gps.location.isValid()) {
        lat = String(gps.location.lat(), 6);
        lng = String(gps.location.lng(), 6);
    } else {
        // Print GPS status every 10s when no fix
        if (millis() - lastGpsDebug > 10000) {
            lastGpsDebug = millis();
            Serial.print("[GPS] No fix. Satellites: ");
            Serial.print(gps.satellites.value());
            Serial.print(" | Chars processed: ");
            Serial.print(gps.charsProcessed());
            Serial.print(" | Sentenced: ");
            Serial.print(gps.sentencesWithFix());
            Serial.print(" | HDOP: ");
            Serial.println(gps.hdop.value());
        }
    }

    // Cold-start hint after 30s of no data
    if (gps.charsProcessed() < 10 && millis() > 35000) {
        Serial.println("[GPS] No NMEA data received. Check wiring: GPS TX → ESP32 GPIO16 (RX)");
        delay(5000); // prevent spam
    }
}

/********************************************************************
                    SEAT READING
********************************************************************/

void readSeats()
{
    seat1 = digitalRead(SEAT1);
    seat2 = digitalRead(SEAT2);
    seat3 = digitalRead(SEAT3);
    seat4 = digitalRead(SEAT4);
}

/********************************************************************
                    SIM900 INIT
********************************************************************/

void initSIM900()
{
    Serial.println("[SIM900] Initializing for GPS + SMS...");

    sendAT("AT", 2000);
    sendAT("ATE0", 1000);

    // Check SIM ready
    if (!checkSIM()) return;

    // Check network registration (required for SMS)
    if (!waitForNetwork()) return;

    // Signal quality
    checkSignal();

    Serial.println("[SIM900] Ready (GPS + SMS only)");
}

bool checkSIM()
{
    String r = sendAT("AT+CPIN?", 3000);
    if (r.indexOf("READY") >= 0) {
        Serial.println("[SIM] SIM ready");
        return true;
    }
    Serial.println("[SIM] SIM not ready - check PIN");
    return false;
}

bool waitForNetwork()
{
    Serial.println("[NET] Waiting for network registration...");
    for (int i = 0; i < 30; i++) {
        String r = sendAT("AT+CREG?", 2000);
        if (r.indexOf("+CREG: 0,1") >= 0 || r.indexOf("+CREG: 0,5") >= 0) {
            Serial.println("[NET] Registered");
            return true;
        }
        delay(1000);
    }
    Serial.println("[NET] Registration failed");
    return false;
}

void checkSignal()
{
    String r = sendAT("AT+CSQ", 2000);
    Serial.println("[SIG] " + r);
}

/********************************************************************
                    FLUSH SIM900 RX BUFFER
********************************************************************/

void flushSIM900()
{
    while (sim900.available()) {
        sim900.read();
    }
}

/********************************************************************
                    SEND AT COMMAND
********************************************************************/

String sendAT(String cmd, int timeout)
{
    flushSIM900();

    String response = "";
    sim900.println(cmd);

    long start = millis();
    while ((millis() - start) < timeout) {
        while (sim900.available()) {
            char c = sim900.read();
            response += c;
            if (response.endsWith("\r\nOK\r\n") || response.endsWith("\r\nERROR\r\n")) {
                goto done;
            }
        }
    }
    done:

    // Drain any leftover bytes so next call starts clean
    while (sim900.available()) {
        sim900.read();
    }

    response.trim();
    if (response.length() > 0) {
        Serial.println("[AT] " + response);
    }
    return response;
}

/********************************************************************
                    SEND BUS DATA TO SERVER
********************************************************************/

void sendBusData()
{
    String url =
        URL_UPDATE +
        "?bus_id=" + BUS_ID +
        "&lat=" + lat +
        "&lng=" + lng +
        "&s1=" + String(seat1) +
        "&s2=" + String(seat2) +
        "&s3=" + String(seat3) +
        "&s4=" + String(seat4);

    String response = httpGET(url);
    if (response.indexOf("OK") >= 0) {
        Serial.println("[DATA] Server OK");
    } else if (response.indexOf("ERROR") >= 0) {
        Serial.println("[DATA] Server error: " + response);
    }
}

/********************************************************************
                    HTTPS GET VIA ESP32 WIFI
********************************************************************/

String httpGET(String url)
{
    if (WiFi.status() != WL_CONNECTED) return "";

    WiFiClientSecure client;
    client.setInsecure();   // Skip SSL cert verification (bus data, not sensitive)

    HTTPClient http;
    http.begin(client, url);

    int httpCode = http.GET();
    String response = "";

    if (httpCode > 0) {
        response = http.getString();
        response.trim();
        Serial.println("[HTTPS] " + String(httpCode) + " " + response.substring(0, 100));
    } else {
        Serial.println("[HTTPS] Request failed: " + String(httpCode));
    }

    http.end();
    return response;
}

/********************************************************************
                    CHECK PENDING SMS
********************************************************************/

void checkPendingSMS()
{
    String response = httpGET(URL_SMS + "?bus_id=" + BUS_ID);

    if (response.indexOf("{") == -1) return;

    StaticJsonDocument<512> doc;
    DeserializationError err = deserializeJson(doc, response);

    if (err) return;

    if (doc["status"] != "success") return;

    String phone    = doc["phone"];
    String message  = doc["message"];
    String ticket_id = doc["ticket_id"];

    bool sent = sendSMS(phone, message);

    if (sent) {
        confirmSMS(ticket_id);
    }
}

/********************************************************************
                    SEND SMS
********************************************************************/

bool sendSMS(String phone, String message)
{
    sendAT("AT+CMGF=1", 1000);

    sim900.print("AT+CMGS=\"");
    sim900.print(phone);
    sim900.println("\"");
    delay(1000);

    sim900.print(message);
    delay(500);
    sim900.write(26);
    delay(5000);

    String resp = "";
    while (sim900.available()) {
        resp += char(sim900.read());
    }
    Serial.println("[SMS] " + resp);

    return resp.indexOf("OK") != -1;
}

/********************************************************************
                    CONFIRM SMS DELIVERY
********************************************************************/

void confirmSMS(String ticket_id)
{
    httpGET(URL_CONFIRM + "?ticket_id=" + ticket_id);
}

/********************************************************************
                    WIFI CONNECTION
********************************************************************/

void initWiFi()
{
    Serial.print("[WiFi] Connecting to ");
    Serial.print(WIFI_SSID);

    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASS);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        wifiConnected = true;
        Serial.println();
        Serial.print("[WiFi] Connected. IP: ");
        Serial.println(WiFi.localIP());
    } else {
        wifiConnected = false;
        Serial.println();
        Serial.println("[WiFi] Connection failed!");
    }
}

void reconnectWiFi()
{
    Serial.println("[WiFi] Reconnecting...");
    WiFi.disconnect();
    WiFi.reconnect();

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
        delay(500);
        attempts++;
    }

    wifiConnected = (WiFi.status() == WL_CONNECTED);
    if (wifiConnected) {
        Serial.println("[WiFi] Reconnected");
    }
}
