<?php

/**
 * Calendar representation with support for caching using SQLlite.
 */
class TimeZone {

#	const CACHETIME = 60*15; // 15 minutes
	const CACHETIME = 15; // 15 seconds
	
	/* Instance of sspmod_core_Storage_SQLPermanentStorage
	 * 
	 * key1		calendar URL
	 * key2		NULL
	 * type		'calendar'
	 *
	 */
	protected $db;

	public $ip;
	public $user;
	
	public function TimeZone(FoodleDBConnector $db, $ip = NULL, $user = null) {
		$this->db = $db;

		if (is_null($ip)) $ip = $_SERVER['REMOTE_ADDR'];
		
		if (isset($user)) $this->user = $user;
		
		if (empty($ip))
			throw new Exception('Trying to use the TimeZone class without specifying an IP address');
		$this->ip = $ip;
		
		// $this->db = new sspmod_core_Storage_SQLPermanentStorage('iptimezone');

	}
	
	
	

	public function lookupRegion($region) {
		
		if ($this->db->tzExists('region-' . $region)) {
			// error_log('IP Geo location: Found region [' . $region . '] in cache.');
			return $this->db->tzGet('region-' . $region);
		}
		
		// error_log('Lookup region');
		$rawdata = file_get_contents('http://freegeoip.net/tz/json/' . $region);
		
		if (empty($rawdata)) throw new Exception('Error looking up IP geo location for [' . $ip . ']');
		$data = json_decode($rawdata, TRUE);
		if (empty($data)) throw new Exception('Error decoding response from looking up IP geo location for [' . $ip . ']');
		
		if (empty($data['timezone'])) throw new Exception('Could not get TimeZone from IP lookup');
		
		$timezone = $data['timezone'];
		
		// error_log('IP Geo location: Store region [' . $region . '] in cache: ' . $timezone);
		$this->db->tzSet('region-' . $region, $timezone);
		
		return $timezone;	
	}
	
	public function lookupIP($ip) {

		if ($this->db->tzExists('ip-' . $ip)) {
			// error_log('IP Geo location: Found ip [' . $ip . '] in cache.');
			return $this->db->tzGet('ip-' . $ip);
		}
		
		// error_log('Lookup IP');
		$rawdata = file_get_contents('http://freegeoip.net/json/' . $ip);
		
		if (empty($rawdata)) throw new Exception('Error looking up IP geo location for [' . $ip . ']');
		$data = json_decode($rawdata, TRUE);
		if (empty($data)) throw new Exception('Error decoding response from looking up IP geo location for [' . $ip . ']');
		
		if (empty($data['country_code'])) throw new Exception('Could not get Coutry Code from IP lookup');
		if (empty($data['region_code'])) throw new Exception('Could not get Coutry Code from IP lookup');
		
		$region = $data['country_code'] . '/' . $data['region_code'];
		
		// error_log('IP Geo location: Store ip [' . $ip . '] in cache: ' . $region);
		$this->db->tzSet('ip-' . $ip, $region);
		
		return $region;
	}
	
	public function getTimeZone() {
		$tz = null;
		
		if (isset($this->user)) {
			if (isset($this->user->timezone)) {
				return $this->user->timezone;
			}
		}
		
		try {
			$tz = $this->lookupRegion($this->lookupIP($this->ip));
		} catch(Exception $e) {
			// $tz = 'Europe/Amsterdam';
		}
		
		return $tz;
	}
	

	
	public function getSelectedTimeZone() {
	
	
		if (isset($_REQUEST['timezone'])) {
		
			if (isset($this->user) && isset($this->user->timezone)) {
				$this->user->timezone = $_REQUEST['timezone'];
				$this->user->db->saveUser($this->user);
			}
			return $_REQUEST['timezone'];
		}
		return $this->getTimeZone();
	}
	
	public function getHTMLList($default = NULL, $autosubmit = FALSE) {

		$tzlist = DateTimeZone::listIdentifiers();
		// $tzlist = array_reverse($tzlist);
		$thiszone = $this->getTimeZone();
		
		if (is_null($default)) $default = $thiszone;
		
		$a = '';
		if ($autosubmit) $a = "onchange='this.form.submit()' ";
		
		$html = '<select ' .  $a . 'name="timezone">' . "\n";
		foreach($tzlist AS $tz) {
			if ($tz == $default) {
				$html .= ' <option selected="selected" value="' . htmlspecialchars($tz) . '">' . htmlspecialchars($tz) . '</option>' . "\n";				
			} else {
				$html .= ' <option value="' . htmlspecialchars($tz) . '">' . htmlspecialchars($tz) . '</option>' . "\n";				
			}

		}
		$html .= '</select>' . "\n";
		return $html;
	}
	

}
