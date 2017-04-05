<?php
namespace NerdBaggy\StatusPage;

class statusPage
{

    public function getChecks($action = null)
    {
        $cache = phpFastCache();

        $allChecks = $cache->get('statuspage-allChecks');
        if ($allChecks === null) {

            if ($action === 'update'){
                $this->updateCache(true);
            }else{
                $this->updateCache(false);
            }
            $allChecks = $cache->get('statuspage-allChecks');
        }

        $needsUpdated = false;
		if(count($allChecks)){
			foreach ($allChecks as $key => $cid) {
				$allCheckInfo[] = $cache->get('statuspage-' . $cid);
				if (count($allCheckInfo[0]['logs']) === 0 && $action === 'update'){
					$needsUpdated = true;
					unset($allCheckInfo);
					break;
				}
			}
		}

        if ($needsUpdated){
            $this->updateCache(true);

            foreach ($allChecks as $key => $cid) {
                $allCheckInfo[] = $cache->get('statuspage-' . $cid);
            }
        }
        return $allCheckInfo;
    }

    public function updateCache($action)
    {
        date_default_timezone_set("UTC");
        $cache            = phpFastCache();
        $checksArray      = $this->getChecksJson($action);
        $excludedMonitors = unserialize(constant('excludedMonitors'));

		if(count($checksArray['monitors'])){
			foreach ($checksArray['monitors'] as $key => $check) {
				if (!in_array($check['id'], $excludedMonitors)) {


					$allCheckID[]       = $check['id'];
					$fixedResponseTimes = array();
					$fixedEventTime = array();

					if (is_array($check['response_times'])) {

						foreach ($check['response_times'] as $key => $restime) {
							$fixedResponseTimes[] = array(
								'datetime' => date("Y-m-d G:i:s", $restime['datetime']),
								'value' => $restime['value']
								);
						}

					}

					if (!is_null($check['logs'])){

					   foreach ($check['logs'] as $key => $dt) {
						   
						$fixedEventTime[] = array(
							'actualTime' => date("m/d/Y G:i:s", $dt['datetime']),
							'type' => $dt['type'],
							'datetime' => $dt['datetime'],
                            'duration' => intval($dt['duration'])
							);
                        }

                    }


				$tempCheck = array(
					'id' => $check['id'],
					'name' => html_entity_decode($check['friendly_name']),
					'type' => $check['type'],
					'interval' => $check['interval'],
					'status' => $check['status'],
					'allUpTimeRatio' => $check['all_time_uptime_ratio'],
					'customUptimeRatio' => explode("-", $check['custom_uptime_ratio']),
					'log' => $fixedEventTime,
					'responseTime' => $fixedResponseTimes,
					'timezone' => intval($checksArray['timezone']),
					'currentTime' => time() + (intval($checksArray['timezone']))*60
					);
				$cache->set('statuspage-' . $check['id'], $tempCheck, constant('cacheTime'));
			}
		}
	}
    $cache->set('statuspage-allChecks', $allCheckID, constant('cacheTime'));
}

public function getTableHeaders()
{
    foreach (unserialize(constant('historyDaysNames')) as $key => $historyDaysName) {
        $headToSend[] = $historyDaysName;
    }
    $headToSend[] = 'Total';
    return $headToSend;
}

public function padIt($checks)
{
    return 'StatusPage(' . json_encode($checks) . ')';
}

private function getChecksJson($action)
{
    $apiKey     = constant('apiKey');
    $historyDay = constant('historyDay');

    // $url = "https://api.uptimerobot.com/getMonitors?apikey=$apiKey&format=json&noJsonCallback=1&customUptimeRatio=$historyDay";
    $url = "https://api.uptimerobot.com/v2/getMonitors";
    $fields = "api_key=$apiKey&format=json&custom_uptime_ratios=$historyDay&all_time_uptime_ratio=1";

    if ($action){

        $fields .= '&logs=1&logs_limit=20&response_times=1&response_times_average=30&timezone=1';
    }

    if (constant('includedMonitors') != '') {
        $monitors = constant('includedMonitors');
        $fields .= "&monitors=$monitors";
 	}

    if (constant('searchMonitors') != '') {
        $search = constant('searchMonitors');
        $fields .= "&search=$search";
 	}

    $curl = curl_init();
    /*curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => 'UptimeRobot Public Status Page',
        CURLOPT_CONNECTTIMEOUT => 10
    ));*/
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $fields, //"api_key=enterYourAPIKeyHere&format=json&logs=1",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ),
    ));
    $checks = json_decode(curl_exec($curl), TRUE);
        //Checks to make sure curl is happy
    if (curl_errno($curl)) {
        return False;
    }
    curl_close($curl);
        //Checks to make sure UptimeRobot didn't return any errors
    if ($checks['stat'] != 'ok') {
        error_log('UptimeRobot API Error - ' . $checks['message']);
        return False;
    }
    return $checks;
}

}
