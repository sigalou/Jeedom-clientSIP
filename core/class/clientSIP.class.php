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
	public function toHtml($_version = 'mobile',$Dialog=true) {
		$User["User"]=$this->getConfiguration("Username");
		$User["Pass"]=$this->getConfiguration("Password");
		$User["Realm"]=config::byKey('Host', 'clientSIP');
		$User["Display"]=$this->getName();
		$User["WSServer"]="wss://".config::byKey('Host', 'clientSIP').":"./*config::byKey('Port', 'clientSIP').*/"8089/ws";
		$_version = jeedom::versionAlias($_version);
		$replace = array(
			'#id#' => $this->getId(),
			'#name#' => ($this->getIsEnable()) ? $this->getName() : '<del>' . $this->getName() . '</del>',
			'#eqLink#' => $this->getLinkToConfiguration(),
			'#background#' => $this->getBackgroundColor($_version),				
			'#height#' => $this->getDisplay('height', 'auto'),
			'#width#' => $this->getDisplay('width', '250'),
			'#User#' => json_encode($User)
		);	
		return template_replace($replace, getTemplate('core', $_version, 'eqLogic','clientSIP'));
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
