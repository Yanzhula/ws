<?php
namespace ws\data;

class SessionProxy implements iProxy {
    /**
     * @var \PDO
     */
    protected $_table;
    protected $_idProperty;
    protected $_proxyData;
    
    public function __construct(array $config=null) {
        if ($config) {
            $this->_table = $config['table'];
            $this->_idProperty = $config['idProperty'];
            
            if (empty($_SESSION['ws']['SessionProxy'][$this->_table]['data']))
                $_SESSION['ws']['SessionProxy'][$this->_table]['data'] = array();
            $this->_proxyData = &$_SESSION['ws']['SessionProxy'][$this->_table]['data'];
            
        }
    }

    public function select($fields,$where,$order=null,$limit=false,$page=1) {
        $rows = $this->_getData($where);
        if ($limit) {
            $rows = array_slice($rows, ($page-1)*$limit, $limit);
        }
        $this->_sortData($rows, $order);
        return $rows;
    }
    
    public function selectIds($where){
        $rows = $this->_getData($where);
        $ids = array();
        foreach ($rows AS $r) $ids[] = $row[$this->_idProperty];
        return $ids;
    }

    public function selectById($id, $fields='*') {
        return array_key_exists($id, $this->_proxyData)? 
            $this->_proxyData[$id]: null;
    }
    public function selectBy($where, $fields='*') {
        return $this->_getData($where);
    }
    public function idExists($id){
        return (bool) array_key_exists($id, $this->_proxyData);
    }

    public function insert($data) {
        return $this->_insertRow($data);
    }
    public function update($data, $where) {
        $rows = $this->_getData($where);
        foreach ($rows AS $r) {
            $up = array_merge($r, $data);
            $this->_updateRow($up[$this->_idProperty], $up);
        }
        return $this;
    }
    public function updateById($id, $data){
        return $this->update($data, array($this->_idProperty => $id));
    }

    public function delete($where) {
        $rows = $this->_getData($where);
        foreach ($rows AS $r){
            $this->_removeRow($r[$this->_idProperty]);
        }
        return $this;
    }
    
    public function deleteById($id){
        return $this->delete(array($this->_idProperty=>$id));
    }

    public function count($where=false) {
        return sizeof($this->_getData($where));
    }
    
    protected function _getNextId(){
        if (!isset($_SESSION['ws']['SessionProxy'][$this->_table]['AI']))
            $_SESSION['ws']['SessionProxy'][$this->_table]['AI'] = 0;
        ++$_SESSION['ws']['SessionProxy'][$this->_table]['AI'];
        return $_SESSION['ws']['SessionProxy'][$this->_table]['AI'];
    }
    
    protected function _insertRow($row){
        $id = $this->_getNextId();
        $row[$this->_idProperty] = $id;
        $this->_proxyData[$id] = $row;
        return $id;
    }
    
    protected function _removeRow($id){
        if ($id) unset($this->_proxyData[$id]);
        return $this;
    }
    protected function _updateRow($id, $row){
        if ($id) $this->_proxyData[$id] = $row;
        return $this;
    }
    
    protected function _getData(array $where){
        $result = $this->_proxyData;
        if ($result && $where) {
            $result = $this->_filterData($result, $where);
        }
        return $result;
    }

    protected function _filterData(array $data, array $where){
        $result = array();
        foreach ($data AS $d){
            if ($this->_matchRow($d, $where)) $result[] = $d;
        }
        return $result;
    }
    
    protected function _matchRow(array &$row, array $where) {
        $result = true;
        
        foreach ($where AS $prop => $value) {
            if (!array_key_exists($prop, $row)) {
                $result = false;
                break;
            }
            if (is_array($value)) {
                $isRange = false;
                if (array_key_exists('from', $value)) {
                    $isRange=true;
                    if ($row[$prop] < $value['from']) {
                        $result = false;
                        break;
                    }
                }
                if (array_key_exists('to', $value)) {
                    $isRange=true;
                    if ($row[$prop] > $value['to']) {
                        $result = false;
                        break;
                    }
                }
                if (array_key_exists('not', $value)) {
                    $isRange=true;
                    if ($row[$prop] == $value['not']) {
                        $result = false;
                        break;
                    }
                }           
                if ($isRange) continue;
                if (!in_array($row[$prop], $value)) {
                    $result = false;
                    break;
                }
            }
            elseif ($row[$prop] != $value) {
                $result = false;
                break;
            }
        }
        return $result;
    }
    
    protected function _sortData(array &$data, array $order=null) {
        return $data;
    } 
}
?>