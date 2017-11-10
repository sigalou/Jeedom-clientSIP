<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'sip', 'class', 'clientSIP');
class clientSIP extends eqLogic {
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$cmd = "dpkg -l | grep mplayer";
		exec($cmd, $output, $return_var);
		if (isset($output[0])) {
			if (`which pico2wave`) {
				$return['state'] = 'ok';
			} else {
				$return['state'] = 'nok';
			}
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}
	public static function dependancy_install() {
		passthru('/bin/bash ' . realpath(dirname(__FILE__)) . '/../../resources/install.sh ' . realpath(dirname(__FILE__)) . '/../../resources/ > ' . log::getPathToLog('clientSIP') . ' 2>&1 &');
	}
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
				$cron = cron::byClassAndFunction('clientSIP', 'WaitCall', array('id' => $clientSIP->getId()));
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
			$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
			$clientSIP->checkAndUpdateCmd('CallStatus','Racrocher');
			$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
			$cron = cron::byClassAndFunction('clientSIP', 'WaitCall', array('id' => $clientSIP->getId()));
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
			$minute=round($this->getConfiguration("Expiration")/60,0);
			$cron->setSchedule('*/'.$minute.' * * * *');
			$cron->save();
		}
		$cron->start();
		$cron->run();
		$cron =cron::byClassAndFunction('clientSIP', 'WaitCall', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('clientSIP');
			$cron->setFunction('WaitCall');
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
			$Username=$clientSIP->getConfiguration("Username");
			$Password=$clientSIP->getConfiguration("Password");
			$_sip = new sip($clientSIP->getId(),network ::getNetworkAccess('internal', 'ip', '', false));
			//while(true){
				$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
				$_sip->setUsername($Username);
				$_sip->setPassword($Password);
				$_sip->addHeader('Expires: '.$clientSIP->getConfiguration("Expiration"));
				$_sip->setMethod('REGISTER');
				//$_sip->setProxy($Host.':'.$Port);
				$_sip->setFrom('sip:'.$Username.'@'.$Host);
				$_sip->setUri('sip:'.$Username.'@'.$Host.':'.$Port.';transport='.$clientSIP->getConfiguration("transport"));
				$_sip->setServerMode(true);
				$res = $_sip->send();
				$clientSIP->checkAndUpdateCmd('RegStatus','Enregistrer');	
				//sleep($clientSIP->getConfiguration("Expiration"));
			//}
		}
	}
	public static function WaitCall($_option){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$Username=$clientSIP->getConfiguration("Username");
			$Password=$clientSIP->getConfiguration("Password");
			$_sip = new sip($clientSIP->getId(),network ::getNetworkAccess('internal', 'ip', '', false));
			while(true){
				$_sip->setUsername($Username);
				$_sip->setPassword($Password);
				$_sip->newCall();
				$_sip->listen('INVITE');
				$clientSIP->RepondreAppel($_sip);
			}
		}
	}	
	public function RepondreAppel($_sip) {
		$call['status']='ringing'; 
		$call['flow']='incoming';  
		$call['number']='';  
		$call['start']=date('d/m/Y H:i:s');  
		self::addHistoryCall($call);
		//$_sip->reply(100,'Trying');
		$_sip->reply(180,'Ringing');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		event::add('clientSIP::call', utils::o2a($this));
		$CallStatus=$this->getCmd(null,'CallStatus');
		$call['status']= 'ringing';
		while($CallStatus->execCmd() == 'Sonnerie');
		self::addCacheMonitor($call);
		switch($CallStatus->execCmd()){
			case 'Decrocher':
				$call['status']= 'call';
				$this->Decrocher($_sip);
			break;
			case 'Racrocher':
				$call['status']= 'reject';
				$this->Racrocher($_sip);
			return;
		}
		self::addHistoryCall($call);
	}
	public function Decrocher($_sip='') {
		//ajouter les options de compatibilité de jeedom
		$_sip->reply(200,'Ok');
		event::add('clientSIP::rtsp', $_sip->rtsp());
		$this->checkAndUpdateCmd('CallStatus','Décrocher');
		while($CallStatus->execCmd() == 'Decrocher')
			sleep(5);
		$this->Racrocher();
	}
	public function Racrocher($_sip) {
		$Username=$this->getConfiguration("Username");
		$Host=config::byKey('Host', 'clientSIP');
		//$_sip->reply(603,'Decline');
		$_sip->setMethod('CANCEL');
		$_sip->setFrom('sip:'.$Username.'@'.$Host.':'.$Port);
		$_sip->send();
		$this->checkAndUpdateCmd('CallStatus','Racrocher');
	}
	public function call($number) {	
		log::add('clientSIP', 'debug', 'Appel en demandé => ' . $number);
		$Host=config::byKey('Host', 'clientSIP');
		$Port=config::byKey('Port', 'clientSIP');
		$Username=$this->getConfiguration("Username");
		$Password=$this->getConfiguration("Password");
		$this->checkAndUpdateCmd('CallStatus','Racrocher');	
		$_sip = new sip($this->getId(),network ::getNetworkAccess('internal', 'ip', '', false));
		$_sip->setUsername($Username);
		$_sip->setPassword($Password);
		$_sip->newCall();
		$_sip->setFrom('sip:'.$Username.'@'.$Host.':'.$Port);
		$_sip->setUri('sip:'.$number.'@'.$Host.':'.$Port.';transport='.$this->getConfiguration("transport"));
		$_sip->setTo('sip:'.$number.'@'.$Host);
		$_sip->setMethod('INVITE');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		$res=$_sip->send();
		$CallStatus=$this->getCmd(null,'CallStatus');
		while($CallStatus->execCmd() == 'Sonnerie');
		switch($CallStatus->execCmd()){
			case 'Decrocher':
				$call['status']= 'call';
				self::addHistoryCall($call);
				$this->Decrocher($_sip);
				while($CallStatus->execCmd() == 'Decrocher');
				$call['status']= 'close';
				self::addHistoryCall($call);
				$this->Racrocher($_sip);
			break;
			case 'Racrocher':
				$call['status']= 'reject';
				self::addHistoryCall($call);
				$this->Racrocher($_sip);
			return;
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
	public function sendCommand( $id, $type, $option ) {
		log::add('clientSIP', 'debug', 'Lecture : ' . $type . ' ' . $option);
		$playtts = self::byId($id, 'clientSIP');
		if ($type == 'tts') {
			$hash = hash('md5', $option);
			$file = '/tmp/' . $hash . '.mp3';
		} else {
			$file = $option;
		}
		log::add('clientSIP', 'debug', 'File : ' .  $file);
		if ($playtts->getConfiguration('maitreesclave') == 'deporte'){
			$ip=$playtts->getConfiguration('addressip');
			$port=$playtts->getConfiguration('portssh');
			$user=$playtts->getConfiguration('user');
			$pass=$playtts->getConfiguration('password');
			if (!$connection = ssh2_connect($ip,$port)) {
				log::add('clientSIP', 'error', 'connexion SSH KO');
			}else{
				if (!ssh2_auth_password($connection,$user,$pass)){
					log::add('clientSIP', 'error', 'Authentification SSH KO');
				}else{
					log::add('clientSIP', 'debug', 'Commande par SSH');
					if ($type == 'tts') {
						$lang = $playtts->getConfiguration('lang');
						if ($lang == '') {
							$lang == 'fr-FR';
						}
						$pico = ssh2_exec($connection,"pico2wave -l " . $lang . " -w /tmp/voice.wav \"" . $option . "\"");						
						stream_set_blocking($pico, true);
						$result = stream_get_contents($pico);						
						
						$sox = ssh2_exec($connection,"sox /tmp/voice.wav -r 48k " . $file);
						stream_set_blocking($sox, true);
						$result = stream_get_contents($sox);						
					}
					$result = ssh2_exec($connection,'mplayer ' . $playtts->getConfiguration('opt') . ' ' . $file);
					stream_set_blocking($result, true);
					$result = stream_get_contents($result);

					$closesession = ssh2_exec($connection, 'exit');
					stream_set_blocking($closesession, true);
					stream_get_contents($closesession);
				}
			}
		}else {
			if (!file_exists($file)) {
				if ($type == 'tts') {
					$lang = $playtts->getConfiguration('lang');
					if ($lang == '') {
						$lang == 'fr-FR';
					}
					exec("pico2wave -l " . $lang . " -w /tmp/voice.wav \"" . $option . "\"");
					exec("sox /tmp/voice.wav -r 48k " . $file);
				} else {
					log::add('clientSIP', 'error', 'Fichier inexistant');
					return;
				}
			}

			exec('mplayer ' . $playtts->getConfiguration('opt') . ' ' . $file);
		}
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
