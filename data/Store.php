<?php
namespace ws\data;

class Store {
    /**
     *
     * @var iProxy
     */
    protected 
        $_proxy,
        $_fields,
        $_sorters,
        $_filters=array(),
        $_pageSize=0,
        $_model = 'Model';
    
    public function  __construct(array $config=null) {
        if ($config) {
            foreach ($config AS $k=>$v){
                $prop = '_'.$k;
                $this->{$prop} = $v;
            }
        }
        $m = $this->_model;
        if ($proxy=$m::getProxy())
            $this->setProxy($proxy);
    }
    public function setProxy(iProxy $proxy){
        $this->_proxy = $proxy;
    }
    public function getProxy(){
        return $this->_proxy;
    }

    public function create($records) {
        
        if (!$records) return false;
        $result = false;
        $createOperation = new DataOperation();
        
        if (is_object($records) || is_array($records) && !isset($records[0])) $records = array($records);
        $modelName = $this->_model;
        
        foreach ($records AS $record) {
            if (!($record instanceof $this->_model)) {
                $record = $modelName::create((array)$record);
            }
            $operation = $record->save();
            if ($operation->success())
                $result[] = $record;
            else {
                $createOperation = $operation;
                break;
            }
        }
        if ($result) $createOperation->setData(array('children'=>$result));
        return $createOperation;
    }
    
    public function clearFilter(){
        $this->_filters=array();
        return $this;
    }
    
    public function filter($filters, $value=null){
        if ($value!==null) {
            $this->_filters[$filters] = $value;
        }
        elseif (is_array($filters)) {
            foreach ($filters AS $k=>$v) {
                if (is_array($v) && array_key_exists('property', $v) && array_key_exists('value', $v)) {
                    $this->_filters[$v['property']] = $v['value'];
                }
                else
                    $this->_filters[$k] = $v;
            }
        }
        return $this;
    }
    
    public function sort($sorters, $direction=null) {
        if ($direction) {
            $this->_sorters[$sorters] = $direction;
        }
        elseif (is_array($sorters)) {
            foreach ($sorters AS $k=>$v) {
                if (is_array($v) && array_key_exists('sort', $v) && array_key_exists('dir', $v)) {
                    $this->_sorters[$v['sort']] = $v['dir'];
                }
                else
                    $this->_sorters[$k] = $v;
            }
        }
        return $this;
    }
    
    public function read($page=1, $pageSize=null) {
        
        if ($pageSize===null) $pageSize = $this->_pageSize;
        if ($page<1) $page=1;
        
        $total = $this->getProxy()->count($this->_filters);
        
        $collection = new StoreCollection();
        $collection->page = $page;
        $collection->pageSize = $pageSize;
        $collection->totalCount = $total;
        $collection->pages = $pageSize? ceil($total/$pageSize) : 1;
        $records = $this->getProxy()->select('*', $this->_filters, $this->_sorters, $pageSize, $page);
        if ($records) {
            $modelName = $this->_model;
            foreach ($records AS $record) {
                $collection->children[] = new $modelName($record);
            }
            $collection->childrenCount = sizeof($collection->children);
        }
        
        $readOperation = new DataOperation();
        $readOperation->setData((array)$collection);
        return $readOperation;
    }

    public function update($records) {
        
        if (!$records) return false;
        $ids = array();
        $updateOperation = new DataOperation();
        
        if (is_object($records) || is_array($records) && !isset($records[0])) $records = array($records);
        $modelName = $this->_model;
        
        foreach ($records AS $record) {
            if (!($record instanceof $this->_model)) {
                $values = (array)$record;
                $id = isset($values[$modelName::getIdProperty()]) ? $values[$modelName::getIdProperty()] : null;
                if (!$id) continue;
                
                $record = $modelName::load($id);
                if (!$record) continue;
                
                $record->set($values);
            }
            $operation = $record->save();
            if ($operation->success())
                $ids[] = $record->getId();
            else {
                $updateOperation = $operation;
                break;
            }
        }
        if ($ids) $updateOperation->setData(array('updated'=>$ids));
        return $updateOperation;
    }
    
    public function destroy($records) {
        $ids = array();
        $destroyOperation = new DataOperation();
        
        if (is_object($records) || is_array($records) && !isset($records[0])) $records = array($records);
        $modelName = $this->_model;
        
        foreach ($records AS $record) {
            if (!($record instanceof $this->_model)) {
                $values = (array)$record;
                $id = isset($values[$modelName::getIdProperty()]) ? $values[$modelName::getIdProperty()] : null;
                if (!$id) continue;
                $record = $modelName::load($id);
                if (!$record) continue;
            }
            $operation = $record->destroy();
            if ($operation->success())
                $ids[] =$record->getId();
            else {
                $destroyOperation = $operation;
                break;
            }
        }
        if ($ids) $destroyOperation->setData (array('deleted'=>$ids));
        return $destroyOperation;
    }
        
    public function getById($id) {
        $modelName = $this->_model;
        return $modelName::load($id);
    }
    
    public function getIds($where) {
        return $this->getProxy()->selectIds($where);
    }

}
?>