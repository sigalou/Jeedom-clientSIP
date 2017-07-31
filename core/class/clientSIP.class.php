<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'sip', 'class', 'clientSIP');
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
			 $clientSIP->_sip=null;
			$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
			$clientSIP->checkAndUpdateCmd('CallStatus','Inactif');
			$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
		}
	}	
	public function toHtml($_version = 'mobile') {
		$_version = jeedom::versionAlias($_version);
		$replace = array(
			'#id#' => $this->getId(),
			'#name#' => ($this->getIsEnable()) ? $this->getName() : '<del>' . $this->getName() . '</del>',
			'#eqLink#' => $this->getLinkToConfiguration(),
			'#background#' => $this->getBackgroundColor($_version),				
			'#height#' => $this->getDisplay('height', 'auto'),
			'#width#' => $this->getDisplay('width', 'auto')
		);	
		foreach ($this->getCmd(null, null, true) as $cmd) {
			 $replace['#'.$cmd->getLogicalId().'#'] = $cmd->toHtml($_version);
		}   
		return template_replace($replace, getTemplate('core', $_version, 'eqLogic','clientSIP'));
	}
	public function postSave() {
		$this->AddCommande('Etat connexion','RegStatus','info', 'string');
		$this->AddCommande('Etat appel','CallStatus','info', 'string');
		$this->AddCommande('Appel','call','action','message','call');
		$this->checkAndUpdateCmd('RegStatus','Inactif');
	}
	public static $_widgetPossibility = array('custom' => array(
	        'visibility' => true,
	        'displayName' => true,
	        'optionalParameters' => true,
	));
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
	public static function ConnectSip($_option){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$Host=config::byKey('Host', 'clientSIP');
			$Port=config::byKey('Port', 'clientSIP');
			$Username=$clientSIP->getConfiguration("Username");
			$Password=$clientSIP->getConfiguration("Password");
			try {			
				$clientSIP->checkAndUpdateCmd('RegStatus','En cours');
				$sip = new sip($Host);
				$sip->setUsername($Username);
				$sip->setPassword($Password);
				$sip->setMethod('REGISTER');
				$sip->setProxy($Host.':'.$Port);
				$sip->setFrom('sip:'.$Username.'@'.$Host/*.':'.$Port*/);
				$sip->setUri('sip:'.$Username.'@'.$Host.';transport='.$clientSIP->getConfiguration("transport"));
				$sip->setServerMode(true);
				$res = $sip->send();
				$clientSIP->checkAndUpdateCmd('RegStatus','Enregistrer');
				while(true){
					//$sip->listen('NOTIFY');
					$sip->newCall();
					$sip->listen('INVITE');
					$clientSIP->RepondreAppel($sip);
				}
			} catch (Exception $e) {
				die("Caught exception ".$e->getMessage."\n");
			}
			
		}
	}
	public function RepondreAppel($sip) {
		//$sip->reply(100,'Trying');
		$sip->reply(180,'Ringing');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		event::add('clientSIP::call', utils::o2a($this));
		cache::set('clientSIP::call::statut', 'Sonnerie', 0);
		while(true){
      			$cache = cache::byKey('clientSIP::call::statut');
			switch($cache->getValue(true)){
				case 'Decrocher':
					//ajouter les options de compatibilité de jeedom
					$sip->reply(200,'Ok');
					cache::set('clientSIP::call::statut', 'Communication', 0);
					$this->checkAndUpdateCmd('CallStatus','Communication en cours');
					event::add('clientSIP::rtp', utils::o2a($this));
				break;
				case 'Racrocher':
					//$sip->reply(603,'Decline');
					$sip->setMethod('CANCEL');
					$sip->send();
					$this->checkAndUpdateCmd('CallStatus','Racrocher');
				return;
			}
		}
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='string',$Template='') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new clientSIPCmd();
			$Commande->setId(null);
			$Commande->setEqLogic_id($this->getId());
		}
		$Commande->setLogicalId($_logicalId);
		$Commande->setName($Name);
		$Commande->setIsVisible(1);
		$Commande->setType($Type);
		$Commande->setSubType($SubType);
			if($Template !=''){
			$Commande->setTemplate('dashboard',$Template);
			$Commande->setTemplate('mobile',$Template);
		}
		$Commande->save();
		return $Commande;
	}
}
class clientSIPCmd extends cmd {
	public function execute($_options = null){
		switch($this->getLogicalId()){
			case 'call':				
				$Host=config::byKey('Host', 'clientSIP');
				$Port=config::byKey('Port', 'clientSIP');
				$Username=$this->getEqLogic()->getConfiguration("Username");
				$Password=$this->getEqLogic()->getConfiguration("Password");
				$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Inactif');
				$sip = new sip($Host);
				$sip->setUsername($Username);
				$sip->setPassword($Password);
				$sip->setProxy($Host.':'.$Port);
				//$sip->setMethod('REGISTER');
				$sip->setFrom('sip:'.$Username.'@'.$Host);
				$sip->setUri('sip:'.$Username.'@'.$Host.';transport='.$this->getEqLogic()->getConfiguration("transport"));
				//$res = $sip->send();
				$sip->setTo('sip:'.$_options['message'].'@'.$Host);
				$sip->setMethod('INVITE');
				$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Appel en cours');
				$res=$sip->send();
				switch($res){
					case '200':
						$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Décroché');
					break;
					case '318':
						$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Sonnerie');
					break;
					default:
						$sip=null;
						$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Inactif');
					break;
				}
			break;
		}
	}
}
?>
