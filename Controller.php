<?php
namespace ws;
abstract class Controller {

    protected $_modelName;

    abstract public function indexAction(array $params=null);

    public function restAction(array $params=null) {
        $response = new RestResponse();
        $modelName = $this->_modelName;

        if (class_exists($modelName)) {

            $method = null;
            switch (ws::request()->method) {
                case 'OPTIONS':
                    $response->success = true;
                    break;
                case 'POST':
                    $method = '_create';
                    break;
                case 'PUT':
                    $method = '_update';
                    break;
                case 'DELETE':
                    $method = '_destroy';
                    break;
                default:
                    $method = '_read';
            }
            if ($method) {
                $response = call_user_func(array($this, $method), $params);
            }
        }
        else $response->error = 'Invalid Model name in '.get_called_class();

        if (ws::get('debug') && $response) {
            $response->debug = array(
                'time' => sprintf('%1.4f', microtime(true) - START_MICROTIME),
                'memory' => memory_get_usage(),
                'php' => PHP_VERSION
            );
        }

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, X-File-Name');

        return json_encode($response);
    }

    protected function _create(array $params = null) {
        $result = new RestResponse();
        $data = ws::request()->put();
        if ($data) {
            $modelName = $this->_modelName;
            $store = $modelName::makeStore();

            $ids = $store->create($data);
            $result->success = (bool)$ids;
            $result->created = $ids;
            $result->apply($store);
        }
        else {
            $result->error = 'No input data!';
        }
        return $result;
    }

    protected function _read(array $params = null) {
        $modelName = $this->_modelName;
        $id = !empty($params[0]) ? $params[0] : 0;

        $store = $modelName::makeStore();

        $filter = ws::request()->get('filter', true);
        if ($filter)
            $store->filter($filter);
        if ($id && ws::request()->get('node') === null)
            $store->filter($modelName::getIdProperty(), $id);

        if (ws::request()->get('node') !== null)
            $store->filter('parentId', ws::request()->get('node'));

        $sort = ws::request()->get('sort');
        $dir = ws::request()->get('dir');
        if ($sort && $dir) {
            $store->sort($sort, $dir);
        }
        $result = new RestResponse();
        $result->success = (bool)$store->load(ws::request()->get('page'), ws::request()->get('limit'));
        $result->apply($store);

        return $result;
    }

    protected function _update(array $params = null) {
        $result = new RestResponse();
        $modelName = $this->_modelName;
        $id = !empty($params[0]) ? $params[0] : 0;
        $data = ws::request()->put();
        if ($data) {
            if ($id) {
                $instance = $modelName::load($id);
                if ($instance) {
                    $instance->set($data);
                    $data = array($instance);
                }
            }

            $store = $modelName::makeStore();
            $ids = $store->update($data);
            $result->success = (bool)$ids;
            $result->updated = $ids;
        }
        else {
            $result->error = 'No input data!';
        }
        return $result;
    }

    protected function _destroy(array $params = null) {
        $result = new RestResponse();
        $modelName = $this->_modelName;
        $id = !empty($params[0]) ? $params[0] : 0;
        $data = ws::request()->put();
        if ($data) {
            if ($id) {
                $data = array($modelName::load($id));
            }

            $store = $modelName::makeStore();
            $ids = $store->destroy($data);
            $result->success = (bool)$ids;
            $result->deleted = $ids;
        }
        else {
            $result->error = 'No input data!';
        }
        return $result;
    }
}
?>