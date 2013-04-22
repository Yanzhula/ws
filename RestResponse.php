<?php

namespace ws;

class RestResponse {
    public $success = false;
    public $error = false;

    public function apply($data){
        foreach ($data AS $k => $v) $this->{$k} = $v;
    }
}
?>