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
			$Host=config::byKey('Host', 'clientSIP');
			$Port=config::byKey('Port', 'clientSIP');
			$Username=$this->getConfiguration("Username");
			$Password=$this->getConfiguration("Password");
			$api = new PhpSIP($Host,$Port);
			$api->setUsername($Username); // authentication username
			$api->setPassword(); // authentication password
			// $api->setProxy('some_ip_here'); 
			$api->addHeader('Event: resync');
			$api->setMethod('NOTIFY');
			$api->setFrom('sip:'.$Username.'@'.$Host.':'.$Port);
			$api->setUri('sip:'.$Username.'@'.$Host.':'.$Port);
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
