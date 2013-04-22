<?php
namespace ws\view;

class HtmlTpl {
    
    protected $_tpl;
    protected $_data=array();

    public function __construct($tpl=null, $data=null) {
        if ($tpl) $this->setLayout($tpl);
        if ($data) $this->set($data);
    }
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    public function __get($name) {
        return $this->get($name);
    }

    public function set($name, $value=null) {
        if (is_array($name)) {
            $this->_data = array_merge($this->_data, $name);
        }
        else $this->_data[$name] = $value;
    }
    public function get($name) {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    public function getLayout() {
        return $this->_tpl;
    }

    public function setLayout($layout) {
        $this->_tpl = $layout;
    }
    
    public function render() {
        if (!$this->getLayout()) return '';
        extract($this->_data);
        ob_start();
        include 'tpls/'.$this->getLayout() .'.tpl.php';
        return ob_get_clean();
    }

    public function __toString() {
        return $this->render();
    }    
    
}
?>