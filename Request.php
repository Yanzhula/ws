<?php
namespace ws;

class Request {
    public $host,$path, $method, $isXhr;

    public function __construct() {
        $this->host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : getenv('HTTP_HOST');
        $this->path = $this->_getRequestPath();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->isXhr = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }

    public function get($name=null,$json=false) {
        return $name? (isset($_GET[$name]) ? ($json? json_decode($_GET[$name],true): $_GET[$name]) : null) : $_GET;
    }

    public function post($name=null) {
        return $name ? (isset($_POST[$name]) ? $_POST[$name] : null) : $_POST;
    }

    public function put($serialized=true) {
        if ($_POST) return $_POST;
        $content = file_get_contents('php://input');
        if ($serialized&&$content) {
            $copy = $content;
            $content = json_decode ($content, true);
            if (!$content)
                parse_str($copy,$content);
        }
        return $content;
    }

    protected function delete() {
        return $this->put(true);
    }

    public function setLocation($location='') {
        if (strpos($location,'http')!==0) {
            $location = 'http://'.$this->host.'/'.$location;
        }
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