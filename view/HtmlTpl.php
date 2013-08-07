<?php
namespace ws\view;

class HtmlTpl {

    protected $_tpl,
            $_extend,
            $_extendNode,
            $_data=array();

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

    public function siteHref(){
        return 'http://'.\ws\ws::request()->host;
    }

    public function extend($tpl,$node='content'){
        $this->_extend = $tpl;
        $this->_extendNode = $node;
        return $this;
    }

    public function getLayout() {
        return $this->_tpl;
    }

    public function setLayout($layout) {
        $this->_tpl = $layout;
    }

    public function render($layout=null, $data=null) {
        if (!$layout) $layout = $this->getLayout();
        if (!$data) $data = $this->_data;

        $content = $this->renderTpl($layout, $data);

        if ($this->_extend) {
            $data[$this->_extendNode] = $content;
            $extend = new HtmlTpl($this->_extend, $data);
            $content = $extend->render();
        }

        return $content;
    }

    public function renderTpl($layout, $data=null) {
        $content = '';
        if (is_readable('tpls/'.$layout.'.tpl.php')) {
            extract($data);
            ob_start();
            include 'tpls/'.$layout.'.tpl.php';
            $content = ob_get_clean();
        }
        return $content;
    }


    public function __toString() {
        return $this->render();
    }

}
?>