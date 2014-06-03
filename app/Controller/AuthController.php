<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 23.08.12
 * Time: 14:45
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class AuthController
 * @property User User
 */
class AuthController extends AppController {
    public $uses = array('User', 'UserDefaults', 'UserRating', 'BalanceHistory');
    public $components = array('RequestHandler');

    protected function __getJSON($url) {
        $result = array();

        try {
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $json = curl_exec($curl);

            curl_close($curl);

            $result = json_decode($json, true);
        } catch (Exception $e) {

        }

        return $result;
    }

    private function __checkToken($service, $user_service_id, $token) {
        return true;
        if ($service == 'vk') {
            $url = Configure::read('OAuth.vk.check_token_uri');

            $url .= "?access_token={$token}&v=" . Configure::read('OAuth.vk.version');

            $result = $this->__getJSON($url);

            if (empty($result) ||
                isset($result['error']) ||
                !isset($result['response']) ||
                !isset($result['response'][0]) ||
                !isset($result['response'][0]['id'])
            ) {
                return false;
            }

            return ($result['response'][0]['id'] == $user_service_id);
        } else {
            return true;
        }
    }

    private function __auth($service, $user_service_id, $api_token, $api_token_expires = null) {
        App::uses('HttpSocket', 'Network/Http');
        $Http = new HttpSocket();
        
        $success = true;
        $data    = array();

        $conditions = array();
        if ($service == 'fb') {
            $conditions['fb_id'] = $user_service_id;
        } elseif ($service == 'vk') {
            $conditions['vk_id'] = $user_service_id;
        }

        $user = $this->User->find('first', array('conditions' => $conditions,
                                                 'fields'     => array('id', 'vk_id')));

        if (!empty($user) and isset($user['User']) and !empty($user['User'])) {
            $data = $this->User->getUser($user['User']['id'], false);

            if ($service == 'vk') {
                $data = $this->User->update(array('vk_token'         => $api_token,
                                                  'vk_token_expires' => $api_token_expires),
                                            $user['User']['id']);
            }

            // TODO: преверяем была ли сегодня авторизация, иначе начисляем бонус
            
            // Проверяем есть ли пользователь в группе ВК
            $inGroup = json_decode(
                        $Http->get('https://api.vk.com/method/groups.isMember',
                            array(
                                'gid' => 'zupersu',
                                'uid' => $user['User']['vk_id']
                            )
                        )
                    );
            if($inGroup->response == 1){
                //Проверяем получал ли пользователь ИВ за вступление в группу
                $result = $this->BalanceHistory->find('count',
                        array(
                            'conditions' => array(
                                'user_id' => $user['User']['id'],
                                'oper_type' => 3
                            )
                        )
                );
                if(!$result){
                    //Если не получал - начисляем
                    $operType = $this->BalanceHistory->getOperationOptions();
                    $this->BalanceHistory->addOperation(3, 20, $user['User']['id'], $operType[3]);
                }
            }
        } else {
            // Создадим нашего пользователя
            $user_data = array();

            if ($service == 'fb') {
                $user_data['fb_id'] = $user_service_id;
            } elseif ($service == 'vk') {
                $user_data['vk_id']            = $user_service_id;
                $user_data['vk_token']         = $api_token;
                $user_data['vk_token_expires'] = $api_token_expires;
            }

            $user_data['ready'] = false;

            try {
            	$defaults = $this->UserDefaults->getList();
            	extract($defaults);
                $user = $this->User->add(array_merge(compact('credo_id', 'status'), $user_data));
                if ($user) {
                    $user_rating_data               = array();
                    $user_rating_data['user_id']    = $user['id'];
                    $user_rating_data = array_merge(compact('positive_votes'), $user_rating_data);

                    $this->UserRating->save($user_rating_data);
                }
                
            } catch (Exception $e) {
                $user = false;
            }

            if (!$user or empty($user)) {
                $this->response->statusCode(500);
                $success = false;
                $data    = 'failed to create user';
            } else {
                $data = $user;
            }
        }

        if ($success) {
            $success = $this->makeAuth($data['id']);
            if (!$success) {
                $this->response->statusCode(500);
                $data = 'authorization failed';
            }
        }

        return array(
            'success' => $success,
            'data'    => $data
        );
    }

    /**
     * Авторизация
     *
     * POST /auth/
     *
     * Параметры:
     *     service {String} Строка, указывающая на то, какой сервис используется для авторизации.
     *                      Пока возможные варианты — "fb" и "vk"
     *     user_service_id {String} Айди пользователя в указанной системе
     *     api_token {String} токен, полученный в ходе авторизации на клиенте в фейсбуке/вконтакте
     *
     * Ответ:
     *     {TUser}
     */
    public function add() {
        $success = true;
        $data    = array();

        if (!isset($this->request->data['service'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no service specified';
        } else {
            $service = strtolower($this->request->data['service']);
            if ($service != 'fb' and $service != 'vk') {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad service request';
            }
        }

        if ($success and !isset($this->request->data['user_service_id'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user service id specified';
        } elseif ($success) {
            $user_service_id = $this->request->data['user_service_id'];
        }

        if ($success and !isset($this->request->data['api_token'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no api token specified';
        } elseif ($success) {
            $api_token = $this->request->data['api_token'];

            if (isset($this->request->data['api_token_expires'])) {
                $api_token_expires = $this->request->data['api_token_expires'];
            } else {
                $api_token_expires = null;
            }

            // TODO: проверить api_token на кореектность средствами php
            if (!$this->__checkToken($service, $user_service_id, $api_token)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad api token request';
            }
        }

        if ($success) {
            $res = $this->__auth($service, $user_service_id, $api_token, $api_token_expires);

            $success = $res['success'];
            $data    = $res['data'];
        }

        $answer = array('status' => ($success ? 'success' : 'error'),
                        'data'   => $data);

        $this->set(compact('answer'));
    }

    public function vk() {
        $success = true;
        $data    = array();

        if (isset($this->request->query['code'])) {
            $code = $this->request->query['code'];
        } else {
            $success = false;

            $data = 'bad vk api answer';
            if (isset($this->request->query['error'])) {
                $data .= ". error = {$this->request->query['error']}";
            }
            if (isset($this->request->query['error_description'])) {
                $data .= ". error_description = {$this->request->query['error_description']}";
            }
        }

        if ($success) {
            $url = Configure::read('OAuth.vk.access_token_uri') .
                   '?client_id=' . Configure::read('OAuth.vk.app_id') .
                   '&client_secret=' . Configure::read('OAuth.vk.app_secret') .
                   '&code=' . $code .
                   '&redirect_uri=' . Configure::read('OAuth.vk.redirect_uri');

            $params = $this->__getJSON($url);

            if (empty($params)) {
                $success = false;
                $data    = 'bad vk request token answer';
            } elseif (!isset($params['access_token']) || !isset($params['expires_in']) || !isset($params['user_id'])) {
                $success = false;
                if (isset($params['error']) && isset($params['error_description'])) {
                    $data = "vk api error: {$params['error']} - {$params['error_description']}";
                } else {
                    $data = 'bad vk request token answer params';
                }
            }
        }

        if ($success) {
            $access_token = $params['access_token'];
            $expires      = date('Y-m-d H:i:s', strtotime("+{$params['expires_in']} seconds"));
            $user_id      = $params['user_id'];

            try {
                $res = $this->__auth('vk', $user_id, $access_token, $expires);

                $success = $res['success'];
                $data    = $res['data'];
            } catch (Exception $e) {
                $success = false;
                $data = $e->getMessage();
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'),
                        'data'   => $data);

        $this->set(compact('answer'));
    }

    public function vk2() {
        $success = true;
        $data    = array();

        if (!isset($this->request->query['access_token'])) {
            $this->json_render = false;
            $this->render('vk');
            return;
        }

        if (empty($this->request->query)) {
            $success = false;
            $data    = 'bad vk request token answer';
        } elseif (!isset($this->request->query['access_token']) ||
                  !isset($this->request->query['expires_in']) ||
                  !isset($this->request->query['user_id'])
        ) {
            $success = false;
            if (isset($this->request->query['error']) && isset($this->request->query['error_description'])) {
                $data = "vk api error: {$this->request->query['error']} - {$this->request->query['error_description']}";
            } else {
                $data = 'bad vk request token answer params';
            }
        }

        if ($success) {
            $access_token = $this->request->query['access_token'];
            $expires      = date('Y-m-d H:i:s', strtotime("+{$this->request->query['expires_in']} seconds"));
            $user_id      = $this->request->query['user_id'];

            try {
                $res = $this->__auth('vk', $user_id, $access_token, $expires);

                $success = $res['success'];
                $data    = $res['data'];
            } catch (Exception $e) {
                $success = false;
                $data    = $e->getMessage();
            }
        }

        if ($success) {
            $this->redirect(Configure::read('OAuth.vk.success_auth_redirect'));
        }

        $answer = array('status' => ($success ? 'success' : 'error'),
                        'data'   => $data);

        $this->set(compact('answer'));
    }
}