<?php
namespace ws\view;

abstract class HtmlWidget extends HtmlTpl {
    abstract public function prepare($config=null);
}
?>