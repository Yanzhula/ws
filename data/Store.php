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
        $_model = 'Model';

    public  $page=1,
            $pageSize=0,
            $pages = 1,
            $totalCount=0,
            $children=array(),
            $childrenCount=0;

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

    /**
     *
     * @param type $records
     * @return array
     */

    public function create($records) {

        if (!$records) return false;
        $ids = array();

        if (is_object($records) || is_array($records) && !isset($records[0])) $records = array($records);
        $modelName = $this->_model;

        foreach ($records AS $record) {
            if (!($record instanceof $modelName)) {
                $record = $modelName::create((array)$record);
            }
            if ($record->save()) {
                $this->children[] = $record;
                $ids[] = $record->getId();
            }
        }
        $this->totalCount = sizeof($ids);
        return $ids;
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

    public function getFilters(){
        return $this->_filters;
    }

    public function sort($sorters, $direction=null) {
        if ($direction) {
            $this->_sorters[$sorters] = $direction;
        }
        elseif (is_array($sorters)) {
            foreach ($sorters AS $k=>$v) {
                if (is_array($v)) {
                    if (array_key_exists('property', $v) && array_key_exists('direction', $v)) {
                        $this->_sorters[$v['property']] = $v['direction'];
                    }
                }
                else {
                    $this->_sorters[$k] = $v;
                }
            }
        }
        return $this;
    }

    public function load($page=null, $pageSize=null) {

        if ($page!==null){
            $this->page = $page;
        }
        if ($pageSize!==null){
            $this->pageSize = $pageSize;
        }

        $this->page = max(1, (int)$this->page);
        $this->pageSize = max(0, (int)$this->pageSize);

        $this->totalCount = $this->totalCount();
        $this->pages = $this->pageSize? ceil($this->totalCount/$this->pageSize) : 1;
        $this->children = array();
        $records = $this->getProxy()->select('*', $this->_filters, $this->_sorters, $this->pageSize, $this->page);
        if ($records) {
            $modelName = $this->_model;
            foreach ($records AS $record) {
                $this->children[] = new $modelName($record, true);
            }
            $this->childrenCount = sizeof($this->children);
        }
        return $this->childrenCount;
    }

    public function loadNode($nodeId=0) {
        $modelName = $this->_model;
        $parentProperty = $modelName::getParentProperty();
        if (!$parentProperty) return false;
        $this->clearFilter();
        $this->load(1,0);
        $children=array();
        foreach ($this->children as $child) {
            $children[$child->get($parentProperty)][] = $child;
        }
        $this->children = $this->_readNodeTree($children, $nodeId);
        $this->childrenCount = sizeof($this->children);
        return $this->childrenCount;
    }

    protected function _readNodeTree(&$source, $nodeId){
        $result = array();
        if (!empty($source[$nodeId])) {
            foreach($source[$nodeId] AS $record) {
                $record->children = $this->_readNodeTree($source, $record->getId());
                $result[] = $record;
            }
        }
        return $result;
    }

    public function totalCount() {
        if (!$this->totalCount){
            $this->totalCount = $this->getProxy()->count($this->_filters);
        }
        return $this->totalCount;
    }

    public function update($records) {

        if (!$records) return false;
        $ids = array();

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
            if ($record->save()) {
                $ids[] = $record->getId();
            }
        }
        return $ids;
    }

    public function destroy($records) {
        $ids = array();

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
            if ($record->destroy()) {
                $ids[] =$record->getId();
            }
        }
        return $ids;
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