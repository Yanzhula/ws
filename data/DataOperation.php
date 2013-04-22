<?php
namespace ws\data;
class DataOperation {
    const OP_CREATE  = 1;
    const OP_UPDATE  = 2;
    const OP_DESTROY = 3;
    
    protected $_data=array();
    protected $_errors=array();
    protected $_denied = false;
    protected $_success = true;
    
    public function __get($name) {
        return $this->getData($name);
    }

    public function setSuccess($flag=true){
        $this->_success = (bool)$flag;
    }
    
    public function success(){
        return $this->_success;
    }

    public function setDenied($flag=true){
        $this->_denied = (bool)$flag;
        if ($flag) $this->setSuccess(false);
    }
    
    public function setErrors($errors){
        if (!is_array($errors)) $errors = array($errors);
        $this->_errors = array_merge($this->_errors, $errors);
        $this->setSuccess(false);
    }
    
    public function getErrors(){
        return $this->_errors;
    }
    
    public function setData($data){
        if (!is_array($data)) $data = (array)$data;
        $this->_data = array_merge($this->_data,$data);
        return $this;
    }
    public function getData($name=null){
        if ($name===null) return $this->_data;
        if (array_key_exists($name, $this->_data))
                return $this->_data[$name];
        return null;
    }
    
    public function toArray(){
        $this->_data['success'] = $this->success();
        if ($this->_denied) $this->_data['access'] = false;
        if ($this->_errors) $this->_data['errors'] = $this->_errors;
        return $this->_data;
    }
}
?>