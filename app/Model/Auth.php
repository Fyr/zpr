<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.05.12
 * Time: 2:26
 * To change this template use File | Settings | File Templates.
 */
class Auth extends AppModel {
    public $useTable = 'auth';

    /**
     * Генерируем соль, с помощью которой будем шифровать имя куки
     *
     * @return string   Строка соли
     */
    protected function generateSalt1() {
        $salt = Configure::read('Security.salt');
        return sha1($salt . microtime(true));
    }

    /**
     * Генерируем соль, которая будет значением куки
     *
     * @return string   Строка соли
     */
    protected function generateSalt2() {
        $salt = Configure::read('Security.salt');
        return sha1($salt . microtime(true));
    }

    /**
     * Попробуем найти пользователя по данным авторизационных кук
     *
     * @param $cookie_data  Данные авторизационных кук
     *
     * @return bool False | Id пользователя
     */
    function authenticate($cookie_data) {
        if (!$cookie_data or !is_array($cookie_data) or empty($cookie_data)) {
            return false;
        }

        $conditions       = array();
        $conditions['OR'] = array();
        foreach ($cookie_data as $name => $hash) {
            $conditions['OR'][] = array('SHA1(CONCAT(Auth.salt, Auth.user_id))' => $name,
                                        'Auth.hash'                             => $hash);
        }

        $auth = $this->find('first',
                            array(
                                'conditions' => $conditions,
                                'order'      => array('created DESC'),
                                'fields'     => 'user_id'));

        if (!$auth or empty($auth)) {
            return false;
        }

        return $auth['Auth']['user_id'];
    }

    /**
     * Составим имя и значение для авторизационной куки пользователя
     *
     * @param $user_id  Id пользователя
     *
     * @return array|bool   Массив с именем и значение авторизационной куки | False, в случае неудачи
     */
    function getCookieData($user_id) {
        if ((int) $user_id == 0) {
            return false;
        }

        $salt1 = $this->generateSalt1();

        $name = sha1($salt1 . $user_id);
        $hash = $this->generateSalt2();

        try {
            // Удалим все прошлые данные авторизации
            $this->deleteAll(array('user_id' => $user_id), false);

            $this->create();

            $data            = array();
            $data['user_id'] = $user_id;
            $data['salt']    = $salt1;
            $data['hash']    = $hash;

            if (!$this->save($data)) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        return array('key'   => $name,
                     'value' => $hash);
    }

    /**
     * Проверим соответствие пользователя авторизационным кукам
     * @deprecated since it was born
     *
     * @param $user_id      Id пользователя
     * @param $cookie_data  Данные авторизационных кук
     *
     * @return bool Есть ли авторизация у текущего пользователя для указанного Id пользователь
     */
    function check($user_id, $cookie_data) {
        if (!$cookie_data or !is_array($cookie_data) or empty($cookie_data)) {
            return false;
        }
        $user_id = (int) $user_id;

        $conditions       = array();
        $conditions['OR'] = array();
        foreach ($cookie_data as $name => $hash) {
            $conditions['OR'][] = array('SHA1(CONCAT(Auth.salt, Auth.user_id))' => $name,
                                        'Auth.hash'                             => $hash);
        }
        $conditions['user_id'] = $user_id;

        $auth = $this->find('first',
            array(
                'conditions' => $conditions,
                'order'      => array('created DESC'),
                'fields'     => 'id'));

        if (!$auth or empty($auth) or !isset($auth['Auth']) or !isset($auth['Auth']['id']) or empty($auth['Auth']['id'])) {
            return false;
        }

        return true;
    }
}
