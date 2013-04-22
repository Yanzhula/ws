<?php

namespace ws\data;

class PDOProxy implements iProxy {

    /**
     * @var \PDO
     */
    protected $_PDO;
    protected $_table;
    protected $_fields;
    protected $_idProperty;

    public function __construct(array $config = null) {
        if ($config) {
            $this->_PDO = \ws\ws::get(!empty($config['PDO']) ? $config['PDO'] : 'PDO');
            $this->_table = $config['table'];
            $this->_idProperty = $config['idProperty'];
        }
    }

    public function select($fields, $where, $order = null, $limit = false, $page = 1) {
        $where = $this->_makeWhereSQL($where);
        $order = $this->_makeOrderSQL($order);
        if (!is_array($fields))
            $fields = array($fields);
        $fields = implode(',', $fields);
        $limit = $limit? ' LIMIT ' . (($page - 1) * $limit . ',') . $limit : '';

        return $this->_PDO->query('SELECT ' . $fields . ' FROM `' . $this->_table . '` ' . $where . $order . $limit)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function selectIds($where) {
        $sqlWhere = $where ? $this->_makeWhereSQL($where) : '';
        return $this->_PDO->query('SELECT `' . $this->_idProperty . '` FROM `' . $this->_table . '` ' . $sqlWhere)
                        ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function selectById($id, $fields = '*') {
        if (!is_array($fields))
            $fields = array($fields);
        $fields = implode(',', $fields);
        return $this->_PDO->query('SELECT ' . $fields . ' FROM `' . $this->_table . '` WHERE `' . $this->_idProperty . '`="' . $id . '" LIMIT 1')
                        ->fetch(\PDO::FETCH_ASSOC);
    }

    public function selectBy($where, $fields = '*') {
        if (!is_array($fields))
            $fields = array($fields);
        $fields = implode(',', $fields);
        $sqlWhere = $where ? $this->_makeWhereSQL($where) : '';
        return $this->_PDO->query('SELECT ' . $fields . ' FROM `' . $this->_table . '` ' . $sqlWhere . ' LIMIT 1')
                        ->fetch(\PDO::FETCH_ASSOC);
    }

    public function idExists($id) {
        return (bool) $this->count(array($this->_idProperty => $id));
    }

    public function insert($data) {
        $setSQL = $this->_makeSetSQL($data, true);
        $this->_PDO->query('INSERT INTO `' . $this->_table . '` SET ' . $setSQL);
        return $this->_PDO->lastInsertId();
    }

    public function update($data, $where) {
        $setSQL = $this->_makeSetSQL($data);
        $where = $this->_makeWhereSQL($where);
        if (!$setSQL)
            return false;
        $this->_PDO->query('UPDATE  `' . $this->_table . '` SET ' . $setSQL . $where);
        return true;
    }

    public function updateById($id, $data) {
        return $this->update($data, array($this->_idProperty => $id));
    }

    public function delete($where) {
        $where = $this->_makeWhereSQL($where);
        $this->_PDO->query('DELETE FROM `' . $this->_table . '`' . $where);
        return true;
    }

    public function deleteById($id) {
        return $this->delete(array($this->_idProperty => $id));
    }

    public function count($where = false) {
        $sqlWhere = $where ? $this->_makeWhereSQL($where) : '';
        $result = $this->_PDO->query('SELECT COUNT(*) FROM `' . $this->_table . '` ' . $sqlWhere)
                ->fetch(\PDO::FETCH_COLUMN);
        return $result;
    }

    protected function _makeSetSQL($data, $fillAll = false) {
        if (!$this->_fields)
            $this->_fields = $this->_PDO->query('SHOW COLUMNS FROM `' . $this->_table . '`')
                    ->fetchAll(\PDO::FETCH_COLUMN);
        $data = (array) $data;
        $sql = array();
        foreach ($this->_fields AS $field) {
            if (isset($data[$field]))
                $sql[] = '`' . $field . '` = ' . $this->_PDO->quote($data[$field]);
            elseif ($fillAll)
                $sql[] = '`' . $field . '` = \'\'';
        }
        return implode(',', $sql);
    }

    protected function _makeWhereSQL(array $where) {
        $sql = array();
        foreach ($where AS $prop => $value) {
            if ($prop == '_search_') {

                $words = preg_replace('#[^0-9A-Za-z\xD0 -\xD1\s\-]+#i', ' ', strip_tags($value['q']));
                $words = preg_replace('#[\s\-]+#', ' ', $words);
                $words = explode(' ', $words);

                $orArray = array();
                //$words = array_map(array($this->_PDO, 'quote'), $words);
                $qStr = $this->_PDO->quote('%'.implode('%', $words).'%');

                foreach ($value['fields'] AS $field) {
                    if (!$this->_isField($field))
                        continue;
                    $orArray[] = '`' . $field . '` LIKE ' . $qStr;
                }
                $sql[] = '(' . implode(' OR ', $orArray) . ')';
                //print_r($sql);die;
                continue;
            }
            if (!$this->_isField($prop))
                continue;
            if (is_array($value)) {

                $isRange = false;
                if (array_key_exists('from', $value)) {
                    $sql[] = '`' . $prop . '` >= ' . $this->_PDO->quote($value['from']);
                    $isRange = true;
                }
                if (array_key_exists('to', $value)) {
                    $sql[] = '`' . $prop . '` <= ' . $this->_PDO->quote($value['to']);
                    $isRange = true;
                }
                if (array_key_exists('not', $value)) {
                    $sql[] = '`' . $prop . '` <> ' . $this->_PDO->quote($value['not']);
                    $isRange = true;
                }
                if ($isRange)
                    continue;

                $tmp = array();
                foreach ($value AS $v)
                    $tmp[] = $this->_PDO->quote($v);

                if ($tmp) {
                    $sql[] = '`' . $prop . '` IN (' . implode(',', $tmp) . ')';
                }
            }
            else
                $sql[] = '`' . $prop . '` = ' . $this->_PDO->quote($value);
        }
        $result = implode(' AND ', $sql);
        if ($result)
            $result = ' WHERE ' . $result;
        return $result;
    }

    protected function _makeOrderSQL(array $order = null) {
        if (!$order)
            return '';
        $sql = array();
        foreach ($order AS $prop => $dir) {
            $dir = strtoupper($dir);
            if (!in_array($dir, array('ASC', 'DESC')))
                $dir = 'ASC';
            if (!$this->_isField($prop))
                continue;
            $sql[] = '`' . $prop . '` ' . $dir;
        }
        $result = implode(', ', $sql);
        if ($result)
            $result = ' ORDER BY ' . $result;
        return $result;
    }

    protected function _isField($name) {
        if (!$name)
            return false;
        //dirty
        if (!$this->_fields)
            $this->_fields = $this->_PDO->query('SHOW COLUMNS FROM `' . $this->_table . '`')
                    ->fetchAll(\PDO::FETCH_COLUMN);
        return in_array($name, $this->_fields);
    }

}

?>