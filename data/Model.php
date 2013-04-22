<?php
namespace ws\data;

class Model {
    
    protected static
        $_idProperty = 'id',
        $_fields,
        $_proxyConfig=array(),

        $_associations=array();
    
    protected $_modifiedFields;
    /**
     * @var Proxy
     */
    private static $_proxy;
    
    public static function load($id) {
        $instance=$data=null;
        $data = static::getProxy()->selectById($id);
        if ($data){
            $instance = new static($data);
        }
        return $instance;
    }
    
    public static function loadBy($where) {
        $instance=$data=null;
        $data = static::getProxy()->selectBy($where);
        if ($data){
            $instance = new static($data);
        }
        return $instance;
    }
    /**
     * @TODO fireEvent = create
     * @param array $data
     * @return Model
     */
    
    public static function create(array $data=null){
        return new static($data);
    }
    
    /**
     * 
     * @return iProxy
     */
    public static function getProxy() {
        $thisClass = get_called_class();
        if (empty(self::$_proxy[$thisClass])) {
            $config = static::$_proxyConfig;
            $proxyType = $config['type'];
            unset($config['type']);
            $config['idProperty'] = static::getIdProperty();
            self::$_proxy[$thisClass] = new $proxyType($config);
        }
        return self::$_proxy[$thisClass];
    }

    public static function getIdProperty() {
        return static::$_idProperty;
    }    
    
    public static function getFields(){
        return static::$_fields;
    }
    
    public static function makeStore() {
        return new Store(array(
            'model' => get_called_class()
        ));
    }
    
    public function __construct($data=null) {
        if ($data) $this->set($data);
        if ($this->getId()) $this->_setModified(false);
    }
    
    public function __set($name, $value) {
        return $this->set($name,$value);
    }
    
    public function __get($name) {
        return $this->get($name);
    }
    
    public function getId() {
        return $this->get(static::getIdProperty());
    }
    
    public function setId($id) {
        if ($id) $this->set(static::getIdProperty(),$id);
        return $this;
    }
    
    public function isModified($field=null) {
        return $field? 
            is_array($this->_modifiedFields) && array_key_exists($field, $this->_modifiedFields)
            : !empty($this->_modifiedFields);
    }
    
    public function set($name, $value=null) {
        $vals = is_array($name)||is_object($name)? $name : array($name=>$value);
        
        foreach ($vals AS $k=>$v) {
            $v = static::_processFieldValue($k, $v,$this);
            if ($v!==null) {
                $this->{$k} = $v;
                $this->_setModified($k);
            }
        }
        return $this;
    }
    
    public function getChanges() {
        $modified = $this->_getModified();
        $vals = array();
        foreach ($modified AS $k=>$v) {
            $vals[$k] = $this->get($k);
        }
        return $vals;
    }

    public function get($name=null) {
        $result = null;
        if ($name===null) {
            $fieldsList = array_keys(static::$_fields);
            foreach($fieldsList AS $key) {
                $result[$key] = $this->{$key};
            }
        }
        elseif (isset($this->{$name}))
            $result = $this->{$name};
        else
            $result = static::_getAssociation($name, $this);
        return $result;
    }

    public function save(array $data=null) {
        if ($data) $this->set ($data);
        $exists = $this->getId()? static::getProxy()->idExists($this->getId()): false;
        $operation = new DataOperation();
        
        if (!$this->isWritable($exists? $operation::OP_UPDATE : $operation::OP_CREATE)) {
            $operation->setDenied(true);
        }
        elseif ($errors = $this->validate()) {
            $operation->setErrors($errors);
        }
        else{
            if (!$exists) {
                $id = static::getProxy()->insert($this->get());
                if ($id) $this->setId($id);
            }
            else {
                $data = $this->getChanges();
                if ($data) static::getProxy()->updateById($this->getId(),$data);
            }
            $operation->setSuccess(true);
            $this->_setModified(false);
        }

        return $operation;
    }
    
    public function destroy() {
        $operation = new DataOperation();
        if (!$this->isWritable($operation::OP_DESTROY)) {
                $operation->setDenied(true);
        }
        $operation->setSuccess(static::getProxy()->deleteById($this->getId()));
        return $operation;
    }

    public function isValid() {
        $e = $this->validate();
        return empty($e);
    }
    
    public function isReadable() {
        return true;
    }
    public function isWritable($op=null) {
        return true;
    }
    
    public function validate() {
        $errors = array();
        foreach (static::$_fields AS $field => $rules) {

            if (!empty($rules['required']) && !$this->{$field}) {
                $errors['required'][] = $field;
                continue;
            }

            if (!empty($rules['unique'])) {
                $r = static::loadBy(array($field => $this->{$field}));
                if ($r && $r->getId()!==$this->getId()) {
                    $errors['unique'][] = $field;
                    continue;
                }
            }

            if (!empty($rules['vtype'])) {
                switch($rules['vtype']) {
                    case 'email':
                        if (!preg_match('/[\w\.\-]+@\w+[\w\.\-]*?\.\w{1,4}/',$this->{$field})) {
                            $errors['email'][] = $field;
                        }
                        break;
                    case 'link':
                        if (!preg_match('#[0-9A-Za-z\xD0 -\xD1\s\-\_\.]+#i',$this->{$field})) {
                            $errors['link'][] = $field;
                        }
                        break;
                }
            }
            if (!empty($rules['matcher']) && !preg_match($rules['matcher'],$this->{$field})) {
                $errors['matcher'][] = $field;
            }
        }
        return $errors;
    }
        
    protected function _setModified($field) {
        if ($field===false) $this->_modifiedFields = array();
        else $this->_modifiedFields[$field]=1;
        return $this;
    }
    
    protected function _getModified() {
        return (array)$this->_modifiedFields;
    }
    
    protected static function _processFieldValue($fieldName,$value, $model) {
        if (!array_key_exists($fieldName, static::$_fields)) return $value;
        $field = static::$_fields[$fieldName];
        if ($value===null && isset($field['default'])) $value=$field['default'];
        if (!empty($field['type'])) {
            switch($field['type']) {
                case 'int':
                    $value = (int) preg_replace('/[^0-9]+/', '', $value);
                    break;
                case 'float':
                    $value = (float)preg_replace('/[^0-9\.]+/', '', str_replace(',','.',$value));
                    break;
                case 'string':
                case 'text':
                    $value = (string)$value;
                    break;
                case 'bool':
                    $value = (bool)$value;
                    break;
            }
        }
//        $converter = !empty($field['converter']) ? $field['converter'].'Converter' : false;
//        if (method_exists($model, $converter)) {
//            $value = $model->{$converter}($value,$model);
//        }
        return $value;
    }
    
    protected static function _getAssociation($name, $instance) {
        if (!array_key_exists($name, static::$_associations)) return null;
        $assoc = static::$_associations[$name];
        if (!empty($assoc['many'])) {
            $result = new Store(array('model'=> $assoc['model'], 'filters'=>array($assoc['foreignKey'] => $instance->getId())));
        }
        else $result = $assoc['model']::load($instance->{$assoc['foreignKey']});
        return $result;
    }
    
}
?>