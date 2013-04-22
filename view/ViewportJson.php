<?php
namespace ws\view;

class ViewportJson implements iViewport{
    
    protected $_data=array('success'=>false);
    
    public function setData($data){
        if (!is_array($data)) $data = (array)$data;
        $this->_data = array_merge($this->_data,$data);
        return $this;
    }

    public function __toString(){
        if (\ws\ws::conf('debug')) {
            $this->setData(array('ws'=>array(
                'time' => sprintf('%1.4f', microtime(true) - START_MICROTIME),
                'memory' => memory_get_usage() - START_MEMORY_USAGE,
                'php' => PHP_VERSION
            )));
        }
        
        return json_encode($this->_data);
    }
    
    protected function _jsonHeaders() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, X-File-Name');
    }
}
?>