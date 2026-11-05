<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/UnifiTile.php';

	class UnifiSiteOverview extends IPSModule
	{
		use UnifiTile;

		// Number of seconds of metrics which are written to the archive in one run.
		private const ARCHIVE_WINDOW = 3600;

		// One timer run needs the site list several times; valid for this call only.
		private $sitesCache = null;

		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyString( 'APIKey', '' );
			$this->RegisterPropertyString( 'HostID', 'default' );
			$this->RegisterPropertyString( 'SiteID', 'default' );
			$this->RegisterPropertyString('WANSelection', '[]');
			$this->RegisterPropertyInteger('Timer', 0);
			$this->RegisterTimer('Collect Data', 0, "UISPM_timerRun(\$_IPS['TARGET']);");
			$this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML') ? constant('INSTANCE_VISUALIZATION_TYPE_HTML') : 1);
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			$this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML') ? constant('INSTANCE_VISUALIZATION_TYPE_HTML') : 1);
			$vpos = 100;
			$this->MaintainVariable( 'SiteName', $this->Translate( 'Name' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'LastUpdate', $this->Translate( 'Start Zeitraum' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'ActiveISP', $this->Translate( 'Active ISP' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'globe'], $vpos++, 1 );

			// Keep WAN variables as a contiguous block directly below ActiveISP.
			$vpos = $this->MaintainSelectedWANVariables($vpos);

			$this->MaintainVariable( 'AVGms', $this->Translate( 'Average Latency 5m' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'chart-fft','SUFFIX'=> ' ms'], $vpos++, 1 );
			$this->MaintainVariable( 'maxms', $this->Translate( 'Max Latency 5m' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'chart-fft','SUFFIX'=> ' ms'], $vpos++, 1 );
			$this->MaintainVariable( 'PacketLoss', $this->Translate( 'Packetloss 5m' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'chart-line','INTERVALS_ACTIVE'=> true, 'SUFFIX'=> ' %', 'INTERVALS' => '[{"IntervalMinValue":0,"IntervalMaxValue":1,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":65280},{"IntervalMinValue":1,"IntervalMaxValue":5,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":16776960},{"IntervalMinValue":5,"IntervalMaxValue":100,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":16711680}]'], $vpos++, 1 );
			$this->MaintainVariable( 'Uptime', $this->Translate( 'Uptime 5m' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'gauge','INTERVALS_ACTIVE'=> true, 'SUFFIX'=> ' %', 'INTERVALS' => '[{"IntervalMinValue":0,"IntervalMaxValue":98,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":16711680},{"IntervalMinValue":98,"IntervalMaxValue":100,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":65280}]'], $vpos++, 1 );

			$this->MaintainVariable( 'UnifiDevices', $this->Translate( 'Unifi Devices' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'diagram-project'], $vpos++, 1 );
			$this->MaintainVariable( 'WifiClients', $this->Translate( 'Wifi Clients' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'laptop'], $vpos++, 1 );
			$this->MaintainVariable( 'WiredClients', $this->Translate( 'Wired Clients' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'computer-classic'], $vpos++, 1 );

			$this->MaintainVariable( 'ActiveWAN', $this->Translate( 'Active WAN' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'globe'], $vpos++, true );
			$this->MaintainVariable( 'Downtime', $this->Translate( 'Downtime 5m' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'clock','SUFFIX'=> ' s'], $vpos++, true );
			$this->MaintainVariable( 'ISPDownload', $this->Translate( 'ISP Speed Download' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'arrow-down','SUFFIX'=> ' Mbit/s'], $vpos++, true );
			$this->MaintainVariable( 'ISPUpload', $this->Translate( 'ISP Speed Upload' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'arrow-up','SUFFIX'=> ' Mbit/s'], $vpos++, true );
			$this->MaintainVariable( 'InternetIssues', $this->Translate( 'Internet Issues' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'triangle-exclamation'], $vpos++, true );
			$this->MaintainVariable( 'OfflineDevices', $this->Translate( 'Offline Devices' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'diagram-project'], $vpos++, true );
			$this->MaintainVariable( 'PendingUpdates', $this->Translate( 'Pending Updates' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'arrow-up-from-bracket'], $vpos++, true );
			$this->MaintainVariable( 'CriticalNotifications', $this->Translate( 'Critical Notifications' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'bell'], $vpos++, true );
			$this->MaintainVariable( 'GuestClients', $this->Translate( 'Guest Clients' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'user'], $vpos++, true );
			$this->MaintainVariable( 'TxRetry', $this->Translate( 'WiFi TX Retries' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'wifi','SUFFIX'=> ' %','DIGITS'=> 1], $vpos++, true );

			// Backward compatibility cleanup: remove legacy static WAN variables.
			$this->MaintainVariable('WAN', '', 3, '', 0, false);
			$this->MaintainVariable('WANIP', '', 3, '', 0, false);
			$this->MaintainVariable('WANUptime', '', 2, '', 0, false);
			$this->MaintainVariable('WAN2', '', 3, '', 0, false);
			$this->MaintainVariable('WAN2IP', '', 3, '', 0, false);
			$this->MaintainVariable('WAN2Uptime', '', 2, '', 0, false);

			$this->getHosts();
			$this->getSites();

			$TimerMS = $this->ReadPropertyInteger( 'Timer' ) * 1000;
			$this->SetTimerInterval( 'Collect Data', $TimerMS );
			if ( 0 == $TimerMS )
			{
				// instance inactive
				$this->SetStatus( 104 );
			} else {
				// instance active
				$this->SetStatus( 102 );
				$this->getMetrics();
				$this->getSiteData();
			}

		}

		private function sanitizeIdent(string $value): string
		{
			$ident = preg_replace('/[^A-Za-z0-9_]/', '_', $value);
			if ($ident === null || $ident === '') {
				$ident = 'WAN';
			}
			return $ident;
		}

		private function getSelectedWANRows(): array
		{
			$rows = json_decode($this->ReadPropertyString('WANSelection'), true);
			if (!is_array($rows)) {
				return [];
			}

			$result = [];
			foreach ($rows as $row) {
				if (!is_array($row) || !isset($row['Name'])) {
					continue;
				}
				$result[] = [
					'Name'    => (string) $row['Name'],
					'Enabled' => isset($row['Enabled']) ? (bool) $row['Enabled'] : false
				];
			}

			return $result;
		}

		/**
		 * Names of the WANs to maintain. NULL means the selection is unknown right now (nothing saved
		 * yet and the API gives no answer): the caller must then leave existing variables alone.
		 */
		private function getEnabledWANNames(): ?array
		{
			$rows = $this->getSelectedWANRows();
			if (count($rows) === 0) {
				// The list has never been saved yet: all WANs which are currently available.
				$available = $this->getAvailableWANNamesForSelectedSite();
				return count($available) > 0 ? $available : null;
			}

			$names = [];
			foreach ($rows as $row) {
				if ($row['Enabled']) {
					$names[] = $row['Name'];
				}
			}

			return array_values(array_unique($names));
		}

		private function getAvailableWANNamesForSelectedSite(): array
		{
			return array_values(array_map('strval', array_keys($this->getSiteWans())));
		}

		private function getSiteWans(): array
		{
			$site = $this->getSiteBySelection();
			$wans = $site['statistics']['wans'] ?? [];
			return is_array($wans) ? $wans : [];
		}

		private function getAllSites(): ?array
		{
			if ($this->sitesCache === null) {
				$this->sitesCache = $this->getApiPages('/sites');
			}

			return $this->sitesCache;
		}

		/** The selected site; "default" as site means the first site of the selected host. */
		private function getSiteBySelection(): ?array
		{
			$siteId = $this->ReadPropertyString('SiteID');
			$hostId = $this->ReadPropertyString('HostID');
			if ($siteId === 'default' && $hostId === 'default') {
				return null;
			}

			foreach ($this->getAllSites() ?? [] as $site) {
				if (!isset($site['siteId'])) {
					continue;
				}
				if ($siteId !== 'default' && $site['siteId'] !== $siteId) {
					continue;
				}
				if (isset($site['hostId']) && $hostId !== 'default' && $site['hostId'] !== $hostId) {
					continue;
				}

				return $site;
			}

			return null;
		}

		private function getWANRowsForForm(): array
		{
			$selectedMap = [];
			foreach ($this->getSelectedWANRows() as $row) {
				$selectedMap[$row['Name']] = $row['Enabled'];
			}

			// Saved WANs stay in the list while they are unavailable: saving the form without
			// them would delete their variables.
			$names = array_values(array_unique(array_merge($this->getAvailableWANNamesForSelectedSite(), array_map('strval', array_keys($selectedMap)))));
			natcasesort($names);

			$rows = [];
			foreach ($names as $wanName) {
				$rows[] = [
					'Name'    => $wanName,
					// Nothing saved yet: same default as getEnabledWANNames.
					'Enabled' => $selectedMap[$wanName] ?? (count($selectedMap) === 0)
				];
			}

			return $rows;
		}

		private function MaintainSelectedWANVariables(int $vpos): int
		{
			$enabledWANs = $this->getEnabledWANNames();
			if ($enabledWANs === null) {
				// Without an answer of the API the variables and their archive data must survive.
				$this->SendDebug('UnifiSiteApi', 'WAN selection unknown, WAN variables left unchanged', 0);
				return $vpos;
			}
			natcasesort($enabledWANs);
			$enabledWANs = array_values($enabledWANs);
			$enabledIdents = [];
			$wanBasePos = 200;

			foreach ($enabledWANs as $index => $wanName) {
				$suffix = $this->sanitizeIdent($wanName);
				$identWan = 'WAN_' . $suffix;
				$identIp = 'WANIP_' . $suffix;
				$identUptime = 'WANUptime_' . $suffix;
				$identAvgLatency = 'WANAvgLatency_' . $suffix;
				$identDowntime = 'WANDowntime_' . $suffix;
				$identMaxLatency = 'WANMaxLatency_' . $suffix;
				$identPacketLoss = 'WANPacketLoss_' . $suffix;
				$identPortUp = 'WANPortUp_' . $suffix;
				$blockPos = $wanBasePos + ($index * 10);

				$this->MaintainVariable($identWan, $this->Translate($wanName . ' ISP'), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'globe' ], $blockPos, true);
				$this->MaintainVariable($identIp, $this->Translate($wanName . ' IP'), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'globe' ], $blockPos + 1, true);
				$this->MaintainVariable($identUptime, $this->Translate($wanName . ' Uptime'), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'gauge','INTERVALS_ACTIVE'=> true, 'SUFFIX'=> ' %', 'INTERVALS' => '[{"IntervalMinValue":0,"IntervalMaxValue":98,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":16711680},{"IntervalMinValue":98,"IntervalMaxValue":100,"ConstantActive":false,"ConstantValue":"","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":true,"ColorValue":65280}]' ], $blockPos + 2, true);
				$this->MaintainVariable($identAvgLatency, $this->Translate($wanName . ' Avg Latency 5m'), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'chart-fft', 'SUFFIX'=> ' ms' ], $blockPos + 3, true);
				$this->MaintainVariable($identDowntime, $this->Translate($wanName . ' Downtime 5m'), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'clock', 'SUFFIX'=> ' s' ], $blockPos + 4, true);
				$this->MaintainVariable($identMaxLatency, $this->Translate($wanName . ' Max Latency 5m'), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'chart-fft', 'SUFFIX'=> ' ms' ], $blockPos + 5, true);
				$this->MaintainVariable($identPacketLoss, $this->Translate($wanName . ' Packetloss 5m'), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'chart-line', 'SUFFIX'=> ' %' ], $blockPos + 6, true);

				$this->MaintainVariable($identPortUp, $this->Translate($wanName . ' Port'), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'ethernet', 'OPTIONS'=> json_encode([
					[ 'Caption'=> $this->Translate('Down'), 'ColorActive'=> true, 'ColorValue'=> 16711680, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> false ],
					[ 'Caption'=> $this->Translate('Up'), 'ColorActive'=> true, 'ColorValue'=> 65280, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> true ]
				]) ], $blockPos + 7, true);

				foreach ([$identWan, $identIp, $identUptime, $identAvgLatency, $identDowntime, $identMaxLatency, $identPacketLoss, $identPortUp] as $offset => $ident) {
					$varId = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
					if ($varId !== false) {
						IPS_SetPosition($varId, $blockPos + $offset);
					}
					$enabledIdents[$ident] = true;
				}
			}

			foreach (IPS_GetChildrenIDs($this->InstanceID) as $childId) {
				$object = IPS_GetObject($childId);
				if ($object['ObjectType'] !== 2) {
					continue;
				}

				$ident = $object['ObjectIdent'];
				if (strpos($ident, 'WAN_') === 0 || strpos($ident, 'WANIP_') === 0 || strpos($ident, 'WANUptime_') === 0 || strpos($ident, 'WANAvgLatency_') === 0 || strpos($ident, 'WANDowntime_') === 0 || strpos($ident, 'WANMaxLatency_') === 0 || strpos($ident, 'WANPacketLoss_') === 0 || strpos($ident, 'WANPortUp_') === 0) {
					if (!isset($enabledIdents[$ident])) {
						$this->MaintainVariable($ident, '', 3, '', 0, false);
					}
				}
			}

			return $vpos;
		}

		/**
		 * The API reports the metrics of one period for the WAN in use, without its name. The name
		 * follows from the ASN of the provider; with the same provider on several WANs the one with
		 * the highest failover priority is taken.
		 */
		private function getWanNameForMetrics(array $wanMetrics, array $siteWans): ?string
		{
			if (count($siteWans) === 0) {
				return 'WAN';
			}

			$asn = (string) ($wanMetrics['ispAsn'] ?? '');
			$best = null;
			$bestPriority = PHP_INT_MAX;
			foreach ($siteWans as $name => $wan) {
				if ($asn === '' || (string) ($wan['ispInfo']['asn'] ?? '') !== $asn) {
					continue;
				}
				$priority = (int) ($wan['failoverPriority'] ?? PHP_INT_MAX);
				if ($priority < $bestPriority || $best === null) {
					$best = (string) $name;
					$bestPriority = $priority;
				}
			}

			return $best;
		}

		private function getWanMetricFromPeriodData(array $periodData, string $wanName, array $siteWans): array
		{
			$wanMetrics = $periodData['wan'] ?? null;
			if (!is_array($wanMetrics) || $this->getWanNameForMetrics($wanMetrics, $siteWans) !== $wanName) {
				return [];
			}

			return $wanMetrics;
		}

		private function getArchiveInstanceId(): int
		{
			$archiveInstances = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
			if (!is_array($archiveInstances) || count($archiveInstances) === 0) {
				return 0;
			}

			return (int) $archiveInstances[0];
		}

		private function getMetricTimestamp(string $metricTime): int
		{
			$metricDate = new DateTime($metricTime, new DateTimeZone('UTC'));
			$metricDate->setTimezone((new DateTime())->getTimezone());

			return $metricDate->getTimestamp();
		}

		private function normalizeArchiveValue(int $varId, $value)
		{
			$varInfo = IPS_GetVariable($varId);
			if ($varInfo['VariableType'] === 1) {
				return (int) round((float) $value);
			}

			if ($varInfo['VariableType'] === 2) {
				return (float) $value;
			}

			return $value;
		}

		private function filterMissingArchiveValues(int $archiveId, int $varId, array $values): array
		{
			if (count($values) === 0) {
				return [];
			}

			$timestamps = array_column($values, 'TimeStamp');
			$startTime = (int) min($timestamps);
			$endTime = (int) max($timestamps) + 1;

			$existingValues = @AC_GetLoggedValues($archiveId, $varId, $startTime, $endTime, 0);
			$existingByTimestamp = [];
			if (is_array($existingValues)) {
				foreach ($existingValues as $entry) {
					if (isset($entry['TimeStamp'])) {
						$existingByTimestamp[(int) $entry['TimeStamp']] = true;
					}
				}
			}

			$missingValues = [];
			foreach ($values as $value) {
				if (!isset($existingByTimestamp[(int) $value['TimeStamp']])) {
					$missingValues[] = $value;
				}
			}

			return $missingValues;
		}

		private function archivePeriodMetrics(array $periods, array $metricToIdentMap, ?string $wanName = null, array $siteWans = []): void
		{
			$archiveId = $this->getArchiveInstanceId();
			if ($archiveId === 0) {
				return;
			}

			foreach ($metricToIdentMap as $metricName => $ident) {
				$varId = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
				if ($varId === false) {
					continue;
				}

				$valuesToArchive = [];
				foreach ($periods as $period) {
					if (!isset($period['metricTime']) || !isset($period['data']) || !is_array($period['data'])) {
						continue;
					}

					$periodData = $period['data'];
					$metricData = $wanName === null
						? ((isset($periodData['wan']) && is_array($periodData['wan'])) ? $periodData['wan'] : [])
						: $this->getWanMetricFromPeriodData($periodData, $wanName, $siteWans);

					if (!isset($metricData[$metricName])) {
						continue;
					}

					$valuesToArchive[] = [
						'TimeStamp' => $this->getMetricTimestamp((string) $period['metricTime']),
						'Value'     => $this->normalizeArchiveValue($varId, $metricData[$metricName])
					];
				}

				$valuesToArchive = $this->filterMissingArchiveValues($archiveId, (int) $varId, $valuesToArchive);
				if (count($valuesToArchive) === 0) {
					continue;
				}

				@AC_AddLoggedValues($archiveId, (int) $varId, $valuesToArchive);
			}
		}

		public function timerRun() {
			$this->getMetrics();
			$this->getSiteData();
		}

		private function TileRefresh(): void
		{
			$this->timerRun();
		}

		/** Requests the API; returns NULL and sets the status of the instance on an error. */
		private function apiRequest(string $endpoint, ?string $postData = null): ?array {
			$APIKey = $this->ReadPropertyString( 'APIKey' );
			if ($APIKey == '') {
				$this->SendDebug("UnifiSiteApi", "API Key is empty", 0);
				$this->SetStatus( 201 );
				return null;
			}
			$headers = [ 'X-API-Key: '.$APIKey, 'Accept: application/json' ];
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://api.ui.com/v1'.$endpoint );
			if ($postData !== null) {
				curl_setopt( $ch, CURLOPT_POST, true );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $postData );
				$headers[] = 'Content-Type: application/json';
			}
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
			$RawData = curl_exec( $ch );
			$httpCode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			if ($RawData === false) {
				$this->SendDebug("UnifiSiteApi", "Curl error: " . curl_error($ch), 0);
				curl_close( $ch );
				$this->SetStatus( 201 );
				return null;
			}
			curl_close( $ch );
			$JSONData = json_decode( (string) $RawData, true );
			if ($httpCode !== 200 || !is_array($JSONData)) {
				$this->SendDebug("UnifiSiteApi", "HTTP " . $httpCode . " " . $endpoint . ": " . substr((string) $RawData, 0, 500), 0);
				$this->SetStatus( in_array($httpCode, [400, 401, 403, 404, 429, 500, 502], true) ? $httpCode : 201 );
				return null;
			}
			// A successful answer ends an earlier error (e.g. the rate limit or an outage).
			if ($this->GetStatus() >= 200) {
				$this->SetStatus( $this->ReadPropertyInteger('Timer') > 0 ? 102 : 104 );
			}
			$this->SendDebug("UnifiSiteApi", $endpoint . ": " . $RawData, 0);
			return $JSONData;
		}

		/** All entries of a list endpoint over all pages; NULL if a page could not be read. */
		private function getApiPages(string $endpoint): ?array {
			$entries = [];
			$next = '';
			do {
				$data = $this->apiRequest($endpoint . ($next === '' ? '' : '?nextToken=' . rawurlencode($next)));
				if ($data === null || !isset($data['data']) || !is_array($data['data'])) {
					return null;
				}
				$entries = array_merge($entries, $data['data']);
				$next = (string) ($data['nextToken'] ?? '');
			} while ($next !== '');

			return $entries;
		}

		public function getApiData( string $endpoint = '' ):array {
			return $this->apiRequest($endpoint) ?? [];
		}

		public function getApiDataPost( string $endpoint = '', string $PostData = '' ):array {
			return $this->apiRequest($endpoint, $PostData) ?? [];
		}

		public function getMetrics() {
			$site = $this->getSiteBySelection();
			if ($site === null) {
				$this->TileUpdate(['metricsError' => true]);
				return;
			}

			$begin = new DateTime('now', new DateTimeZone('UTC'));
			$begin->modify('-24 hours');
			$data = $this->getApiDataPost('/isp-metrics/5m/query', (string) json_encode(['sites' => [[
				'beginTimestamp' => $begin->format('Y-m-d\TH:i:s\Z'),
				'hostId'         => (string) $site['hostId'],
				'siteId'         => (string) $site['siteId']
			]]]));
			if (!isset($data['data']['metrics']) || !is_array($data['data']['metrics'])) {
				$this->TileUpdate(['metricsError' => true]);
				return;
			}

			$periods = [];
			foreach ($data['data']['metrics'] as $entry) {
				if (($entry['siteId'] ?? '') === $site['siteId'] && isset($entry['periods']) && is_array($entry['periods'])) {
					$periods = $entry['periods'];
				}
			}
			if (count($periods) === 0) {
				// The console is not sending any metrics right now, which is no error of the API.
				$this->TileUpdate(['metricsError' => false, 'history' => []]);
				return;
			}

			$siteWans = $this->getSiteWans();
			$enabledWANs = $this->getEnabledWANNames() ?? [];

			// The archive only gets the last hour, the 24 hours are for the chart of the tile.
			$archiveFrom = time() - self::ARCHIVE_WINDOW;
			$recent = array_values(array_filter($periods, function ($period) use ($archiveFrom) {
				return isset($period['metricTime']) && $this->getMetricTimestamp((string) $period['metricTime']) >= $archiveFrom;
			}));
			$this->archivePeriodMetrics($recent, [
				'avgLatency' => 'AVGms',
				'maxLatency' => 'maxms',
				'packetLoss' => 'PacketLoss',
				'uptime' => 'Uptime'
			]);
			foreach ($enabledWANs as $wanName) {
				$suffix = $this->sanitizeIdent($wanName);
				$this->archivePeriodMetrics($recent, [
					'avgLatency' => 'WANAvgLatency_' . $suffix,
					'downtime' => 'WANDowntime_' . $suffix,
					'maxLatency' => 'WANMaxLatency_' . $suffix,
					'packetLoss' => 'WANPacketLoss_' . $suffix
				], $wanName, $siteWans);
			}

			$lastPeriod = end($periods);
			$periodData = (isset($lastPeriod['data']) && is_array($lastPeriod['data'])) ? $lastPeriod['data'] : [];
			$wanMetrics = (isset($periodData['wan']) && is_array($periodData['wan'])) ? $periodData['wan'] : [];
			$lastTime = $this->getMetricTimestamp((string) $lastPeriod['metricTime']);
			$this->SetValue('LastUpdate', $lastTime);
			foreach (['avgLatency' => 'AVGms', 'maxLatency' => 'maxms', 'packetLoss' => 'PacketLoss', 'uptime' => 'Uptime', 'downtime' => 'Downtime'] as $metric => $ident) {
				if (isset($wanMetrics[$metric])) {
					$this->SetValue($ident, $wanMetrics[$metric]);
				}
			}
			if (isset($wanMetrics['download_kbps'])) {
				$this->SetValue('ISPDownload', $wanMetrics['download_kbps'] / 1000);
			}
			if (isset($wanMetrics['upload_kbps'])) {
				$this->SetValue('ISPUpload', $wanMetrics['upload_kbps'] / 1000);
			}

			$activeWAN = $this->getWanNameForMetrics($wanMetrics, $siteWans);
			$this->SetValue('ActiveWAN', $activeWAN ?? '');

			foreach ($enabledWANs as $wanName) {
				$suffix = $this->sanitizeIdent($wanName);
				$wanPeriodMetrics = $this->getWanMetricFromPeriodData($periodData, $wanName, $siteWans);
				foreach (['avgLatency' => 'WANAvgLatency_', 'downtime' => 'WANDowntime_', 'maxLatency' => 'WANMaxLatency_', 'packetLoss' => 'WANPacketLoss_'] as $metric => $prefix) {
					if (isset($wanPeriodMetrics[$metric])) {
						$this->SetValue($prefix . $suffix, $wanPeriodMetrics[$metric]);
					}
				}
			}

			// Time, average and maximum latency, packet loss and downtime of each period.
			$history = [];
			foreach ($periods as $period) {
				$wan = $period['data']['wan'] ?? null;
				if (!is_array($wan) || !isset($period['metricTime'], $wan['avgLatency'])) {
					continue;
				}
				$history[] = [
					$this->getMetricTimestamp((string) $period['metricTime']),
					(int) round((float) $wan['avgLatency']),
					(int) round((float) ($wan['maxLatency'] ?? $wan['avgLatency'])),
					(int) round((float) ($wan['packetLoss'] ?? 0)),
					(int) round((float) ($wan['downtime'] ?? 0))
				];
			}

			$this->TileUpdate([
				'metricsError' => false,
				'time'         => $lastTime,
				'activeWan'    => $activeWAN,
				'uptime'       => $wanMetrics['uptime'] ?? null,
				'history'      => $history
			]);
		}

		public function getHosts() {
			$hosts = $this->getApiPages('/hosts') ?? [];
			$hostName = function (array $host): string {
				return (string) ($host['reportedState']['hostname'] ?? $host['id'] ?? '');
			};
			usort($hosts, function ($a, $b) use ($hostName) {
				return strcmp($hostName($a), $hostName($b));
			});

			$value = [];
			foreach ($hosts as $host) {
				$value[] = [ 'caption' => $hostName($host), 'value' => $host['id'] ];
			}
			if (count($value) === 0) {
				$value[] = [ 'caption' => 'default', 'value' => 'default' ];
			}
			$this->UpdateFormField("HostID", "options", json_encode($value));
			$this->SetBuffer( 'HostID', json_encode($value));
		}

		public function getSites() {
			$sites = $this->getAllSites() ?? [];
			usort($sites, function ($a, $b) {
				return strcmp((string) ($a['meta']['desc'] ?? ''), (string) ($b['meta']['desc'] ?? ''));
			});

			$value = [];
			foreach ($sites as $site) {
				if (($site['hostId'] ?? '') !== $this->ReadPropertyString("HostID")) {
					continue;
				}
				$value[] = [ 'caption' => (string) ($site['meta']['desc'] ?? $site['siteId']), 'value' => $site['siteId'] ];
			}
			if (count($value) === 0) {
				$value[] = [ 'caption' => 'default', 'value' => 'default' ];
			}
			$this->UpdateFormField("SiteID", "options", json_encode($value));
			$this->SetBuffer( 'SiteID', json_encode($value));
		}

		public function getSiteData() {
			$site = $this->getSiteBySelection();
			if ($site === null) {
				$this->TileUpdate(['siteError' => true]);
				return;
			}

			$statistics = $site['statistics'] ?? [];
			$counts = $statistics['counts'] ?? [];
			$internetIssues = is_array($statistics['internetIssues'] ?? null) ? count($statistics['internetIssues']) : 0;
			$this->SetValue('SiteName', isset($site['meta']['desc']) ? $site['meta']['desc'] : '');
			$this->SetValue('ActiveISP', isset($statistics['ispInfo']['name']) ? $statistics['ispInfo']['name'] : '');
			$this->SetValue('UnifiDevices', $counts['totalDevice'] ?? 0);
			$this->SetValue('WifiClients', $counts['wifiClient'] ?? 0);
			$this->SetValue('WiredClients', $counts['wiredClient'] ?? 0);
			$this->SetValue('GuestClients', $counts['guestClient'] ?? 0);
			$this->SetValue('OfflineDevices', $counts['offlineDevice'] ?? 0);
			$this->SetValue('PendingUpdates', $counts['pendingUpdateDevice'] ?? 0);
			$this->SetValue('CriticalNotifications', $counts['criticalNotification'] ?? 0);
			$this->SetValue('InternetIssues', $internetIssues);
			if (isset($statistics['percentages']['txRetry'])) {
				$this->SetValue('TxRetry', (float) $statistics['percentages']['txRetry']);
			}

			$siteWans = $this->getSiteWans();
			$tileWans = [];
			foreach ($this->getEnabledWANNames() ?? [] as $wanName) {
				if (!isset($siteWans[$wanName]) || !is_array($siteWans[$wanName])) {
					// Not reported at the moment: the variables keep their last value.
					continue;
				}
				$wanData = $siteWans[$wanName];
				$suffix = $this->sanitizeIdent($wanName);

				$this->SetValue('WAN_' . $suffix, $wanData['ispInfo']['name'] ?? '');
				$this->SetValue('WANIP_' . $suffix, $wanData['externalIp'] ?? '');
				$this->SetValue('WANUptime_' . $suffix, $wanData['wanUptime'] ?? 0);
				$this->SetValue('WANPortUp_' . $suffix, (bool) ($wanData['portUp'] ?? false));

				// Only values for display, see UnifiTile.
				$tileWans[] = [
					'name'     => $wanName,
					'isp'      => (string) ($wanData['ispInfo']['name'] ?? ''),
					'ip'       => (string) ($wanData['externalIp'] ?? ''),
					'up'       => (bool) ($wanData['portUp'] ?? false),
					'uptime'   => $wanData['wanUptime'] ?? null,
					'issues'   => is_array($wanData['wanIssues'] ?? null) ? count($wanData['wanIssues']) : 0,
					'priority' => (int) ($wanData['failoverPriority'] ?? 99)
				];
			}
			usort($tileWans, function ($a, $b) {
				return $a['priority'] <=> $b['priority'];
			});

			$this->TileUpdate([
				'siteError' => false,
				'name'      => (string) ($site['meta']['desc'] ?? ''),
				'isp'       => (string) ($statistics['ispInfo']['name'] ?? ''),
				'gateway'   => (string) ($statistics['gateway']['shortname'] ?? ''),
				'devices'   => (int) ($counts['totalDevice'] ?? 0),
				'offline'   => (int) ($counts['offlineDevice'] ?? 0),
				'updates'   => (int) ($counts['pendingUpdateDevice'] ?? 0),
				'critical'  => (int) ($counts['criticalNotification'] ?? 0),
				'wifi'      => (int) ($counts['wifiClient'] ?? 0),
				'wired'     => (int) ($counts['wiredClient'] ?? 0),
				'issues'    => $internetIssues,
				'wans'      => $tileWans
			]);
		}

		public function GetConfigurationForm(){       
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );
			$arrayStatus[] = array( 'code' => 104, 'icon' => 'inactive', 'caption' => 'Instanz ist inaktiv (Timer = 0)' );
			$arrayStatus[] = array( 'code' => 201, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Fehler Datenabfrage' );
			$arrayStatus[] = array( 'code' => 400, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Bad Request' );
			$arrayStatus[] = array( 'code' => 401, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Unauthorized' );
			$arrayStatus[] = array( 'code' => 403, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Forbidden' );
			$arrayStatus[] = array( 'code' => 404, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Not Found' );
			$arrayStatus[] = array( 'code' => 429, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Rate Limit' );
			$arrayStatus[] = array( 'code' => 500, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Server Error' );
			$arrayStatus[] = array( 'code' => 502, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Bad Gateway' );

			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Unifif Site Manager Metrics') ); 
			$arrayElements[] = array( 'type' => 'Label', 'label' => 'Bitte API Key unter "https://unifi.ui.com/" erzeugen -> Settings -> API Keys');
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off, 300=5min (Metriken liegen im 5-Minuten-Raster vor)' );
			$arrayElements[] = array( 'type' => 'PasswordTextBox', 'name' => 'APIKey', 'caption' => 'APIKey' );

			$Bufferdata = $this->GetBuffer("HostID");
			
			if ($Bufferdata=="" || $Bufferdata === null) {
				$arrayOptions[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}			
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'HostID', 'caption' => 'Host ID', 'options' => $arrayOptions );

			unset($arrayOptions);
			$Bufferdata = $this->GetBuffer("SiteID");
			if ($Bufferdata=="" || $Bufferdata === null) {
				$arrayOptions[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'SiteID', 'caption' => 'Site ID', 'options' => $arrayOptions );

			$wanRows = $this->getWANRowsForForm();
			$arrayElements[] = array(
				'type'    => 'List',
				'name'    => 'WANSelection',
				'caption' => 'WAN Schnittstellen (nur gewaehlte SiteID)',
				'rowCount'=> 6,
				// The rows come from the API and are merged with the saved selection in getWANRowsForForm.
				'loadValuesFromConfiguration' => false,
				'columns' => array(
					// Not editable, therefore not saved without "save": the selection would lose its WAN names.
					array('caption' => 'WAN', 'name' => 'Name', 'width' => '220px', 'save' => true),
					array('caption' => 'Aktiv', 'name' => 'Enabled', 'width' => '100px', 'edit' => array('type' => 'CheckBox'))
				),
				'values'  => $wanRows
			);

			$arrayActions = array();

			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Hosts Holen', 'onClick' => 'UISPM_getHosts($id);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Sites Holen', 'onClick' => 'UISPM_getSites($id);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'WANs Laden', 'onClick' => 'IPS_RequestAction($id, "ReloadForm", 0);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Metrics Holen', 'onClick' => 'UISPM_getMetrics($id);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Site Daten Holen', 'onClick' => 'UISPM_getSiteData($id);' );


			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
	    }

		public function RequestAction($Ident, $Value)
		{
			if ($Ident === 'ReloadForm') {
				$this->ReloadForm();
				return;
			}

			throw new Exception('Invalid ident');
		}
	}
