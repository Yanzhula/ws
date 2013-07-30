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
        $_associations=array();

    protected $_proxy, $_modifiedFields = array();

    public static function load($id, $proxy=null) {
        $instance=$data=null;
        $proxy = $proxy? $proxy : static::createProxy();
        $data = $proxy->selectById($id);
        if ($data){
            $instance = new static($data, true);
        }
        return $instance;
    }

    public static function loadBy($where, $proxy=null) {
        $instance=$data=null;
        $proxy = $proxy? $proxy : static::createProxy();
        $data = $proxy->selectBy($where);
        if ($data){
            $instance = new static($data, true);
        }
        return $instance;
    }

    /**
     *
     * @return iProxy
     */

    public static function createProxy() {
        $config = static::$_proxyConfig;
        $proxyType = $config['type'];
        unset($config['type']);
        $config['idProperty'] = static::getIdProperty();
        return new $proxyType($config);
    }

    public function setProxy(iProxy $proxy) {
        $this->_proxy = $proxy;
        return $this;
    }

    public function getProxy() {
        if (!$this->_proxy) {
            $this->_proxy = static::createProxy();
        }
        return $this->_proxy;
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
        $storeClass = '\ws\data\Store';
        $config['model'] = get_called_class();
        $cStoreClass = str_replace('\models\\', '\stores\\', $config['model']);
        if (class_exists($cStoreClass)) {
            $storeClass = $cStoreClass;
        }
        return new $storeClass($config);
    }

    /**
     * @param array $data
     * @return \ws\Model
     */
    public function __construct(array $data=null, $silent=false) {
        if ($data) $this->set($data, null, $silent);
    }
    //alias of constructor
    public static function create(array $data=null, $silent=false){
        return new static($data, $silent);
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
        $this->set(static::getIdProperty(),$id);
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

    public function save(array $data=null, $proxy=null) {
        if ($data) $this->set ($data);
        $proxy = $proxy? $proxy : $this->getProxy();

        $exists = $this->getId()? $proxy->idExists($this->getId()): false;
        $result = false;

        if ($this->isWritable($exists? self::OP_UPDATE : self::OP_CREATE, $proxy)) {
            if (!$exists) {
                $id = $proxy->insert($this->get());

                if ($id) {
                    $this->setId($id);
                }
            }
            else {
                $data = $this->getChanges();
                if ($data) $proxy->updateById($this->getId(),$data);
            }
            $result = true;
            $this->_setModified(false);
        }
        else {
            //exception
        }
        return $result;
    }

    public function destroy($proxy=null) {
        $proxy = $proxy? $proxy : $this->getProxy();
        if ($this->isWritable(self::OP_DESTROY, $proxy)) {
            $proxy->deleteById($this->getId());
            $this->_destroyCascades();
            return true;
        }
        return false;
    }

    public function isWritable($op=null, $proxy=null) {
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
            $model = $assoc['model'];
            $result = $model::makeStore(array('filters'=>array($assoc['foreignKey'] => $instance->getId())));
        }
        else $result = $assoc['model']::load($instance->{$assoc['foreignKey']});
        return $result;
    }

    protected function _destroyCascades() {
        foreach (self::$_associations AS $name => $assoc) {
            if (!empty($assoc['cascade'])) {
                $result = self::_getAssociation($name, $this);
                if ($result instanceof Model) {
                    $result->destroy();
                }
                elseif($result instanceof Store){
                    $result->load();
                    $result->destroy($result->children);
                }
            }
        }
    }


}
?>