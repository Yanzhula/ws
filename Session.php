<?php
namespace ws;

class Session {
    const SESSION_NAMESPACE = 'WS7';
    protected $data;

    public function __construct() {
        if (!session_id()) session_start();
        if (empty($_SESSION[self::SESSION_NAMESPACE])) $_SESSION[self::SESSION_NAMESPACE] = array();
        $this->data = &$_SESSION[self::SESSION_NAMESPACE];
    }

    public function __set($name, $value){
        $this->data[$name] = $value;
    }

    public function __get($name){
        if (array_key_exists($name, $this->data)) return $this->data[$name];
        return null;
    }
}
?>