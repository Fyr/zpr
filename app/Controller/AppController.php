<?php
App::uses('Controller', 'Controller');
App::uses('BalanceHistory', 'Model');
App::uses('User', 'Model');
class AppController extends Controller {
	
	const STATUS_OK = 'success';
	const STATUS_ERROR = 'error';

    public $uses = array('Auth', 'User', 'BalanceHistory');
    public $authCookie;
    public $currentUserId;
    protected $auth_cookie_name;
    protected $json_render = false, $_response = null;

    public function beforeFilter() {
        parent::beforeFilter();

        $this->json_render = true;

        /* Читаем авторизационные куки */
        $this->auth_cookie_name = Configure::read('Security.auth_cookie_name');
        if ($this->auth_cookie_name and isset($_COOKIE[$this->auth_cookie_name])) {
            $this->authCookie = $_COOKIE[$this->auth_cookie_name];
        } else {
            $this->authCookie = array();
        }

        /* Определяем текущего пользователя по данным авторизационных кук */
        $this->currentUserId = $this->Auth->authenticate($this->authCookie);

        if (Configure::read('debug') > 0 and isset($this->request->query['user_id']) and $this->request->query['user_id']) {
            $this->currentUserId = (int) $this->request->query['user_id'];
        }
        
        /*
         *  Начисление бонуса за ежедневневный заход на сайт
         */
          
        /* Получим дату авторизации, с которой начинается ежедневный отсчет */
        $dateAuth = $this->User->find('first', array(
            'fields' => array('date_auth'),
            'conditions' => array('id' => $this->currentUserId)
        ));
        
        /* Если дата авторизации существует... */
        if ($dateAuth) {
            /* ...проверим сколько дней подряд пользователь был на сайте */
            $countDays = $this->BalanceHistory->find('count', array(
                'fields' => array('id'),
                'conditions' => array(
                    'BalanceHistory.oper_type' => BalanceHistory::BH_DAILY,
                    'BalanceHistory.user_id' => $this->currentUserId,
                    'BalanceHistory.created >=' => $dateAuth['User']['date_auth']
                )
            ));
            if ($countDays) {
                /* Если в таблице balance_history есть записи старше чем дата авторизации
                   проверим сколько времени прошло с момента авторизации */
                $exDays = time() - strtotime($dateAuth['User']['date_auth']);
                if ($exDays >= $countDays * 86400 && $exDays <= $countDays * 86400 + 86400 ) {
                    /* Начался следующий день в серии ежедневных заходов
                       добавим запись в историю начислений и изменим баланс.

                       Если пользователь собрал серию ежедневных заходов более 5-ти дней
                       уменьшим переменную содержащую количество дней до 4-x (5-й индекс), т.к. если
                       кол-во дней 5 и более, то начисляется фиксированное количество ИВ */
                    $countDays = $countDays >= 5 ? 4 : $countDays;
                    $this->setEveryDayBonus($countDays);
                } elseif ($exDays > $countDays * 86400 + 86400) {
                    /* Если с момента последней записи в balance_history прошло больше 24 часов
                       добавим запись в историю начислений, изменим баланс и обновим дату авторизации */
                    $this->setEveryDayBonus(0);
                }
            } else {
                /* Если в таблице balance_history нет ниодной записи старше даты авторизации
                   добавим запись в историю начислений, изменим баланс и обновим дату авторизации */
                $this->setEveryDayBonus(0);
            }
        } else {
            /* Если даты авторизации не существует, т.е пользователь после регистрации еще не авторизировался
               добавим запись в историю начислений, изменим баланс и запишем дату авторизации */
            $this->setEveryDayBonus(0);
        }
    }
    
    /**
     * Начисление бонуса за серию ежедневного захода
     * @param int $numDay
     */
    private function setEveryDayBonus($numDay) {
        $operType  = $this->BalanceHistory->getOperationOptions();
        $operBonus = $this->BalanceHistory->getOperationBonus();
        
        $this->BalanceHistory->addOperation(
            BalanceHistory::BH_DAILY,
            $operBonus[BalanceHistory::BH_DAILY][$numDay],
            $this->currentUserId,
            $operType[BalanceHistory::BH_DAILY]
        );
        if (!$numDay) {
            $this->User->save(array('id' => $this->currentUserId, 'date_auth' => DboSource::expression('NOW()')));
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
            $this->renderJSON();
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
    public function renderJSON() {
	    if ($this->_response) {
	    	/*
    	    $this->set('_response', $this->_response);
    	    $this->set('_serialize', '_response');
    	    */
	    	$answer = $this->_response;
	    	$this->set(compact('answer'));
	    }
	}
	public function setResponse($data = array()) {
	    $this->_response = array('status' => self::STATUS_OK);
	    if ($data) {
	    	$this->_response['data'] = $data;
	    }
	}
	
	public function getResponse() {
	    return $this->_response;
	}
	
	public function setError($errMsg) {
	    $this->_response = array('status' => self::STATUS_ERROR, 'data' => $errMsg);
	}

}
