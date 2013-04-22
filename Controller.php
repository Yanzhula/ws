<?php
namespace ws;
abstract class Controller {
    
    abstract public function indexAction(array $params=null);
    
    protected function _getRequestMethod(){
        return $_SERVER['REQUEST_METHOD'];
    }

    protected function _GET($name=null,$json=false) {
        return $name? (isset($_GET[$name]) ? ($json? json_decode($_GET[$name],true): $_GET[$name]) : null) : $_GET;
    }
    
    protected function _POST($name=null) {
        return $name ? (isset($_POST[$name]) ? $_POST[$name] : null) : $_POST;
    }
    
    protected function _INPUT_DATA($serialized=true) {
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
    
    //@TODO: remove after
    protected function _PUT($json=true) {
        $content = file_get_contents('php://input');
        if ($json) $content = $content ? json_decode ($content) : null;
        return $content;
    }
    protected function _DELETE($json=true) {
        return $this->_PUT($json);
    }
}
?>