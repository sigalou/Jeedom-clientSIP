<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'sip', 'class', 'clientSIP');
class clientSIP extends eqLogic {
	public $_sip;
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				$cron = cron::byClassAndFunction('clientSIP', 'Register', array('id' => $clientSIP->getId()));
				if (!is_object($cron)) 	
					return $return;
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
			$clientSIP->checkAndUpdateCmd('CallStatus','Racrocher');
			$cron = cron::byClassAndFunction('clientSIP', 'Register', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
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
		$this->AddCommande('Etat appel','CallStatus','info', 'string','CallStatus');
		$this->AddCommande('Appel','call','action','message','call');
		$this->checkAndUpdateCmd('RegStatus','Inactif');
	}
	public static $_widgetPossibility = array('custom' => array(
	        'visibility' => true,
	        'displayName' => true,
	        'optionalParameters' => true,
	));
	public function CreateDemon() {
		$cron =cron::byClassAndFunction('clientSIP', 'Register', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('clientSIP');
			$cron->setFunction('Register');
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			$minute=round($this->getConfiguration('Expiration')/60,0);
			$second=$this->getConfiguration('Expiration')-$minute*60;
			if($minute>60){
				$heure='*/'.round($minute/60,0);
				$minute=$minute-$heure*60;
			}
			else
				$heure='*';
			$cron->setSchedule('*/'.$minute.' '.$heure.' * * *');
			$cron->save();
		}
		$cron =cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('clientSIP');
			$cron->setFunction('ConnectSip');
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			$cron->setDeamon(1);
			$cron->setSchedule('* * * * *');
			$cron->setTimeout($minute);
			$cron->save();
		}
		$cron->start();
		$cron->run();
	}
	public static function ConnectSip($_option){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$Host=config::byKey('Host', 'clientSIP');
			$Port=config::byKey('Port', 'clientSIP');
			try {			
				$clientSIP->checkAndUpdateCmd('RegStatus','Enregistrer');
				while($clientSIP->getCmd(null,'RegStatus')->execCmd() == 'Decrocher');
				while(true){
					$clientSIP->_sip->newCall();
					$clientSIP->_sip->listen('INVITE');
					$clientSIP->RepondreAppel();
				}
			} catch (Exception $e) {
				die("Caught exception ".$e->getMessage."\n");
			}
			
		}
	}
	public static function Register($_option){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$Host=config::byKey('Host', 'clientSIP');
			$Port=config::byKey('Port', 'clientSIP');
			$Username=$clientSIP->getConfiguration("Username");
			$Password=$clientSIP->getConfiguration("Password");
			
			$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
			if(!is_object($clientSIP->_sip))
				$clientSIP->_sip= new sip($Host); 
			$clientSIP->_sip->setUsername($Username);
			$clientSIP->_sip->setPassword($Password);
			$clientSIP->_sip->setMethod('REGISTER');
			//$clientSIP->_sip->setProxy($Host.':'.$Port);
			$clientSIP->_sip->setFrom('sip:'.$Username.'@'.$Host/*.':'.$Port*/);
			$clientSIP->_sip->setUri('sip:'.$Username.'@'.$Host.';transport='.$clientSIP->getConfiguration("transport"));
			$clientSIP->_sip->setServerMode(true);
			$res = $clientSIP->_sip->send();
			$clientSIP->checkAndUpdateCmd('RegStatus','Enregistrer');			
		}
	}
	public function RepondreAppel() {
		//$clientSIP->_sip->reply(100,'Trying');
		$clientSIP->_sip->reply(180,'Ringing');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		event::add('clientSIP::call', utils::o2a($this));
		while($this->getCmd(null,'CallStatus')->execCmd() == 'Sonnerie');
		$call['start']= date('d-m-Y H:i:s');
		$call['status']= 'ringing';
		$call['status']= 'incoming';
		self::addCacheMonitor($call);
		switch($this->getCmd(null,'CallStatus')->execCmd()){
			case 'Decrocher':
				$this->Decrocher($sip);
				$call['status']= 'open';
				self::addCacheMonitor($call);
			break;
			case 'Racrocher':;
				$this->Racrocher();
			return;
		}
		while($this->getCmd(null,'CallStatus')->execCmd() == 'Decrocher');
		$this->Racrocher();
	}
	public function Decrocher() {
		//ajouter les options de compatibilité de jeedom
		$clientSIP->_sip->reply(200,'Ok');
		event::add('clientSIP::rtsp', $clientSIP->_sip->rtsp());
		$this->checkAndUpdateCmd('CallStatus','Décrocher');
	}
	public function Racrocher() {
		$Host=config::byKey('Host', 'clientSIP');
		$Port=config::byKey('Port', 'clientSIP');
		$Username=$this->getConfiguration("Username");
		$Password=$this->getConfiguration("Password");
		//$sip->reply(603,'Decline');
		$clientSIP->_sip->setMethod('CANCEL');
		$clientSIP->_sip->setFrom('sip:'.$Username.'@'.$Host/*.':'.$Port*/);
		$clientSIP->_sip->send();
		$this->checkAndUpdateCmd('CallStatus','Racrocher');
	}
	public static function addHistoryCall($_call) {
		$cache = cache::byKey('clientSIP::HistoryCall');
		$value = json_decode($cache->getValue('[]'), true);
		if($key=array_search($value,$_call['start'])===false)
			$value[$key]=$_call;
		else
			$value[] = $_call;
		cache::set('clientSIP::HistoryCall', json_encode(array_slice($value, -250, 250)), 0);
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
				$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Racrocher');
				$sip = new sip($Host);
				$sip->setUsername($Username);
				$sip->setPassword($Password);
				//$sip->setProxy($Host.':'.$Port);
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
						$this->getEqLogic()->checkAndUpdateCmd('CallStatus','Racrocher');
					break;
				}
			break;
		}
	}
}
?>
