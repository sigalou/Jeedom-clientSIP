<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'PhpSIP', 'class', 'clientSIP');
class clientSIP extends eqLogic {
	public function preInsert() {
	}
	public function preSave() {
	}
	public function postSave() {		
	}	
	private function ConnectSip(){
		try{
			$api = new PhpSIP();
			$api->setUsername($this->getConfiguration("Username")); // authentication username
			$api->setPassword($this->getConfiguration("Password")); // authentication password
			// $api->setProxy('some_ip_here'); 
			$api->addHeader('Event: resync');
			$api->setMethod('NOTIFY');
			$api->setFrom('sip:10000@sip.domain.com');
			$api->setUri('sip:10000@sip.domain.com');
			$res = $api->send();
			
			log::add('clientSIP','debug',"response: $res");

		} catch (Exception $e) {
			log::add('clientSIP','error',$e);
		}
	}
}
class clientSIPCmd extends cmd {
	public function execute($_options = null){
		
	}
}
?>
