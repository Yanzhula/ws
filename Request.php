<?php
namespace ws;

class Request {
    public $host;
    public $path=array();

    public function __construct() {
        $this->HOST = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : getenv('HTTP_HOST');
        $this->REQUEST_PATH = $this::_getRequestPath();
    }

    public function setLocation($location='') {
        if (strpos($location,'http')!==0)
                $location = 'http://'.self::$HOST.'/'.$location;
        header('Location: '.$location);
        die();
    }

    protected function _getRequestPath() {
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