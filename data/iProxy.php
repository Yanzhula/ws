<?php
namespace ws\data;

interface iProxy {
    public function __construct(array $config=null);
    public function select($fields,$where,$order=null,$limit=false,$page=1);
    public function selectIds($where);
    public function selectById($id, $fields='*');
    public function selectBy($where, $fields='*');
    public function idExists($id);
    public function insert($data);
    public function update($data, $where);
    public function updateById($id, $data);
    public function delete($where);
    public function deleteById($id);
    public function count($where=false);
}
?>