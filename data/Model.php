<?php
namespace ws\data;

class Model {

    const OP_CREATE = 1;
    const OP_UPDATE = 2;
    const OP_DESTROY = 3;

    protected static
        $_idProperty = 'id',
        $_parentProperty,
        $_fields,
        $_proxyConfig=array(),
        $_validations,
        $_associations=array();

    protected $_modifiedFields = array(), $_validationErrors;
    /**
     * @var Proxy
     */
    private static $_proxy;

    public static function load($id) {
        $instance=$data=null;
        $data = static::getProxy()->selectById($id);
        if ($data){
            $instance = new static($data, true);
        }
        return $instance;
    }

    public static function loadBy($where) {
        $instance=$data=null;
        $data = static::getProxy()->selectBy($where);
        if ($data){
            $instance = new static($data, true);
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

    public static function getParentProperty() {
        return static::$_parentProperty;
    }

    public static function getFields(){
        return static::$_fields;
    }

    /**
     *
     * @param array $config
     * @return \ws\data\Store
     */

    public static function makeStore(array $config=null) {
        $config['model'] = get_called_class();
        return new Store($config);
    }

    public function __construct($data=null, $silent=false) {
        if ($data) $this->set($data, null, $silent);
    }

    public function __set($name, $value) {
        return $this->set($name,$value, true);
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
            in_array($field, $this->_modifiedFields)
            : !empty($this->_modifiedFields);
    }

    public function set($name, $value=null, $silent=false) {
        $vals = is_array($name)||is_object($name)? $name : array($name=>$value);

        foreach ($vals AS $k=>$v) {
                $v = static::_processFieldValue($k, $v, $this, $silent);

            if ($v!==null) {
                if (isset($this->{$k}) && $this->{$k} === $v) continue;
                $this->{$k} = $v;
                if (!$silent) $this->_setModified($k);
            }
        }
        return $this;
    }

    public function getChanges() {
        $modified = $this->_getModified();
        $vals = array();
        foreach ($modified AS $k) {
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

    protected function _validate($type='*') {

        $this->_cleanValidationErrors();
        if (empty(static::$_validations[$type])) return true;

        foreach (static::$_validations[$type] AS $field => $rules) {

            switch($rules['type']) {
                case 'length':
                    $l = mb_strlen($this->get($field));
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
                    if (!preg_match('/[\w\.\-]+@\w+[\w\.\-]*?\.\w{1,4}/',$this->get($field))) {
                        $this->_addValidationError($field, 'email');
                    }
                break;
                case 'matcher':
                    if (!preg_match($rules['matcher'], $this->get($field))) {
                        $this->_addValidationError($field, 'matcher', array(
                            'regexp' => $rules['matcher']
                        ));
                    }
                break;
            }

            if (!empty($rules['required'])) {
                if ($this->get($field)===null) {
                    $this->_addValidationError($field, 'required');
                }
            }

            if (!empty($rules['unique'])) {
                $r = static::loadBy(array($field => $this->get($field)));
                if ($r && $r->getId()!==$this->getId()) {
                    $this->_addValidationError($field, 'unique');
                }
            }

        }
        return !$this->_hasValidationErrors();
    }

    public function isValid($type='*') {
        return $this->_validate($type);
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

    public function getValidationErrors() {
        return $this->_validationErrors;
    }

    public function save(array $data=null) {
        if ($data) $this->set ($data);
        $exists = $this->getId()? static::getProxy()->idExists($this->getId()): false;
        $result = false;

        if ($this->isWritable($exists? self::OP_UPDATE : self::OP_CREATE)) {
            if ($this->isValid()) {
                if (!$exists) {
                    $id = static::getProxy()->insert($this->get());

                    if ($id) {
                        $this->setId($id);
                    }
                }
                else {
                    $data = $this->getChanges();
                    if ($data) static::getProxy()->updateById($this->getId(),$data);
                }
                $result = true;
                $this->_setModified(false);
            }
            else {
                //exception
            }
        }
        else {
            //exception
        }
        return $result;
    }

    public function destroy() {
        if ($this->isWritable(self::OP_DESTROY)) {
            static::getProxy()->deleteById($this->getId());
            return true;
        }
        return false;
    }

    public function isReadable() {
        return true;
    }
    public function isWritable($op=null) {
        return true;
    }

    protected function _setModified($field) {
        if ($field===false) {
            $this->_modifiedFields = array();
        }
        elseif (!in_array($field, $this->_modifiedFields)) {
            $this->_modifiedFields[]=$field;
        }
        return $this;
    }

    protected function _getModified() {
        return $this->_modifiedFields;
    }

    protected static function _processFieldValue($fieldName,$value, $model, $silent=false) {
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
                    $value = (string)$value;
                    break;
                case 'bool':
                    $value = (bool)$value;
                    break;
            }
        }
        if (!$silent && !empty($field['setter']) && method_exists($model, $field['setter'])) {
            $value = call_user_func(array($model, $field['setter']), $value);
        }
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