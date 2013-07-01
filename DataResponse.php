<?php

namespace ws;

class DataResponse {
    public $success = false;
    public $message;

    public function __construct($flagSuccess=null) {
        if ($flagSuccess!==null) {
            $this->success = (bool)$flagSuccess;
        }
    }

    public function apply($data){
        foreach ($data AS $k => $v) $this->{$k} = $v;
    }

    public function __toString() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, X-File-Name, X-File-Size, X-File-Type');

        return json_encode($this);
    }
}
?>