<?php
namespace ws;

if (version_compare(PHP_VERSION,'5.3', '<')) die('PHP 5.3+ needle! Our version: '.PHP_VERSION);
if (get_magic_quotes_gpc()) die('get_magic_quotes_gpc() -> need turn off');

ws::init();
//---------------------------------------------------------
class ws {
    protected static $_pdo = array();
    protected static $_data = array();
    protected static $_texts=array();
    protected static $_configs;
    
    public static $HOST;
    public static $REQUEST_PATH=array();

    public static function set($name, $data) {
        self::$_data[$name] = $data;
    }
    
    public static function get($name) {
        if (array_key_exists($name, self::$_data))
                return self::$_data[$name];
        return null;
    }

    public static function init() {
        self::$HOST = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : getenv('HTTP_HOST');
        self::$REQUEST_PATH = self::_getRequestPath();
        spl_autoload_register('\ws\ws::loadClass');
    }

    public static function loadClass($className) {
        $className = str_replace(array('/', '\\'), \DIRECTORY_SEPARATOR, $className);
        $load = $className . '.php';
        !file_exists($load) ?: require_once($load);
        return class_exists($className, false);
    }
    
    public static function setConfig(array $config) {
        self::$_configs = $config;
    }
    
    public static function conf($name) {
        if (is_array(self::$_configs) && array_key_exists($name, self::$_configs))
                return self::$_configs[$name];
        return null;
    }
    public static function setPDO(\PDO $PDO, $name='default') {
        self::$_pdo[$name] = $PDO;
    }
    public static function getPDO($name='default') {
        return array_key_exists($name, self::$_pdo)? self::$_pdo[$name]:false;
    }

    public static function setLocation($location='') {
        if (strpos($location,'http')!==0)
                $location = 'http://'.self::$HOST.'/'.$location;
        header('Location: '.$location);
        die();
    }
    
    public static function setTexts(array $texts){
        self::$_texts = $texts;
    }

    public static function text($name) {
        $name=(string)$name;
        if (array_key_exists($name, self::$_texts))
                return self::$_texts[$name];
        return $name;
    }

    protected static function _getRequestPath() {
        $path = array();
        $uri = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
        $parse = parse_url($uri);
        if (!empty($parse['path'])) {
            $parse['path'] = urldecode($parse['path']);
            $path = array_values(array_filter(explode('/', $parse['path'])));
        }
        return implode('/',$path);
    }
}
?>