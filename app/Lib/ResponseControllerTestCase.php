<?php
/**
 * Created by PhpStorm.
 * User: Rem
 * Date: 18.02.14
 * Time: 23:32
 */

class ResponseControllerTestCase extends ControllerTestCase {
    /**
     * Lets you do functional tests of a controller action.
     *
     * ### Options:
     *
     * - `data` Will be used as the request data. If the `method` is GET,
     *   data will be used a GET params. If the `method` is POST, it will be used
     *   as POST data. By setting `$options['data']` to a string, you can simulate XML or JSON
     *   payloads to your controllers allowing you to test REST webservices.
     * - `method` POST or GET. Defaults to POST.
     * - `return` Specify the return type you want. Choose from:
     *     - `vars` Get the set view variables.
     *     - `view` Get the rendered view, without a layout.
     *     - `contents` Get the rendered view including the layout.
     *     - `result` Get the return value of the controller action. Useful
     *       for testing requestAction methods.
     *
     * @param string $url     The url to test
     * @param array  $options See options
     *
     * @return mixed
     *
     * @requires PHPUnit 3.7
     */
    protected function _testAction($url = '', $options = array()) {
        $this->vars = $this->result = $this->view = $this->contents = $this->headers = null;

        $options = array_merge(array(
                                   'data'   => array(),
                                   'method' => 'POST',
                                   'return' => 'result'
                               ), $options);

        $restore = array('get' => $_GET, 'post' => $_POST);

        $_SERVER['REQUEST_METHOD'] = strtoupper($options['method']);
        if (is_array($options['data'])) {
            if (strtoupper($options['method']) == 'GET') {
                $_GET  = $options['data'];
                $_POST = array();
            } else {
                $_POST = $options['data'];
                $_GET  = array();
            }
        }
        $request = $this->getMock('CakeRequest', array('_readInput'), array($url));

        if (is_string($options['data'])) {
            $request->expects($this->any())
                    ->method('_readInput')
                    ->will($this->returnValue($options['data']));
        }

        $Dispatch = new ControllerTestDispatcher();
        foreach (Router::$routes as $route) {
            if ($route instanceof RedirectRoute) {
                $route->response = $this->getMock('CakeResponse', array('send'));
            }
        }
        $Dispatch->loadRoutes = $this->loadRoutes;
        $Dispatch->parseParams(new CakeEvent('ControllerTestCase', $Dispatch, array('request' => $request)));
        if (!isset($request->params['controller']) && Router::currentRoute()) {
            $this->headers = Router::currentRoute()->response->header();

            return;
        }
        if ($this->_dirtyController) {
            $this->controller = null;
        }

        $plugin = empty($request->params['plugin']) ? '' : Inflector::camelize($request->params['plugin']) . '.';
        if ($this->controller === null && $this->autoMock) {
            $this->generate($plugin . Inflector::camelize($request->params['controller']));
        }
        $params = array();
        if ($options['return'] == 'result') {
            $params['return']    = 1;
            $params['bare']      = 1;
            $params['requested'] = 1;
        }
        $Dispatch->testController = $this->controller;

        if (!isset($options['response']) || !($options['response'] instanceof CakeResponse)) {
            $Dispatch->response = $this->getMock('CakeResponse', array('send'));
        } else {
            $Dispatch->response = $options['response'];
        }
        $this->result             = $Dispatch->dispatch($request, $Dispatch->response, $params);
        $this->controller         = $Dispatch->testController;
        $this->vars               = $this->controller->viewVars;
        $this->contents           = $this->controller->response->body();
        if (isset($this->controller->View)) {
            $this->view = $this->controller->View->fetch('__view_no_layout__');
        }
        $this->_dirtyController = true;
        $this->headers          = $Dispatch->response->header();

        $_GET  = $restore['get'];
        $_POST = $restore['post'];

        return $this->{$options['return']};
    }
}