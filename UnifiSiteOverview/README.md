# UnifiSiteOverview
Zeigt Latenz, Paketverlust, Verfügbarkeit und Kennzahlen einer UniFi-Site über die [Site Manager API v1.0](https://developer.ui.com/site-manager/v1.0.0/gettingstarted) in Symcon an.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
4. [Statusvariablen](#4-statusvariablen)
5. [Visualisierung](#5-visualisierung)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* ISP-Metriken im 5-Minuten-Raster: Latenz (Durchschnitt und Maximum), Paketverlust, Uptime, Ausfallzeit
* Status je WAN: Provider, externe IP, Uptime, Port verbunden/getrennt
* Kennzahlen der Site: Geräte, Geräte offline, ausstehende Updates, Clients, Gäste, WLAN-Wiederholungen, Internetprobleme
* Kachel mit dem Latenzverlauf der letzten 24 Stunden (HTML-SDK)
* Metriken der letzten Stunde werden nachträglich ins Archiv geschrieben, wenn die Variablen geloggt werden

### 2. Voraussetzungen

- IP-Symcon ab Version 7.1
- API-Key des UI-Kontos

### 3. Einrichten der Instanzen in IP-Symcon

__Konfigurationsseite__:

Name          | Beschreibung
------------- | ------------------
Timer         | Abfrageintervall in Sekunden, 0 = aus. Die Metriken liegen im 5-Minuten-Raster vor, 300 ist daher sinnvoll.
APIKey        | Erzeugen unter unifi.ui.com -> Settings -> API Keys
Host ID       | Konsole, zur Auswahl zuerst "Hosts Holen"
Site ID       | Site der Konsole, zur Auswahl zuerst "Sites Holen"
WAN Schnittstellen | WANs, für die Variablen angelegt werden. Beim Abwählen werden die Variablen samt Archivdaten gelöscht.

### 4. Statusvariablen

Die Statusvariablen werden automatisch angelegt.

Name | Typ | Beschreibung
---- | --- | ------------
Average / Max Latency 5m | Integer | Latenz des genutzten WAN in ms
Packetloss 5m, Uptime 5m | Float | in %
Ausfallzeit 5m | Integer | in Sekunden
Aktives WAN, Active ISP | String | aktuell genutzte Leitung
ISP-Geschwindigkeit Download/Upload | Float | von der API gemeldete Geschwindigkeit in Mbit/s. Das ist kein aktueller Datenverkehr.
Geräte offline, Ausstehende Updates, Kritische Meldungen, Internetprobleme, Gäste | Integer | Anzahl
WLAN TX-Wiederholungen | Float | in %
\<WAN\> ISP, IP, Uptime, Port | | Status je WAN
\<WAN\> Avg/Max Latency, Downtime, Packetloss | | Metriken je WAN

Die API liefert die Metriken nur für das gerade genutzte WAN und ohne dessen Namen. Das Modul ordnet sie über die ASN des Providers zu. Ein WAN im Standby behält daher seine letzten Werte. Nutzen mehrere WANs denselben Provider, zählt das WAN mit der höchsten Failover-Priorität.

### 5. Visualisierung

Die Kachel zeigt die aktuellen Werte, den Latenzverlauf (1, 6 oder 24 Stunden), die WANs und die Kennzahlen der Site. Sie passt sich der Kachelgröße an. Beim Zeigen oder Tippen auf den Verlauf erscheinen die Werte des jeweiligen Zeitraums. Striche auf der Grundlinie markieren Paketverlust (gelb) sowie Ausfälle und Verlust ab 5 % (rot).

### 6. PHP-Befehlsreferenz

`void UISPM_timerRun(integer $InstanzID);`
Liest Metriken und Site-Daten und aktualisiert Variablen und Kachel.

`void UISPM_getMetrics(integer $InstanzID);`
Liest nur die Metriken.

`void UISPM_getSiteData(integer $InstanzID);`
Liest nur die Site-Daten.
