<?php
namespace ws\view;

class ViewportHtml extends HtmlTpl implements iViewport {
    
    protected 
            $_title,
            $_meta,
            $_headers = array('Content-Type' => 'text/html; charset=utf-8'),
            $_children,
            $_breadcrumb=array();

    public function setTitle($title){
        $this->_title = $title;
        return $this;
    }
    
    public function getTitle(){
        return $this->_title;
    }
    
    public function setMeta($meta,$content=null){
        if ($content) {
            $this->_meta[$meta] = $content;
        }
        elseif (is_array($meta)) {
            foreach ($meta AS $k=>$v) {
                if (is_array($v) && array_key_exists('name', $v) && array_key_exists('content', $v)) {
                    $this->_meta[$v['name']] = $v['content'];
                }
                else
                    $this->_meta[$k] = $v;
            }
        }
        return $this;
    }
    
    public function setHeaders($name,$content=null){
        if ($content) {
            $this->_headers[$name] = $content;
        }
        elseif (is_array($name)) {
            foreach ($name AS $k=>$v) {
                if (is_array($v) && array_key_exists('name', $v) && array_key_exists('content', $v)) {
                    $this->_headers[$v['name']] = $v['content'];
                }
                else
                    $this->_headers[$k] = $v;
            }
        }
        return $this;
    }
    
    public function getMeta(){
        return $this->_meta;
    }

    public function setChild(HtmlTpl $child) {
        $this->_children[] = $child;
        return $this;
    }
    
    public function widget($widgetName,$config=null){
        $wname = '\app\widget\\'.$widgetName;
        $widget = new $wname($this);
        $widget->prepare($config?$config:$_GET);
        return $widget;
    }
    public function displayChildren() {
        foreach ($this->_children AS $child){
            echo $child;
        }
    }
    
    public function __toString() {
        $this->_headers();
        $str = parent::__toString();
        if (\ws\ws::conf('debug')) {
            $str.= '<!--[Time: '.sprintf('%1.4f', microtime(true) - START_MICROTIME)
                    .'] [Memory: '.memory_get_usage().'] [PHP '.PHP_VERSION.']-->';
        }
        return $str;
    }
    
    protected function _headers(){
        foreach ($this->_headers AS $name => $content) {
            header($name.($content? ': '.$content:''));
        }
    }
}
?>