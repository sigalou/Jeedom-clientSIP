<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'PhpSIP', 'class', 'clientSIP');
class clientSIP extends eqLogic {
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
				if (!is_object($cron)) 	
					return $return;
			}
		}
		$return['state'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		log::remove('clientSIP');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] == 'ok') 
			return;
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				$clientSIP->CreateDemon();   
			}
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
		}
	}	
	public function toHtml($_version = 'mobile') {
		$User["User"]=$this->getConfiguration("Username");
		$User["Pass"]=$this->getConfiguration("Password");
		$User["Realm"]=config::byKey('Host', 'clientSIP');
		$User["Display"]=$this->getName();
		//$User["WSServer"]="wss://".config::byKey('Host', 'clientSIP').":"./*config::byKey('Port', 'clientSIP').*/"8089/ws";
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
	public function CreateDemon() {
		$cron =cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('clientSIP');
			$cron->setFunction('ConnectSip');
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			$cron->setDeamon(1);
			$cron->setSchedule('* * * * *');
			$cron->setTimeout('999999');
			$cron->save();
		}
		$cron->save();
		$cron->start();
		$cron->run();
		return $cron;
	}
	public static function ConnectSip(){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = Volets::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			try{
				$Host=config::byKey('Host', 'clientSIP');
				$Port=config::byKey('Port', 'clientSIP');
				$Username=$clientSIP->getConfiguration("Username");
				$Password=$clientSIP->getConfiguration("Password");
				$api = new PhpSIP($Host,$Port);
				$api->setUsername($Username); // authentication username
				$api->setPassword(); // authentication password
				$api->setProxy('some_ip_here'); 
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
}
class clientSIPCmd extends cmd {
	public function execute($_options = null){
		
	}
}
?>
