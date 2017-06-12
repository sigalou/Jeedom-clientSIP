<?php

/**
 * Created by PhpStorm.
 * User: Koen Beckers
 * Date: 8-10-2016
 * Time: 17:16
 */
class SIPRequester {
  
  
  /**
   * @var string
   */
  private $server;
  /**
   * Please keep in mind that this port does not get automatically detected.
   *
   * @var integer
   */
  private $port;
  
  /**
   * @var array
   */
  private $valuesToRequest = [];
  
  /**
   * Holds the returned values
   *
   * @var array
   */
  private $returnedMap = [];
  
  /**
   * SIPRequester constructor.
   *
   * @param string $server
   * @param int    $port
   */
  public function __construct($server, $port) {
    
    $this->server = $server;
    $this->port = $port;
  }
  
  
  /**
   * Adds a key to be requested.
   * Does not actually request anything
   *
   * @param $key
   * @param $arguments
   */
  public function addValueToRequest($key, $arguments = NULL) {
    
    if ($arguments === NULL) {
      $this->valuesToRequest[] = $key;
    }
    else {
      $this->valuesToRequest[] = ['key' => $key, 'args' => $arguments];
    }
    
  }
  
  
  /**
   * Actually does the request
   */
  public function doRequest() {
    
    /* Create a TCP/IP socket. */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === FALSE) {
      die("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
    }
    
    $result = socket_connect($socket, $this->server, $this->port);
    if ($result === FALSE) {
      die("socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n");
    }
    
    $toSend = json_encode($this->valuesToRequest) . "\n";
    
    socket_write($socket, $toSend, strlen($toSend));
    
    $return = "";
    while ($packet = socket_read($socket, 2048)) {
      $return .= $packet;
    }
    
    socket_close($socket);
    
    $this->returnedMap = json_decode($return, TRUE);
  }
  
  public function getValue($key) {
    
    if (array_key_exists($key, $this->returnedMap)) {
      return $this->returnedMap[$key];
    }
    else {
      return NULL;
    }
  }
}


?>
