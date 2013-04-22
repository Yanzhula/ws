<?php
namespace ws\view;

abstract class HtmlWidget extends HtmlTpl {

    protected $_viewport;

    abstract public function prepare($config=null);
}
?>