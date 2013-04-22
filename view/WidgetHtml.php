<?php
namespace ws\view;

abstract class WidgetHtml extends HtmlTpl {
    
    protected $_viewport;

    abstract public function prepare($config=null);

    public function __construct(ViewportHtml $viewport=null) {
        $this->_viewport = $viewport;
    }
    
    public function getViewport() {
        return $this->_viewport;
    }
}
?>