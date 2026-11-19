BIN WATCH - WEB-BASED IOT SMART GARBAGE COLLECTION SYSTEM (PHP + MySQL + ESP32)

1. Extract into C:\xampp\htdocs  ->  C:\xampp\htdocs\smart-garbage
2. XAMPP Control Panel: start Apache and MySQL.
3. phpMyAdmin (http://localhost/phpmyadmin) -> Import -> database.sql -> Go
   (this RESETS the smart_garbage database and adds demo accounts)
4. Open http://localhost/smart-garbage/

Demo logins
  Admin      admin@estate.com / admin123
  Collector  kamau@estate.com / collector123
  Resident   jane@estate.com  / resident123   (owns BIN-001, the bin the ESP32 reports for)

IoT: the ESP32 (esp32/smart_bin.ino, Wokwi) POSTs {"binId":1,"level":N} to api/reading.php
with header X-Device-Key. Keys and the 80% threshold are in config.php.
MySQL port is 3307 (config.php). Change DB_PORT if yours differs.

UPDATE: already imported an older database.sql and want to keep your data?
Run upgrade.sql instead of re-importing database.sql (it only adds the bin_reports table).

CONNECTING THE ESP32 (Wokwi): log in as admin -> "IoT Device" page. It shows the address to paste into SERVER_URL,
the device key, each bin's BIN_ID, whether the sensor is online, and the readings received live.
Wokwi runs on the internet, so run "ngrok http 80" and open the site through the ngrok address first.
