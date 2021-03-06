<?php
namespace ws;

if (version_compare(PHP_VERSION,'5.3', '<')) die('PHP 5.3+ needle! Our version: '.PHP_VERSION);
if (get_magic_quotes_gpc()) die('get_magic_quotes_gpc() -> need turn off');
spl_autoload_register('\ws\ws::loadClass');

class ws {
    const VERSION = '7';
    protected static $_data = array();
    protected static $_app;
    protected static $_request;

    public static function app(){
        if (!self::$_app)
            self::$_app = new \app\App();
        return self::$_app;
    }

    public static function request(){
        if (!self::$_request)
            self::$_request = new Request();
        return self::$_request;
    }

    public static function set($name, $data=null) {
        if ($data===null && is_array($name)) {
            foreach ($name AS $key => $value){
                self::$_data[$key] = $value;
            }
        }
        else {
            self::$_data[$name] = $data;
        }
    }

    public static function get($name) {
        if (array_key_exists($name, self::$_data))
                return self::$_data[$name];
        return null;
    }

    public static function loadClass($className) {
        $className = str_replace(array('/', '\\'), \DIRECTORY_SEPARATOR, $className);
        $load = ROOT_DIR.DIRECTORY_SEPARATOR.$className . '.php';
        !file_exists($load) ?: require_once($load);
        return class_exists($className, false);
    }
}
?>