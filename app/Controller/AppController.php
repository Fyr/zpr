<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package       app.Controller
 * @link http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 *
 * @property Auth Auth
 * @property CakeResponse response
 * @property CakeRequest request
 */
class AppController extends Controller {
    public $uses = array('Auth');
    public $authCookie;
    public $currentUserId;
    protected $auth_cookie_name;
    protected $json_render = false;

    public function beforeFilter() {
        parent::beforeFilter();

        $this->json_render = true;

        // Читаем авторизационные куки
        $this->auth_cookie_name = Configure::read('Security.auth_cookie_name');
        if ($this->auth_cookie_name and isset($_COOKIE[$this->auth_cookie_name])) {
            $this->authCookie = $_COOKIE[$this->auth_cookie_name];
        } else {
            $this->authCookie = array();
        }

        // Определяем текущего пользователя по данным авторизационных кук
        $this->currentUserId = $this->Auth->authenticate($this->authCookie);

        if (Configure::read('debug') > 0 and isset($this->request->query['user_id']) and $this->request->query['user_id']) {
            $this->currentUserId = (int) $this->request->query['user_id'];
        }
    }

    public function beforeRender() {
        if (Configure::read('debug') > 0) {
            $this->response->header('Access-Control-Allow-Origin: *');
            $this->response->header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
            $this->response->header('Access-Control-Allow-Headers: "Origin, X-Requested-With, Content-Type, Accept"');
        }
        if ($this->json_render) {
            $this->viewPath = 'Layouts';
            $this->view     = 'empty';

            $this->response->type('json');
            if (array_key_exists('answer', $this->viewVars)) {
                $this->response->body(json_encode($this->viewVars['answer']));
            }
            $this->response->send();
        }

        parent::beforeRender();
    }

    /**
     * Установка куки
     *
     * @param      $key     Ключ
     * @param      $value   Значение
     * @param null $time    Время жизни (по умолчанию = 1 год)
     *
     * @return bool Удалось поставить?
     */
    protected function setCookie($key, $value, $time = null) {
        if ($time === null) {
            $time = 60 * 60 * 24 * 365; // 1 year
        }

        $result = setcookie(
            $key,
            $value,
            time() + $time,
            '/',
            Configure::read('current_domain_name'),
            false,
            Configure::read('debug') == 0 ? true : false
        );

        if ($result) {
            unset($_COOKIE[$key]);
        }

        return $result;
    }

    /**
     * Произвести авторизацию указанного пользователя
     *
     * @param $user_id  Id пользователя
     *
     * @return bool Удалось?
     */
    protected function makeAuth($user_id) {
        if (!$this->auth_cookie_name) {
            return false;
        }

        if ($user_id == $this->currentUserId) {
            return true;
        }

        $cookie_data = $this->Auth->getCookieData($user_id);

        if (!$cookie_data or !$this->clearAuthCookies()) {
            return false;
        }

        $result = $this->setCookie("{$this->auth_cookie_name}[{$cookie_data['key']}]", $cookie_data['value']);

        if ($result) {
            $this->currentUserId = $user_id;
        } else {
            $this->currentUserId = false;
        }

        return $result;
    }

    /**
     * Очистка авторизационных кук
     *
     * @return bool почистились ли куки?
     */
    protected function clearAuthCookies() {
        $res = true;
        foreach ($this->authCookie as $key => $value) {
            $res = $res && setcookie(
                "{$this->auth_cookie_name}[{$key}]",
                false,
                1,
                '/',
                Configure::read('current_domain_name'),
                false,
                Configure::read('debug') == 0 ? true : false
            );

            if ($res) {
                unset($_COOKIE["{$this->auth_cookie_name}[{$key}]"]);
            }
        }

        return $res;
    }

    /**
     * Метод, выводящий информацию о том, что доступ к данной странице запрещён
     */
    protected function __access_denied() {
        $this->response->statusCode(403);

        $success = false;
        $data    = 'access denied';

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Функция проверки админского доступа
     *
     * @return bool Разрешён ли доступ
     */
    protected function adminCheck() {
        // TODO: проверить на админский доступ. Если доступа нет, то показать access denied и 403 ошибку
        if (Configure::read('debug') == 0) {
            $this->__access_denied();
            return false;
        } else {
            return true;
        }
    }

    /**
     * Функция проверки доступа к собственным данным
     *
     * @param $id   Пользователь, к данным которого идёт обращения
     *
     * @return bool Разрешён ли доступ
     */
    protected function userCheck($id) {
        $result = ($this->currentUserId == $id);
        if ((int) $id == 0) {
            $result = false;
        }

        if (!$result) {
            $this->__access_denied();
        }

        return $result;
    }

    /**
     * Функция проверки авторизован ли текущий пользователь
     *
     * @return bool Разрешён ли доступ
     */
    protected function authCheck() {
        if ($this->currentUserId) {
            $result = true;
        } else {
            $result = false;
        }

        if (!$result) {
            $this->response->statusCode(401);

            $success = false;
            $data    = 'unauthorized';

            $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
            $this->set(compact('answer'));
        }

        return $result;
    }
}
