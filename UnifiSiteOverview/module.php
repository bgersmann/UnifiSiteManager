<?php

declare(strict_types=1);
	class UnifiSiteOverview extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyString( 'APIKey', '' );
			$this->RegisterPropertyString( 'HostID', '' );
			$this->RegisterPropertyString( 'SiteID', '' );
			$this->RegisterPropertyInteger('Timer', 0);
			$this->RegisterPropertyBoolean("WAN2", 0);
			$this->RegisterTimer('Collect Data', 0, "UISPM_timerRun(\$_IPS['TARGET']);");
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
			$vpos = 100;
			$this->MaintainVariable( 'SiteName', $this->Translate( 'Name' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'LastUpdate', $this->Translate( 'Start Zeitraum' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME ], $vpos++, 1 );
			$this->MaintainVariable( 'ActiveISP', $this->Translate( 'Active ISP' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'WAN', $this->Translate( 'WAN' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'WANIP', $this->Translate( 'WAN IP' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'WANUptime', $this->Translate( 'WAN Uptime' ), 2, '', $vpos++, 1 );
			$this->MaintainVariable( 'WAN2', $this->Translate( 'WAN2' ), 3, '', $vpos++, $this->ReadPropertyBoolean("WAN2") );
			$this->MaintainVariable( 'WAN2IP', $this->Translate( 'WAN2 IP' ), 3, '', $vpos++, $this->ReadPropertyBoolean("WAN2") );
			$this->MaintainVariable( 'WAN2Uptime', $this->Translate( 'WAN2 Uptime' ), 2, '', $vpos++, $this->ReadPropertyBoolean("WAN2") );

			$this->MaintainVariable( 'AVGms', $this->Translate( 'Average Latency 1h' ), 1, '', $vpos++, 1 );
			$this->MaintainVariable( 'maxms', $this->Translate( 'Max Latency 1h' ), 1, '', $vpos++, 1 );
			$this->MaintainVariable( 'PacketLoss', $this->Translate( 'Packetloss 1h' ), 2, '', $vpos++, 1 );
			$this->MaintainVariable( 'Uptime', $this->Translate( 'Uptime 1h' ), 2, '', $vpos++, 1 );

			$this->MaintainVariable( 'UnifiDevices', $this->Translate( 'Unifi Devices' ), 1, '', $vpos++, 1 );
			$this->MaintainVariable( 'WifiClients', $this->Translate( 'Wifi Clients' ), 1, '', $vpos++, 1 );
			$this->MaintainVariable( 'WiredClients', $this->Translate( 'Wired Clients' ), 1, '', $vpos++, 1 );

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

		function timerRun() {
			$this->getMetrics();
			$this->getSiteData();
		}

		public function getApiData( string $endpoint = '' ):array {
			$APIKey = $this->ReadPropertyString( 'APIKey' );
			if ($APIKey == '') {
				$this->SendDebug("UnifiSiteApi", "API Key is empty", 0);
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://api.ui.com/ea'.$endpoint );
			curl_setopt( $ch, CURLOPT_HTTPGET, true );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-API-KEY:'.$APIKey ) );
			curl_setopt( $ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1' );
			$RawData = curl_exec( $ch );
			curl_close( $ch );
			if ($RawData === false) {
				// Handle error
				$this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}
			$JSONData = json_decode( $RawData, true );
			if ( isset( $JSONData[ 'statusCode' ] ) ) {
				if ($JSONData[ 'statusCode' ]<> 200) {
					// instance inactive
					$this->SetStatus( $JSONData[ 'statusCode' ] );
					return [];
				}        
			}
			$this->SendDebug("UnifiSiteApi", "Curl error: " . $RawData, 0);
			return $JSONData;
    	}

		public function getApiDataPost( string $endpoint = '', string $PostData = '' ):array {
			$APIKey = $this->ReadPropertyString( 'APIKey' );
			if ($APIKey == '') {
				$this->SendDebug("UnifiSiteApi", "API Key is empty", 0);
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://api.ui.com/ea'.$endpoint );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $PostData );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-API-KEY:'.$APIKey ) );
			curl_setopt( $ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1' );
			$RawData = curl_exec( $ch );
			curl_close( $ch );
			if ($RawData === false) {
				// Handle error
				$this->SendDebug("UnifiSiteApi", "Curl error: " . curl_error($ch), 0);
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}
			$JSONData = json_decode( $RawData, true );
			if ( isset( $JSONData[ 'statusCode' ] ) ) {
				if ($JSONData[ 'statusCode' ]<> 200) {
					// instance inactive
					$this->SetStatus( $JSONData['statusCode']);
					return [];
				}        
			}
			$this->SendDebug("UnifiGW", "Curl error: " . $RawData, 0);
			return $JSONData;
    	}

		public function getMetrics() {
			if ($this->GetStatus() != 102) {
				return;
			}
			$begin = new DateTime(date("Y-m-d H:0:0",strtotime('-1 hours')), (new DateTime)->getTimezone());
			$end = new DateTime(date("Y-m-d H:0:0"), (new DateTime)->getTimezone());
			$begin->setTimezone(new DateTimeZone("UTC"));
			$end->setTimezone(new DateTimeZone("UTC"));
			$post = [
				'sites' => array([
					'beginTimestamp'  => $begin->format("Y-m-d\TH:i:s\Z"),
					'hostId' => $this->ReadPropertyString("HostID"),
					'endTimestamp' => $end->format("Y-m-d\TH:i:s\Z"),
					'siteId' => $this->ReadPropertyString("SiteID")
				])
			];
			$PostArray=json_encode($post,1);
			#var_dump ($PostArray);

			$data = $this->getApiDataPost( '/isp-metrics/1h/query', $PostArray );
			if ( is_array( $data ) && isset( $data ) ) {
				foreach ($data['data']['metrics'] as $metrics) {
					foreach ($metrics['periods'] as $periods) {
						$aktDate = new DateTime($periods['metricTime'], (new DateTimeZone("UTC")));
						$aktDate->setTimezone((new DateTime)->getTimezone());
						$this->SetValue('LastUpdate', strtotime($aktDate->format("Y-m-d H:i:s")));
						$this->SetValue('AVGms', $periods['data']['wan']['avgLatency']);
						$this->SetValue('maxms', $periods['data']['wan']['maxLatency']);
						$this->SetValue('PacketLoss', $periods['data']['wan']['packetLoss']);
						$this->SetValue('Uptime', $periods['data']['wan']['uptime']);						
					}
				}
			}
		}


		public function getHosts() {
			$data[] = $this->getApiData( '/hosts' );			
			if ( is_array( $data ) && isset( $data ) ) {
				foreach ($data as $JSONData) {
				if (isset($JSONData[ 'data' ])) {
					$hosts = $JSONData[ 'data' ];
					usort( $hosts, function ( $a, $b ) {
						return strcmp($a['reportedState']['hostname'], $b['reportedState']['hostname']);
					});

					foreach ( $hosts as $host ) {
						$value[] = [
							'caption'=>$host[ 'reportedState' ]['hostname'],
							'value'=> $host[ 'id' ]
						];
					}
				} else {
					$value[] = [
						'caption'=>'default',
						'value'=> 'default'
					];
				}
				$this->UpdateFormField("HostID", "options", json_encode($value));
				$this->SetBuffer( 'HostID', json_encode($value));
				}
			}
		}

		public function getSites() {
			$data[] = $this->getApiData( '/sites' );			
			$bGefunden=false;
			if ( is_array( $data ) && isset( $data ) ) {
				foreach ($data as $JSONData) {
				if (isset($JSONData[ 'data' ])) {
					$sites = $JSONData[ 'data' ];
					usort( $sites, function ( $a, $b ) {
						return strcmp($a['meta']['desc'], $b['meta']['desc']);
					});					
					foreach ( $sites as $site ) {						
						if ($site['hostId'] !== $this->ReadPropertyString("HostID")) {
							continue; 
						}
						$value[] = [
							'caption'=>$site['meta']['desc'],
							'value'=> $site[ 'siteId' ]
						];
						$bGefunden=true;
					}
				} 
			}
		}
		if (!$bGefunden) {
			$value[] = [
				'caption'=>'default',
				'value'=> 'default'
			];
		}
		$this->UpdateFormField("SiteID", "options", json_encode($value));
		$this->SetBuffer( 'SiteID', json_encode($value));
		}

		public function getSiteData() {
			if ($this->GetStatus() != 102) {
				return;
			}
			$data[] = $this->getApiData( '/sites' );			
			if ( is_array( $data ) && isset( $data ) ) {
				foreach ($data as $JSONData) {
					if (isset($JSONData[ 'data' ])) {
						$sites = $JSONData[ 'data' ];
						foreach ( $sites as $site ) {						
							if ($site['hostId'] == $this->ReadPropertyString("HostID")) {
								#Host gefunden, daten lesen:
								$this->SetValue('SiteName', $site['meta']['desc']);
								$this->SetValue('ActiveISP', isset($site['statistics']['ispInfo']['name']) ? $site['statistics']['ispInfo']['name'] : '');
								$this->SetValue('WAN', isset($site['statistics']['wans']['WAN']['ispInfo']['name']) ? $site['statistics']['wans']['WAN']['ispInfo']['name'] : '');
								$this->SetValue('WANIP', isset($site['statistics']['wans']['WAN']['externalIp']) ? $site['statistics']['wans']['WAN']['externalIp'] : '');
								$this->SetValue('WANUptime', isset($site['statistics']['wans']['WAN']['wanUptime']) ? $site['statistics']['wans']['WAN']['wanUptime'] : '');
								$this->SetValue('UnifiDevices', isset($site['statistics']['counts']['totalDevice']) ? $site['statistics']['counts']['totalDevice'] : '');
								$this->SetValue('WifiClients', isset($site['statistics']['counts']['wifiClient']) ? $site['statistics']['counts']['wifiClient'] : '');
								$this->SetValue('WiredClients', isset($site['statistics']['counts']['wiredClient']) ? $site['statistics']['counts']['wiredClient'] : '');
								if ($this->ReadPropertyBoolean("WAN2")) {
									$this->SetValue('WAN2', isset($site['statistics']['wans']['WAN2']['ispInfo']['name']) ? $site['statistics']['wans']['WAN2']['ispInfo']['name'] : '');
									$this->SetValue('WAN2IP', isset($site['statistics']['wans']['WAN2']['externalIp']) ? $site['statistics']['wans']['WAN2']['externalIp'] : '');
									$this->SetValue('WAN2Uptime', isset($site['statistics']['wans']['WAN2']['wanUptime']) ? $site['statistics']['wans']['WAN2']['wanUptime'] : '');
								}

							}						
						}
					}
				} 
			}
		}

		public function GetConfigurationForm(){       
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );
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
			$arrayElements[] = array( 'type' => 'Label', 'label' => 'Bitte API Key unter "https://unifi.ui.com/" erzeugen -> Links auf "API"');
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off, 3600=1h' );
			$arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'APIKey', 'caption' => 'APIKey' );
			$arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'WAN2', 'caption' => $this->Translate('WAN2 Anzeigen') );

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

			$arrayActions = array();

			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Hosts Holen', 'onClick' => 'UISPM_getHosts($id);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Sites Holen', 'onClick' => 'UISPM_getSites($id);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Metrics Holen', 'onClick' => 'UISPM_getMetrics($id);' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Site Daten Holen', 'onClick' => 'UISPM_getSiteData($id);' );


			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
	    }
	}