<?php
namespace ws\data;

class Validator {

    protected
            $_data=array(),
            $_rules=array(),
            $_validationErrors=array();

    public function __construct(array $data=null, array $rules=null) {
        if ($data) $this->setData($data);
        if ($rules) $this->setRules($rules);
    }

    public function setData(array $data){
        $this->_data = $data;
    }

    public function setRules(array $rules) {
        $this->_rules = $rules;
    }

    protected function _get($name) {
        if (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        }
        return null;
    }

    protected function _validate() {

        $this->_cleanValidationErrors();
        if (empty($this->_rules)) return true;

        foreach ($this->_rules AS $field => $rules) {
            if (!empty($rules['type'])) {
                switch($rules['type']) {
                    case 'length':
                        $l = mb_strlen($this->_get($field));
                        if (!empty($rules['min']) && $l < $rules['min']) {
                            $this->_addValidationError($field, 'length', array(
                                'min' => $rules['min']
                            ));
                        }
                        if (!empty($rules['max']) && $l > $rules['max']) {
                            $this->_addValidationError($field, 'length', array(
                                'max' => $rules['max']
                            ));
                        }
                        unset($l);
                    break;
                    case 'email':
                        if (!preg_match('/[\w\.\-]+@\w+[\w\.\-]*?\.\w{1,4}/',$this->_get($field))) {
                            $this->_addValidationError($field, 'email');
                        }
                    break;
                    case 'matcher':
                        if (!preg_match($rules['matcher'], $this->_get($field))) {
                            $this->_addValidationError($field, 'matcher', array(
                                'regexp' => $rules['matcher']
                            ));
                        }
                    break;
                }
            }
            if (!empty($rules['required'])) {
                if ($this->_get($field)===null) {
                    $this->_addValidationError($field, 'required');
                }
            }
            if (!empty($rules['fn'])) {
                $result = call_user_func($rules['fn'], $this->_get($field));
                if (!$result) {
                    $this->_addValidationError($field, 'fn');
                }
            }
        }
        return !$this->_hasValidationErrors();
    }

    public function isValid() {
        return $this->_validate();
    }

    protected function _cleanValidationErrors() {
        $this->_validationErrors = array();
        return $this;
    }

    protected function _addValidationError($fieldName, $type, array $attr = null) {
        $e = array('field' => $fieldName, 'type' => $type);
        if (is_array($attr)) {
            $e = array_merge($e, $attr);
        }
        $this->_validationErrors[] = $e;
    }

    protected function _hasValidationErrors() {
        return (bool) sizeof($this->_validationErrors);
    }

    public function getErrors() {
        return $this->_validationErrors;
    }
}
?>