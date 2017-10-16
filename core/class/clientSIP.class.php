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
				/*$cron = cron::byClassAndFunction('clientSIP', 'updateRegister', array('id' => $clientSIP->getId()));
				if (!is_object($cron)) 	
					return $return;*/
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
			$cron = cron::byClassAndFunction('clientSIP', 'updateRegister', array('id' => $clientSIP->getId()));
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
			$clientSIP->Listen();
		}
	}
	public static function updateRegister($_option){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$clientSIP->Register();	
		}
	}
	public function Register(){
		$Host=config::byKey('Host', 'clientSIP');
		$Port=config::byKey('Port', 'clientSIP');
		$Username=$this->getConfiguration("Username");
		$Password=$this->getConfiguration("Password");
		$this->checkAndUpdateCmd('RegStatus','Inactif');
		$sip= new sip(network ::getNetworkAccess('internal', 'ip', '', false)); 
		$sip->setUsername($Username);
		$sip->setPassword($Password);
		$sip->setMethod('REGISTER');
		//$sip->setProxy($Host.':'.$Port);
		$sip->setFrom('sip:'.$Username.'@'.$Host/*.':'.$Port*/);
		$sip->setUri('sip:'.$Username.'@'.$Host.';transport='.$this->getConfiguration("transport"));
		$sip->setServerMode(true);
		$res = $sip->send();
		$this->checkAndUpdateCmd('RegStatus','Enregistrer');	
      	return $sip;
	}	
	public function Listen(){
		$Host=config::byKey('Host', 'clientSIP');
		$Port=config::byKey('Port', 'clientSIP');
		try {	
			if(!is_object($sip))
				$sip=$this->Register();
			//while($this->getCmd(null,'RegStatus')->execCmd() == 'Enregistrer');
			while(true){
				$sip->newCall();
				$sip->listen('INVITE');
				$this->RepondreAppel();
			}
		} catch (Exception $e) {
			die("Caught exception ".$e->getMessage."\n");
		}	
	}
	public function RepondreAppel($sip) {
		$call['status']='ringing'; 
		$call['flow']='incoming';  
		$call['number']='';  
		$call['start']=date('d/m/Y H:i:s');  
		self::addHistoryCall($call);
		//$sip->reply(100,'Trying');
		$sip->reply(180,'Ringing');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		event::add('clientSIP::call', utils::o2a($this));
		$CallStatus=$this->getCmd(null,'CallStatus');
		$call['status']= 'ringing';
		while($CallStatus->execCmd() == 'Sonnerie');
		self::addCacheMonitor($call);
		switch($CallStatus->execCmd()){
			case 'Decrocher':
				$call['status']= 'call';
				$this->Decrocher($sip);
			break;
			case 'Racrocher':
				$call['status']= 'reject';
				$this->Racrocher($sip);
			return;
		}
		self::addHistoryCall($call);
		while($CallStatus->execCmd() == 'Decrocher');
		$this->Racrocher();
	}
	public function Decrocher($sip) {
		//ajouter les options de compatibilité de jeedom
		$sip->reply(200,'Ok');
		event::add('clientSIP::rtsp', $sip->rtsp());
		$this->checkAndUpdateCmd('CallStatus','Décrocher');
	}
	public function Racrocher($sip) {
		$Host=config::byKey('Host', 'clientSIP');
		$Port=config::byKey('Port', 'clientSIP');
		$Username=$this->getConfiguration("Username");
		$Password=$this->getConfiguration("Password");
		//$sip->reply(603,'Decline');
		$sip->setMethod('CANCEL');
		$sip->setFrom('sip:'.$Username.'@'.$Host/*.':'.$Port*/);
		$$sip->send();
		$this->checkAndUpdateCmd('CallStatus','Racrocher');
	}
	public function call($number) {
		$Host=config::byKey('Host', 'clientSIP');
		$Port=config::byKey('Port', 'clientSIP');
		$Username=$this->getConfiguration("Username");
		$Password=$this->getConfiguration("Password");
		$this->checkAndUpdateCmd('RegStatus','Inactif');
		$sip= new sip(network ::getNetworkAccess('internal', 'ip', '', false)); 
		$sip->setUsername($Username);
		$sip->setPassword($Password);
		$sip->setMethod('REGISTER');
		$this->checkAndUpdateCmd('CallStatus','Racrocher');
		$sip->newCall();
		$sip->setFrom('sip:'.$Username.'@'.$Host);
		$sip->setUri('sip:'.$number.'@'.$Host.';transport='.$this->getConfiguration("transport"));
		$sip->setTo('sip:'.$number.'@'.$Host);
		$sip->setMethod('INVITE');
		$this->checkAndUpdateCmd('CallStatus','Appel en cours');
		$res=$sip->send();
		$call['status']='ringing'; 
		$call['flow']='outcoming';  
		$call['number']=$number;  
		$call['start']=date('d/m/Y H:i:s');  
		self::addHistoryCall($call);
		switch($res){
			case '200':
				$this->checkAndUpdateCmd('CallStatus','Décroché');
			break;
			case '318':
				$this->checkAndUpdateCmd('CallStatus','Sonnerie');
			break;
			default:
				$this->checkAndUpdateCmd('CallStatus','Racrocher');
			break;
		}
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
				$this->getEqLogic()->call($_options['message']);
			break;
		}
	}
}
?>
