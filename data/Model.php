<?php
namespace ws\data;

class Model {

    const OP_CREATE = 1;
    const OP_UPDATE = 2;
    const OP_DESTROY = 3;

    protected static
        $_idProperty = 'id',
        $_nodeProperty,
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
    public static function getNodeProperty() {
        return static::$_nodeProperty;
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

    public function __construct($data=null) {
        if ($data) $this->set($data);
        if ($this->getId()) $this->_setModified(false);
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
            is_array($this->_modifiedFields) && array_key_exists($field, $this->_modifiedFields)
            : !empty($this->_modifiedFields);
    }

    public function set($name, $value=null, $silent=false) {
        $vals = is_array($name)||is_object($name)? $name : array($name=>$value);

        foreach ($vals AS $k=>$v) {
                $v = static::_processFieldValue($k, $v, $this, $silent);

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
        $result = false;

        if ($this->isWritable($exists? self::OP_UPDATE : self::OP_CREATE)) {
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
        if ($field===false) $this->_modifiedFields = array();
        else $this->_modifiedFields[$field]=1;
        return $this;
    }

    protected function _getModified() {
        return (array)$this->_modifiedFields;
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