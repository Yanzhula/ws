<?php
namespace ws;

abstract class App {
    abstract public function run();

    protected function _getControllerFromRequest() {
        $path = explode('/', ws::request()->path);
        $controllerName=  ws::get('app.controller.default');
        $controllerAction = 'indexAction';
        if (empty($path[0])) $controllerName = ws::get('app.controller.home');
        else{
            $names = array_map('ucfirst', $path);
            $path = array();
            while($names){
                $check = '\app\controllers\\'.implode('_',$names);
                if (class_exists($check)) {
                    $controllerName = $check;
                    break;
                }
                array_unshift($path, array_pop($names));
            }
        }
        $controller = new $controllerName;
        //action
        if (!empty($path[0])) {
            if (method_exists($controller, $path[0].'Action')) {
                $controllerAction = $path[0].'Action';
                array_shift($path);
            }
        }

        return (object)array(
            'controller' => $controller,
            'action' => $controllerAction,
            'params' => $path
        );
    }

}
?>