<?php
class sip
{
  private $min_port = 5065;
  private $max_port = 5265;
  private $fr_timer = 10000;
  private $lock_file = '/var/www/html/tmp/PhpSIP.lock';
  private $persistent_lock_file = true;
  private $allowed_methods = array(
    "CANCEL","NOTIFY", "INVITE","BYE","REFER","OPTIONS","SUBSCRIBE","MESSAGE", "PUBLISH", "REGISTER"
  );
  private $server;
  private $server_mode = false;
  private $dialog = false;
  private $socket;
  private $src_ip;
  private $user_agent = 'Jeedom';
  private $cseq = 20;
  private $src_port;
  private $call_id;
  private $contact;
  private $uri;
  private $host;
  private $port = 5060;
  private $proxy;
  private $method;
  private $username;
  private $password;
  private $to;
  private $to_tag;
  private $from;
  private $from_user;
  private $from_tag;
  private $via;
  private $content_type;
  private $body;
  private $rx_msg;
  private $res_code;
  private $res_contact;
  private $res_cseq_method;
  private $res_cseq_number;
  private $req_method;
  private $req_cseq_method;
  private $req_cseq_number;
  private $req_contact;
  private $req_from;
  private $req_from_tag;
  private $req_to;
  private $req_to_tag;
  private $rtsp;
  private $auth;
  private $routes = array();
  private $record_route = array();
  private $request_via = array();
  private $extra_headers = array();
  private $jeedomId='';
  public function __construct($jeedomId,$src_ip = null, $src_port = null, $fr_timer = null)
  {
    $this->jeedomId=$jeedomId;
    if (!function_exists('socket_create'))
    {
      log::add('clientSIP','error',"socket_create() function missing.");
      die();
    }
    if ($src_ip)
    {
      if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $src_ip))
      {
        log::add('clientSIP','error',"Invalid src_ip $src_ip");
      die();
      }
    }
    else
    {
      // running in a web server
      if (isset($_SERVER['SERVER_ADDR']))
      {
        $src_ip = $_SERVER['SERVER_ADDR'];
      }
      // running from command line
      else
      {
        $addr = gethostbynamel(php_uname('n'));
        
        if (!is_array($addr) || !isset($addr[0]) || substr($addr[0],0,3) == '127')
        {
          log::add('clientSIP','error',"Failed to obtain IP address to bind. Please set bind address manualy.");
      die();
        }
      
        $src_ip = $addr[0];
      }
    }
    
    $this->src_ip = $src_ip;
    
    if ($src_port)
    {
      if (!preg_match('/^[0-9]+$/',$src_port))
      {
        log::add('clientSIP','error',"Invalid src_port $src_port");
      die();
      }
      
      $this->src_port = $src_port;
      $this->lock_file = null;
    }
    
    if ($fr_timer)
    {
      if (!preg_match('/^[0-9]+$/',$fr_timer))
      {
        log::add('clientSIP','error',"Invalid fr_timer $fr_timer");
      die();
      }
      
      $this->fr_timer = $fr_timer;
    }
    
    $this->createSocket();
  }
  public function __destruct()
  {
    $this->closeSocket();
  }
  public function getSrcIp()
  {
    return $this->src_ip;
  }
  private function getPort()
  {
    if ($this->src_port)
    {
      return true;
    }
    
    if ($this->min_port > $this->max_port)
    {
      log::add('clientSIP','error',"Min port is bigger than max port.");
      die();
    }
    
    $fp = @fopen($this->lock_file, 'a+');
    
    if (!$fp)
    {
     log::add('clientSIP','error',"Failed to open lock file ".$this->lock_file);
      die();
    }
    
    $canWrite = flock($fp, LOCK_EX);
    
    if (!$canWrite)
    {
      log::add('clientSIP','error',"Failed to lock a file in 1000 ms.");
      die();
    }

    clearstatcache();
    $size = filesize($this->lock_file);
    
    if ($size)
    {
      $contents = fread($fp, $size);
      
      $ports = explode(",",$contents);
    }
    else
    {
      $ports = false;
    }
    
    ftruncate($fp, 0);
    rewind($fp);
    
    // we are the first one to run, initialize "PID" => "port number" array
    if (!$ports)
    {
      if (!fwrite($fp, $this->min_port))
      {
        log::add('clientSIP','error',"Fail to write data to a lock file.");
      die();
      }
      
      $this->src_port =  $this->min_port;
    }
    // there are other programs running now
    else
    {
      $src_port = null;
      
      for ($i = $this->min_port; $i <= $this->max_port; $i++)
      {
        if (!in_array($i,$ports))
        {
          $src_port = $i;
          break;
        }
      }
      
      if (!$src_port)
      {
        log::add('clientSIP','error',"No more ports left to bind.");
      die();
      }
      
      $ports[] = $src_port;
      
      if (!fwrite($fp, implode(",",$ports)))
      {
        log::add('clientSIP','error',"Failed to write data to lock file.");
      die();
      }
      
      $this->src_port = $src_port;
    }
    
    if (!fclose($fp))
    {
      log::add('clientSIP','error',"Failed to close lock_file");
      die();
    }
    
  }
  private function releasePort()
  {
    if ($this->lock_file === null)
    {
      return true;
    }
    
    $fp = fopen($this->lock_file, 'r+');
    
    if (!$fp)
    {
      log::add('clientSIP','error',"Can't open lock file.");
      die();
    }
    
    $canWrite = flock($fp, LOCK_EX);
    
    if (!$canWrite)
    {
      log::add('clientSIP','error',"Failed to lock a file in 1000 ms.");
      die();
    }
    
    clearstatcache();
    
    $size = filesize($this->lock_file);
    $content = fread($fp,$size);
    
    //file was locked
    $ports = explode(",",$content);
    
    $key = array_search($this->src_port,$ports);
    
    unset($ports[$key]);
    
    if (!$this->persistent_lock_file && count($ports) === 0)
    {
      if (!fclose($fp))
      {
        log::add('clientSIP','error',"Failed to close lock_file");
      die();
      }
      
      if (!unlink($this->lock_file))
      {
        log::add('clientSIP','error',"Failed to delete lock_file.");
      die();
      }
    }
    else
    {
      ftruncate($fp, 0);
      rewind($fp);
      
      if ($ports && !fwrite($fp, implode(",",$ports)))
      {
        log::add('clientSIP','error',"Failed to save data in lock_file");
      die();
      }
      
      flock($fp, LOCK_UN);
      
      if (!fclose($fp))
      {
        log::add('clientSIP','error',"Failed to close lock_file");
      die();
      }
    }
  }
  public function addHeader($header)
  {
    $this->extra_headers[] = $header;
  }
  public function setFrom($from)
  {
    if (preg_match('/<.*>$/',$from))
    {
      $this->from = $from;
    }
    else
    {
      $this->from = '<'.$from.'>';
    }
    
    $m = array();
    if (!preg_match('/sip:(.*)@/i',$this->from,$m))
    {
      log::add('clientSIP','error','Failed to parse From username.');
      die();
    }
    
    $this->from_user = $m[1];
  }
  public function setTo($to)
  {
    if (preg_match('/<.*>$/',$to))
    {
      $this->to = $to;
    }
    else
    {
      $this->to = '<'.$to.'>';
    }
  }
  public function setMethod($method)
  {
    if (!in_array($method,$this->allowed_methods))
    {
      log::add('clientSIP','error','Invalid method.');
      die();
    }
    
    $this->method = $method;
    
    if ($method == 'INVITE')
    {
      $body = "v=0\r\n";
      $body.= "o=click2dial 0 0 IN IP4 ".$this->src_ip."\r\n";
      $body.= "s=click2dial call\r\n";
      $body.= "c=IN IP4 ".$this->src_ip."\r\n";
      $body.= "t=0 0\r\n";
      $body.= "m=audio 8000 RTP/AVP 0 8 18 3 4 97 98\r\n";
      $body.= "m=audio 14582 RTP/AVP 0 8 3 101\r\n";
      $body.= "a=rtpmap:0 PCMU/8000\r\n";
      $body.= "a=rtpmap:18 G729/8000\r\n";
      $body.= "a=rtpmap:97 ilbc/8000\r\n";
      $body.= "a=rtpmap:98 speex/8000\r\n";
      
      $this->body = $body;
      
      $this->setContentType(null);
    }
    
    if ($method == 'REFER')
    {
      $this->setBody('');
    }
    
    if ($method == 'CANCEL')
    {
      $this->setBody('');
      $this->setContentType(null);
    }
    
    if ($method == 'MESSAGE' && !$this->content_type)
    {
      $this->setContentType(null);
    }
  }
  public function setProxy($proxy)
  {
    $this->proxy = $proxy;
    
    if (strpos($this->proxy,':'))
    {
      $temp = explode(":",$this->proxy);
      
      if (!preg_match('/^[0-9]+$/',$temp[1]))
      {
        log::add('clientSIP','error',"Invalid port number ".$temp[1]);
      die();
      }
      
      $this->host = $temp[0];
      $this->port = $temp[1];
    }
    else
    {
      $this->host = $this->proxy;
    }
  }
  public function setContact($v)
  {
    $this->contact = $v;
  }
  public function setUri($uri)
  {
    if (strpos($uri,'sip:') === false)
    {
      log::add('clientSIP','error',"Only sip: URI supported.");
      die();
    }
    
    if (!$this->proxy && strpos($uri,'transport=tcp') !== false)
    {
      log::add('clientSIP','error',"Only UDP transport supported.");
      die();
    }
    
    $this->uri = $uri;
    
    if (!$this->to)
    {
      $this->to = '<'.$uri.'>';
    }
    
    if ($this->proxy)
    {
      if (strpos($this->proxy,':'))
      {
        $temp = explode(":",$this->proxy);
        
        $this->host = $temp[0];
        $this->port = $temp[1];
      }
      else
      {
        $this->host = $this->proxy;
      }
    }
    else
    {
      $uri = ($t_pos = strpos($uri,";")) ? substr($uri,0,$t_pos) : $uri;
      
      $url = str_replace("sip:","sip://",$uri);
      
      if (!$url = @parse_url($url))
      {
        log::add('clientSIP','error',"Failed to parse URI '$url'.");
      die();
      }
      
      $this->host = $url['host'];
      
      if (isset($url['port']))
      {
        $this->port = $url['port'];
      }
    }
  }
  public function rtsp()
  {
    return $this->rtsp;
  }
  public function setUsername($username)
  {
    $this->username = $username;
  }
  public function setUserAgent($user_agent)
  {
    $this->user_agent = $user_agent;
  }
  public function setPassword($password)
  {
    $this->password = $password;
  }
  public function send()
  {
    if (!$this->from)
    {
      log::add('clientSIP','error','Missing From.');
      die();
    }
    
    if (!$this->method)
    {
      log::add('clientSIP','error','Missing Method.');
      die();
    }
    
    if (!$this->uri)
    {
      log::add('clientSIP','error','Missing URI.');
      die();
    }
    
    $data = $this->formatRequest();
    
    $this->sendData($data);
    
    $this->readMessage();
    
    if ($this->method == 'CANCEL' && $this->res_code == '200')
    {
      $i = 0;
      while (substr($this->res_code,0,1) != '4' && $i < 2)
      {
        $this->readMessage();
        $i++;
      }
    }
    
    if ($this->res_code == '407')
    {
      $this->cseq++;
      
      $this->auth();
      
      $data = $this->formatRequest();
      
      $this->sendData($data);
      
      $this->readMessage();
    }
    
    if ($this->res_code == '401')
    {
      $this->cseq++;
      
      $this->authWWW();
      
      $data = $this->formatRequest();
      
      $this->sendData($data);
      
      $this->readMessage();
    }
    
    if (substr($this->res_code,0,1) == '1')
    {
      $i = 0;
      while (substr($this->res_code,0,1) == '1' && $i < 4)
      {
        $this->readMessage();
        $i++;
      }
    }
    
    $this->extra_headers = array();
    $this->cseq++;
    
    return $this->res_code;
  }
  private function sendData($data)
  {
    if (!$this->host)
    {
      log::add('clientSIP','error',"Can't send data, host undefined");
      die();
    }
    
    if (!$this->port)
    {
      log::add('clientSIP','error',"Can't send data, host undefined");
      die();
    }
    
    if (!$data)
    {
      log::add('clientSIP','error',"Can't send - empty data");
      die();
    }
    
    if (preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $this->host))
    {
      $ip_address = $this->host;
    }
    else
    {
      $ip_address = gethostbyname($this->host);
      
      if ($ip_address == $this->host)
      {
        log::add('clientSIP','error',"DNS resolution of ".$this->host." failed");
      die();
      }
    }
    
    if (!@socket_sendto($this->socket, $data, strlen($data), 0, $ip_address, $this->port))
    {
      $err_no = socket_last_error($this->socket);
      log::add('clientSIP','error',"Failed to send data to ".$ip_address.":".$this->port.". Source IP ".$this->src_ip.", source port: ".$this->src_port.". ".socket_strerror($err_no));
      die();
    }
    
    log::add('clientSIP','info','TX : '.$data);
      
  }
  public function listen($methods)
  { 
    if (!is_array($methods))
    {
      $methods = array($methods);
    }
    
    log::add('clientSIP','debug',"Listenning for ".implode(", ",$methods));
    
    
    if ($this->server_mode)
    {
      while (!in_array($this->req_method, $methods))
      {
        $this->readMessage(); 
        
        if ($this->rx_msg && !in_array($this->req_method, $methods))
        {
          $this->reply(200,'OK');
        }
      }
    }
    else
    {
      $i = 0;
      $this->req_method = null;
    
      while (!in_array($this->req_method, $methods))
      {
        $this->readMessage(); 
        
        $i++;
        
        if ($i > 5)
        {
          log::add('clientSIP','error',"Unexpected request ".$this->req_method." received.");
      die();
        }
      }
    }
  }
  
  /**
   * Sets server mode
   * 
   * @param bool $status
   */
  public function setServerMode($v)
  {
    if (!@socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0,"usec"=>0)))
    {
      $err_no = socket_last_error($this->socket);
      log::add('clientSIP','error',socket_strerror($err_no));
      die();
    }
    
    $this->server_mode = $v;
  }
  private function readMessage()
  {
    $from = "";
    $port = 0;
    $this->rx_msg = null;
    
    if (!@socket_recvfrom($this->socket, $this->rx_msg, 10000, 0, $from, $port))
    {
      $this->res_code = "No final response in ".round($this->fr_timer/1000,3)." seconds. (".socket_last_error($this->socket).")";
      die();
      return $this->res_code;
    }
    
    log::add('clientSIP','info','RX: '.$this->rx_msg);
    
    // Response
    $m = array();
    if (preg_match('/^SIP\/2\.0 ([0-9]{3})/', $this->rx_msg, $m))
    {
      $this->res_code = trim($m[1]);
      
      $this->parseResponse();
    }
    // Request
    else
    {
      $this->parseRequest();
    }
    // $this->actionResCode();
  }

	private function actionResCode(){
		$client=eqLogic::byId($this->jeedomId);
		if(is_object($client)){
			switch($this->res_code){
				case 100:
					$client->checkAndUpdateCmd('CallStatus','Appel en cours');
					$this->reply(100,'Trying');
				break;
				case 180:
					$client->checkAndUpdateCmd('CallStatus','Sonnerie');
					$this->reply(180,'Ringing');
				break;
				case '200':
					$client->checkAndUpdateCmd('CallStatus','Décroché');
					$this->reply(200,'OK');
				break;
				case '486':
					$client->checkAndUpdateCmd('CallStatus','Décroché');
				break;
			}
		}
	}
  private function parseResponse()
  {
    // Request via
    $m = array();
    $this->req_via = array();
    
    if (preg_match_all('/^Via: (.*)$/im', $this->rx_msg, $m))
    {
      foreach ($m[1] as $via)
      {
        $this->req_via[] = trim($via);
      }
    }

    // Routes
    $this->parseRecordRoute();
    
    // To tag
    $m = array();
    if (preg_match('/^To: .*;tag=(.*)$/im', $this->rx_msg, $m))
    {
      $this->to_tag = trim($m[1]);
    }
    
    // Server Name
    $m = array(); 
    if (preg_match('/^Server: (.*)/im', $this->rx_msg, $m))
    {
      $this->server = trim($m[1]);
    }
    // Response contact
    $this->res_contact = $this->parseContact();
    
    // Response CSeq method
    $this->res_cseq_method = $this->parseCSeqMethod();
    
    // ACK 2XX-6XX - only invites - RFC3261 17.1.2.1
    if ($this->res_cseq_method == 'INVITE' && in_array(substr($this->res_code,0,1),array('2','3','4','5','6')))
    {
      $this->ack();
    }
    
    return $this->res_code;
  }
  private function parseRequest()
  {
    $temp = explode("\r\n",$this->rx_msg);
    $temp = explode(" ",$temp[0]);
    
    $this->req_method = trim($temp[0]);
    
    // Routes
    $this->parseRecordRoute();
    
    // Request via
    $m = array();
    $this->req_via = array();
    if (preg_match_all('/^Via: (.*)$/im',$this->rx_msg,$m))
    {
      if ($this->server_mode)
      {
        // set $this->host to top most via
        $m2 = array();
        if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/',$m[1][0],$m2))
        {
          $this->host = $m2[0];
        }
      }
      
      foreach ($m[1] as $via)
      {
        $this->req_via[] = trim($via);
      }
    }
    
    // Request contact
    $this->req_contact = $this->parseContact();
    
    // Request CSeq method
    $this->req_cseq_method = $this->parseCSeqMethod();

    // Request CSeq number
    $m = array(); 
    if (preg_match('/^CSeq: ([0-9]+)/im', $this->rx_msg, $m))
    {
      $this->req_cseq_number = trim($m[1]);
    }
    // Server Name
    $m = array(); 
    if (preg_match('/^Server: (.*)/im', $this->rx_msg, $m))
    {
      $this->server = trim($m[1]);
    }
    // Request From
    $m = array();
    if (preg_match('/^From: (.*)/im', $this->rx_msg, $m))
    {
      $this->req_from = (strpos($m[1],';')) ? substr($m[1],0,strpos($m[1],';')) : $m[1];
    }
    
    // Request From tag
    $m = array();
    if (preg_match('/^From:.*;tag=(.*)$/im', $this->rx_msg, $m))
    {
      $this->req_from_tag = trim($m[1]);
    }
    
    // Request To
    $m = array();
    if (preg_match('/^To: (.*)/im', $this->rx_msg, $m))
    {
      $this->req_to = (strpos($m[1],';')) ? substr($m[1],0,strpos($m[1],';')) : $m[1];
    }
    
    // Request To tag
    $m = array();
    if (preg_match('/^To:.*;tag=(.*)$/im', $this->rx_msg, $m))
    {
      $this->req_to_tag = trim($m[1]);
    }
    else
    {
      $this->req_to_tag = rand(10000,99999);
    }
    
    // Call-id
    if (!$this->call_id)
    {
      $m = array();
      if (preg_match('/^Call-ID:(.*)$/im', $this->rx_msg, $m))
      {
        $this->call_id = trim($m[1]);
      }
    }
    //RTSP information
    $this->rtsp = $this->parseRtsp();
  }
  private function parseRtsp(){
    $rtsp=substr($this->rx_msg,stripos($this->rx_msg,"Content-Length"));
    $rtsp=substr($rtsp,stripos($rtsp,"\n")+1);
    return $rtsp;
  }
  public function reply($code, $text)
  {
    $r = 'SIP/2.0 '.$code.' '.$text."\r\n";
    
    // Via
    foreach ($this->req_via as $via)
    {
      $r.= 'Via: '.$via."\r\n";
    }
    
    // Record-route
    foreach ($this->record_route as $record_route)
    {
      $r.= 'Record-Route: '.$record_route."\r\n";
    }
    
    // From
    $r.= 'From: '.$this->req_from.';tag='.$this->req_from_tag."\r\n";
    
    // To
    $r.= 'To: '.$this->req_to.';tag='.$this->req_to_tag."\r\n";
    
    // Call-ID
    $r.= 'Call-ID: '.$this->call_id."\r\n";
    
    //CSeq
    $r.= 'CSeq: '.$this->req_cseq_number.' '.$this->req_cseq_method."\r\n";
    
    //Server
    if($this->server)
    {
      $r.= 'Server: '.$this->server."\r\n";
    }
    
    $r.= "Allow: INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, SUBSCRIBE, NOTIFY, INFO, PUBLISH, MESSAGE\r\n";
    $r.= "Supported: replaces, timer\r\n";
    
    // Max-Forwards
    //$r.= 'Max-Forwards: 70'."\r\n";
     // Contact
    if ($this->contact)
    {
      if (substr($this->contact,0,1) == "<") {
        $r.= 'Contact: '.$this->contact."\r\n";
      } else {
        $r.= 'Contact: <'.$this->contact.'>'."\r\n";
      }
    }
    else if ($this->method != 'MESSAGE')
    {
      $r.= 'Contact: <sip:'.$this->from_user.'@'.$this->src_ip.':'.$this->src_port.'>'."\r\n";
    }
    // User-Agent
    $r.= 'User-Agent: '.$this->user_agent."\r\n";
    
    // Content-Length
    $r.= 'Content-Length: 0'."\r\n";
    $r.= "\r\n";
    
    $this->sendData($r);
  }
  private function ack()
  {
    if ($this->res_cseq_method == 'INVITE' && $this->res_code == '200')
    {
      $a = 'ACK '.$this->res_contact.' SIP/2.0'."\r\n";
    }
    else
    {
      $a = 'ACK '.$this->uri.' SIP/2.0'."\r\n";
    }
    
    // Via
    $a.= 'Via: '.$this->via."\r\n";
    
    // Route
    if ($this->routes)
    {
      $a.= 'Route: '.implode(",",array_reverse($this->routes))."\r\n";
    }
    
    // From 
    if (!$this->from_tag)
    {
      $this->from_tag = rand(10000,99999);
    }
    
    $a.= 'From: '.$this->from.';tag='.$this->from_tag."\r\n";
    
    // To
    if ($this->to_tag)
    {
      $a.= 'To: '.$this->to.';tag='.$this->to_tag."\r\n";
    }
    else
    {
      $a.= 'To: '.$this->to."\r\n";
    }
    //Server
    if($this->server)
    {
      $a.= 'Server: '.$this->server."\r\n";
    }
    // Call-ID
    if (!$this->call_id)
    {
      $this->setCallId();
    }
    
    $a.= 'Call-ID: '.$this->call_id."\r\n";
    
    //CSeq
    $a.= 'CSeq: '.$this->cseq.' ACK'."\r\n";
    
    // Authentication
    if ($this->res_code == '200' && $this->auth)
    {
      $a.= 'Proxy-Authorization: '.$this->auth."\r\n";
    }
    
    // Max-Forwards
    $a.= 'Max-Forwards: 70'."\r\n";
    
    // User-Agent
    $a.= 'User-Agent: '.$this->user_agent."\r\n";
    
    // Content-Length
    $a.= 'Content-Length: 0'."\r\n";
    $a.= "\r\n";
    
    $this->sendData($a);
  }
  private function formatRequest()
  {
    if ($this->res_contact && in_array($this->method,array('BYE','REFER','SUBSCRIBE')))
    {
      $r = $this->method.' '.$this->res_contact.' SIP/2.0'."\r\n";
    }
    else
    {
      $r = $this->method.' '.$this->uri.' SIP/2.0'."\r\n";
    }
    
    // Via
    if ($this->method != 'CANCEL')
    {
      $this->setVia();
    }
    
    $r.= 'Via: '.$this->via."\r\n";
    
    // Route
    if ($this->method != 'CANCEL' && $this->routes)
    {
      $r.= 'Route: '.implode(",",array_reverse($this->routes))."\r\n";
    }
    
    // From
    if (!$this->from_tag)
    {
      $this->from_tag = rand(10000,99999);
    }
    
    $r.= 'From: '.$this->from.';tag='.$this->from_tag."\r\n";
    
    // To
    if ($this->to_tag && !in_array($this->method,array("INVITE","CANCEL","NOTIFY","REGISTER")))
    {
      $r.= 'To: '.$this->to.';tag='.$this->to_tag."\r\n";
    }
    else
    {
      $r.= 'To: '.$this->to."\r\n";
    }
    
    //Server
    if($this->server)
    {
      $r.= 'Server: '.$this->server."\r\n";
    }
    // Authentication
    if ($this->auth)
    {
      $r.= $this->auth."\r\n";
      $this->auth = null;
    }
    
    // Call-ID
    if (!$this->call_id)
    {
      $this->setCallId();
    }
    
    $r.= 'Call-ID: '.$this->call_id."\r\n";
    
    //CSeq
    if ($this->method == 'CANCEL')
    {
      $this->cseq--;
    }
    
    $r.= 'CSeq: '.$this->cseq.' '.$this->method."\r\n";
    
    // Contact
    if ($this->contact)
    {
      if (substr($this->contact,0,1) == "<") {
        $r.= 'Contact: '.$this->contact."\r\n";
      } else {
        $r.= 'Contact: <'.$this->contact.'>'."\r\n";
      }
    }
    else if ($this->method != 'MESSAGE')
    {
      $r.= 'Contact: <sip:'.$this->from_user.'@'.$this->src_ip.':'.$this->src_port.'>'."\r\n";
    }
    
    // Content-Type
    if ($this->content_type)
    {
      $r.= 'Content-Type: '.$this->content_type."\r\n";
    }
    
    // Max-Forwards
    $r.= 'Max-Forwards: 70'."\r\n";
    
    // User-Agent
    $r.= 'User-Agent: '.$this->user_agent."\r\n";
    
    // Additional header
    foreach ($this->extra_headers as $header)
    {
      $r.= $header."\r\n";
    }
    
    // Content-Length
    $r.= 'Content-Length: '.strlen($this->body)."\r\n";
    $r.= "\r\n";
    $r.= $this->body;
    
    return $r;
  }
  public function setBody($body)
  {
    $this->body = $body;
  }
  public function setContentType($content_type = null)
  {
    if ($content_type !== null)
    {
      $this->content_type = $content_type;
    }
    else
    {
      switch ($this->method)
      {
        case 'INVITE':
          $this->content_type = 'application/sdp';
          break;
        case 'MESSAGE':
          $this->content_type = 'text/html; charset=utf-8';
          break;
        default:
          $this->content_type = null;
      }
    }
  }
  private function setVia()
  {
    $rand = rand(100000,999999);
    $this->via = 'SIP/2.0/UDP '.$this->src_ip.':'.$this->src_port.';rport;branch=z9hG4bK'.$rand;
  }
  public function setFromTag($v)
  {
    $this->from_tag = $v;
  }
  public function setToTag($v)
  {
    $this->to_tag = $v;
  }
  public function setCseq($v)
  { 
    $this->cseq = $v;
  }
  public function setCallId($v = null)
  {
    if ($v)
    {
      $this->call_id = $v;
    }
    else
    {
      $this->call_id = md5(uniqid()).'@'.$this->src_ip;
    }
  }
  public function getHeader($name)
  {
    $m = array();
    
    if (preg_match('/^'.$name.': (.*)$/im', $this->rx_msg, $m))
    {
      return trim($m[1]);
    }
    else
    {
      return false;
    }
  }
  public function getBody()
  {
    $temp = explode("\r\n\r\n",$this->rx_msg);
    
    if (!isset($temp[1]))
    {
      return '';
    }
    
    return $temp[1];
  }
  private function auth()
  {
    if (!$this->username)
    {
      log::add('clientSIP','error',"Missing username");
      die();
    }
    
    if (!$this->password)
    {
      log::add('clientSIP','error',"Missing password");
      die();
    }
    
    // realm
    $m = array();
    if (!preg_match('/^Proxy-Authenticate: .* realm="(.*)"/imU',$this->rx_msg, $m))
    {
      log::add('clientSIP','error',"Can't find realm in proxy-auth");
      die();
    }
    
    $realm = $m[1];
    
    // nonce
    $m = array();
    if (!preg_match('/^Proxy-Authenticate: .* nonce="(.*)"/imU',$this->rx_msg, $m))
    {
      log::add('clientSIP','error',"Can't find nonce in proxy-auth");
      die();
    }
    
    $nonce = $m[1];
    
    $ha1 = md5($this->username.':'.$realm.':'.$this->password);
    $ha2 = md5($this->method.':'.$this->uri);
    
    $res = md5($ha1.':'.$nonce.':'.$ha2);
    
    $this->auth = 'Proxy-Authorization: Digest username="'.$this->username.'", realm="'.$realm.'", nonce="'.$nonce.'", uri="'.$this->uri.'", response="'.$res.'", algorithm=MD5';
  }
  private function authWWW()
  {
    if (!$this->username)
    {
      log::add('clientSIP','error',"Missing auth username");
      die();
    }
    
    if (!$this->password)
    {
      log::add('clientSIP','error',"Missing auth password");
      die();
    }
    
    $qop_present = false;
    if (strpos($this->rx_msg,'qop=') !== false)
    {
      $qop_present = true;
      
      // we can only do qop="auth"
      if  (strpos($this->rx_msg,'qop="auth"') === false)
      {
        log::add('clientSIP','error','Only qop="auth" digest authentication supported.');
      die();
      }
    }
    
    // realm
    $m = array();
    if (!preg_match('/^WWW-Authenticate: .* realm="(.*)"/imU',$this->rx_msg, $m))
    {
      log::add('clientSIP','error',"Can't find realm in www-auth");
      die();
    }
    
    $realm = $m[1];
    
    // nonce
    $m = array();
    if (!preg_match('/^WWW-Authenticate: .* nonce="(.*)"/imU',$this->rx_msg, $m))
    {
      log::add('clientSIP','error',"Can't find nonce in www-auth");
      die();
    }
    
    $nonce = $m[1];
    
    $ha1 = md5($this->username.':'.$realm.':'.$this->password);
    $ha2 = md5($this->method.':'.$this->uri);
    
    if ($qop_present)
    {
      $cnonce = md5(time());
      
      $res = md5($ha1.':'.$nonce.':00000001:'.$cnonce.':auth:'.$ha2);
    }
    else
    {
      $res = md5($ha1.':'.$nonce.':'.$ha2);
    }
    
    $this->auth = 'Authorization: Digest username="'.$this->username.'", realm="'.$realm.'", nonce="'.$nonce.'", uri="'.$this->uri.'", response="'.$res.'", algorithm=MD5';
    
    if ($qop_present)
    {
      $this->auth.= ', qop="auth", nc="00000001", cnonce="'.$cnonce.'"';
    }
  }
  private function createSocket()
  { 
    $this->getPort();
    
    if (!$this->src_ip)
    {
      log::add('clientSIP','error',"Source IP not defined.");
      die();
    }
    
    if (!$this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))
    {
      $err_no = socket_last_error($this->socket);
      log::add('clientSIP','error',socket_strerror($err_no));
      die();
    }
    
    if (!@socket_bind($this->socket, $this->src_ip, $this->src_port))
    {
      $err_no = socket_last_error($this->socket);
      log::add('clientSIP','error',"Failed to bind ".$this->src_ip.":".$this->src_port." ".socket_strerror($err_no));
      die();
    }
    
    $microseconds = $this->fr_timer * 1000;
    
    $usec = $microseconds % 1000000;
    
    $sec = floor($microseconds / 1000000);
    
    if (!@socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>$sec,"usec"=>$usec)))
    {
      $err_no = socket_last_error($this->socket);
      log::add('clientSIP','error',socket_strerror($err_no));
      die();
    }
    
    if (!@socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>5,"usec"=>0)))
    {
      $err_no = socket_last_error($this->socket);
      log::add('clientSIP','error',socket_strerror($err_no));
      die();
    }
  }
  private function closeSocket()
  {
    socket_close($this->socket);
    
    $this->releasePort();
  }
  public function newCall()
  {
    $this->cseq = 20;
    $this->call_id = null;
    $this->to = null;
    $this->to_tag = null;
    $this->from = null;
    $this->from_tag = null;
    
    /**
     * Body
     */
    $this->body = null;
    
    /**
     * Received Response
     */
    $this->rx_msg = null;
    $this->res_code = null;
    $this->res_contact = null;
    $this->res_cseq_method = null;
    $this->res_cseq_number = null;

    /**
     * Received Request
     */
    $this->req_via = array();
    $this->req_method = null;
    $this->req_cseq_method = null;
    $this->req_cseq_number = null;
    $this->req_contact = null;
    $this->req_from = null;
    $this->req_from_tag = null;
    $this->req_to = null;
    $this->req_to_tag = null;
    
    $this->routes = array();
  }
  private function parseRecordRoute()
  {
    $this->record_route = array();
    
    $m = array();
    
    if (preg_match_all('/^Record-Route: (.*)$/im', $this->rx_msg, $m))
    {
      foreach ($m[1] as $route_header)
      {
        $this->record_route[] = $route_header;
        
        foreach (explode(",",$route_header) as $route)
        {
          if (!in_array(trim($route), $this->routes))
          {
            $this->routes[] = trim($route);
          }
        }
      }
    }
  }
  private function parseContact()
  {
    $output = null;
    
    $m = array();
    
    if (preg_match('/^Contact:.*<(.*)>/im', $this->rx_msg, $m))
    {
      $output = trim($m[1]);
      
      $semicolon = strpos($output, ";");
      
      if ($semicolon !== false)
      {
        $output = substr($output, 0, $semicolon);
      }
    }
    
    return $output;
  }
  private function parseCSeqMethod()
  {
    $output = null;
    
    $m = array();
    
    if (preg_match('/^CSeq: [0-9]+ (.*)$/im', $this->rx_msg, $m))
    {
      $output = trim($m[1]);
    }
    
    return $output;
  }
}
?>
