<?php

class sip {
	public $to=NULL;
	public $from=NULL;
	public $protocol=NULL;
	public $serverIP=NULL;
	public $serverHostname=NULL;
	public $clientHostname=NULL;
	public $clientPort=NULL;
	private $branch=NULL;
	private $connected=FALSE;
	private $socket=NULL;
	private $userAgent = "FIP/0.1";
	private $commands = array("INVITE","ACK","OPTIONS","BYE","CANCEL","SUBSCRIBE","NOTIFY","REFER","MESSAGE","INFO","PING");

	function __construct($from, $serverHostname, $protocol="udp", $port=NULL) {

		$protocol = strtolower($protocol);

		if ($protocol !== "udp" && $protocol !== "tcp") {

			$error = "Protocole non supporté";

			log::add('clientSIP','error',$error);

			throw new Exception($error);

		} else
			$this->protocol = $protocol;

		if (!is_null($port) && !ctype_digit($port)) {			
			$error = "Port spécifié invalide";
			log::add('clientSIP','error',$error);

			throw new Exception($error);

		} else
			$this->port = getservbyname('sip', $this->protocol);

		$this->serverHostname = $serverHostname;

		if (ip2long($this->serverHostname) === FALSE) {

			$serverIP = gethostbyname($this->serverHostname);

			if ($serverIP == $this->serverHostname) {

				$error = "Impossible de se connecter";
				log::add('clientSIP','error',$error);

				throw new Exception($error);

			}
		} else
			$serverIP = $this->serverHostname;

		$this->from = $from;
		$this->serverIP = $serverIP;
		$this->clientHostname = exec("/bin/hostname");

	}

	function __destruct() {
		$this->close();
	}

	private function getBranch() {

		if (is_null($this->branch)) {

			$branch = md5(time);

			// The magic cookie z9hG4bK is prepended to the branch
			// as per RFC 3261
			$this->branch="z9hG4bK$branch";

		}

		return $this->branch;

	}

	public function set($key, $value) {
		$this->key=$value;
	}

	public function get($key) {
		return $this->key;
	}

	public function register() {

		$this->connect();

		$from = $this->from;
		$branch = $this->getBranch();
		$clientPort = $this->clientPort;
		$serverHostname = $this->serverHostname;
		$clientHostname = $this->clientHostname;
		$allowedCommands= $this->getAllowedCommands();
		
		$contents[] = "REGISTER sip:$serverHostname SIP/2.0";
		$contents[] = "CSeq: 1 REGISTER";
		$contents[] = "Via: SIP/2.0/UDP $clientHostname:$clientPort;branch=$branch;rport";
		$contents[] = "User-Agent: ".$this->userAgent;
		$contents[] = "From: <sip:$from@$clientHostname>";
		$contents[] = "Call-ID: $branch@$clientHostname";
		$contents[] = "To: <sip:$from@$serverHostname>";
		$contents[] = "Contact: <sip:$from@$clientHostname:$clientPort>;q=1";
		$contents[] = "Allow: $allowedCommands";
		$contents[] = "Expires: 3600";
		$contents[] = "Content-Length: 0";
		$contents[] = "Max-Forwards: 70";

		return $this->send(implode("\r\n", $contents));
	}
	private function send($content) {
		$this->connect();
		socket_write($this->socket, $content, strlen($content));
		$result=$this->read();
		log::add('clientSIP','debug',"Result : ".$result);
		switch($result){
			case '200':
				return true;
			default:
				return false;
		}
	}
	private function connect() {

		if ($this->connected)
			return TRUE;

		$this->createSocket();
		log::add('clientSIP','debug',"Attente de la connexion au serveur ".$this->serverIP.":".$this->port);

		$result = socket_connect($this->socket, $this->serverIP, $this->port);

		if ($result === FALSE) {

			$error = "socket_connect() failed. Reason: ($result) " . socket_strerror(socket_last_error($this->socket)) . "\n";
			log::add('clientSIP','error',$error);

			throw new Exception($error);

		}

		$this->connected = TRUE;

	}

	private function createSocket() {

		if (is_resource($this->socket))
			return TRUE;

		if ($this->protocol == "tcp")
			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		else
			$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

		if ($socket === FALSE) {

			$error = "socket_create() failed: reason: " . socket_strerror(socket_last_error());
			log::add('clientSIP','error',$error);

			throw new Exception($error);

		}

		$this->socket = $socket;

		$returnValue = socket_getsockname($this->socket, $ip, $clientPort);

		if ($returnValue === TRUE)
			$this->clientPort = $clientPort;

		else
			log::add('clientSIP','debug',"Impossible de trouver le port du client");

		return TRUE;

	}

	public function read() {

		if (!is_resource($this->socket)) {
			$error = "Accune connexion initialisé";
			log::add('clientSIP','error',$error);
			throw new Exception($error);

		}

		while ($out = socket_read($this->socket, 2048))
			$returnValue .= $out;

		return $returnValue;

	}

	public function close() {

		if (!is_resource($this->socket))
			return FALSE;
		log::add('clientSIP','debug',"Fermeture de la connexion");

		$returnValue = socket_close($this->socket);
		log::add('clientSIP','debug',"Connexion fermée.");

	}

	private function getAllowedCommands() {
		return implode($this->commands, ",");
	}
}
?>
