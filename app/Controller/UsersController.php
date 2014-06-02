<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 05.08.12
 * Time: 15:22
 * To change this template use File | Settings | File Templates.
 */
/**
 * Class UsersController
 * @property User User
 * @property Country Country
 * @property City City
 * @property UserRating UserRating
 * @property UserVote UserVote
 * @property UserComment UserComment
 * @property UserMessage UserMessage
 * @property HallOfFame HallOfFame
 * @property Credo Credo
 */
class UsersController extends AppController {
    public $uses = array(
        'User',
        'Country',
        'City',
        'UserRating',
        'UserVote',
        'UserComment',
        'UserMessage',
        'HallOfFame',
        'Credo',
        'UserDefaults'
    );
    public $components = array('RequestHandler');

    /**
     * Проверка на корректность адреса картинки
     *
     * @param $url  Адрес картинки
     *
     * @return array|bool   данные о картинке | False, если адрес картинеи некорректный
     */
    private function __checkPictureUrl($url) {
        try {
            $res = getimagesize($url);
        } catch (Exception $e) {
            $res = false;
        }

        return $res;
    }

    /**
     * Проверка корректности типа картинки
     *
     * @param $image_size   Данные о картинке
     *
     * @return bool Корректен ли тип?
     */
    protected function __checkPictureType($image_size) {
         return isset($this->User->PictureTypes[$image_size[2]]);
    }

    /**
     * Функция генерации набора аватарок на основе адреса картинки
     *
     * @param $url          Адрес картинки
     * @param $user_id      Id пользователя
     * @param $image_size   Данные о картинке
     * @param $x            Координаты по горизонтали верхнего левого угла кропа картинки
     * @param $x2           Координаты по горизонтали нажнего правого угла кропа картинки
     * @param $y            Координаты по вертикали верхнего левого угла кропа картинки
     * @param $y2           Координаты по горизонтали нажнего правого угла кропа картинки
     * @param $w            Ширина кропа картинки
     * @param $h            Высота кропа картинки
     */
    protected function __buildPictureAvatars($url, $user_id, $image_size, $x, $x2, $y, $y2, $w, $h) {
        if ($w === false and $h === false) {
            $w = $image_size[0];
            $h = $image_size[1];
        }
        if ($x2 === false and $y2 === false) {
            $x2 = min($w + $x, $w);
            $y2 = min($h + $y, $h);
        }
        $w = min($x2 - $x, $w);
        $h = min($y2 - $y, $h);

        $im_fn = "imagecreatefrom{$this->User->PictureTypes[$image_size[2]]}";
        $im    = $im_fn($url);

        $res_im = imagecreatetruecolor($w, $h);
        imagecopy($res_im, $im, 0, 0, $x, $y, $w, $h);

        foreach ($this->User->PictureSizes as $filename => $params) {
            $im = imagecreatetruecolor($params['width'], $params['height']);
            imagecopyresampled($im, $res_im, 0, 0, 0, 0, $params['width'], $params['height'], $w, $h);
            if (!file_exists("img/users/{$user_id}")) {
                mkdir("img/users/{$user_id}", 0777, true);
            }
            imagejpeg($im, "img/users/{$user_id}/{$filename}.jpg", 100);
        }

        imagedestroy($im);
        imagedestroy($res_im);
    }

    /**
     * Поиск пользователя по подстроке
     *
     * GET /users/
     *
     * Параметры:
     *     q {String} Строка поиска
     *     page {Number}
     *     per_page {Number}
     *
     * Ответ:
     *     users {Array of TUser},
     *     pages {TPages}
     */
    public function index() {
        $data    = array();
        try {

	        if (!isset($this->request->query['q'])) {
	            throw new Exception('no search string specified');
	        }

            $search_string = $this->request->query['q'];

            $page = ($page = intval($this->request->query('page'))) ? $page : 1;
        	$per_page = ($per_page = intval($this->request->query('per_page'))) ? $per_page : 25;

            $joins = array();
            $joins[] = array(
                'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                'alias'      => 'Country',
                'type'       => 'LEFT',
                'conditions' => array('Country.id = User.country_id')
            );
            $joins[] = array(
                'table'      => $this->City->getDataSource()->fullTableName($this->City),
                'alias'      => 'City',
                'type'       => 'LEFT',
                'conditions' => array('City.id = User.city_id')
            );
            $joins[] = array(
                'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
                'alias'      => 'Credo',
                'type'       => 'LEFT',
                'conditions' => array('Credo.id = User.credo_id')
            );

            $conditions = array();
            if ($search_string) {
                $conditions['OR'] = array();

                $search_array = preg_split('/[\s,.;:]+/i', $search_string);
                if (empty($search_array)) {
                    $search_array = array($search_string);
                }

                foreach ($search_array as $search_word) {
                    $conditions['OR'][] = array('LOWER(TRIM(User.name)) LIKE LOWER(?)' => array("%{$search_word}%"));
                }
            }
            $conditions['User.is_ready'] = 1;

            $users_count = $this->User->find('count', array('conditions' => $conditions,
                                                            'joins'      => $joins));

            if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            } elseif ($users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);

                $users = $this->User->find('all', array('conditions' => $conditions,
                                                        'joins' => $joins,
                                                        'fields' => array(
                                                            'User.id',
                                                            'User.fb_id',
                                                            'User.vk_id',
                                                            'User.name',
                                                            'User.is_ready',
                                                            'User.is_new',
                                                            'User.email',
                                                            'User.status',
                                                            'User.balance',
                                                            'Credo.text',
                                                            'Country.id',
                                                            'Country.name',
                                                            'City.id',
                                                            'City.name',
                                                            'City.region_name',
                                                        ),
                                                        'order' => array(
                                                            'User.name',
                                                            'Country.name',
                                                            'City.name'
                                                        ),
                                                        'limit' => $per_page,
                                                        'offset' => ($page - 1) * $per_page));

                foreach ($users as $user) {
                    $ans = array();

                    $ans['id']       = $user['User']['id'];
                    $ans['fb_id']    = $user['User']['fb_id'];
                    $ans['vk_id']    = $user['User']['vk_id'];
                    $ans['name']     = $user['User']['name'];
                    $ans['is_ready'] = $user['User']['is_ready'];
                    $ans['is_new']   = $user['User']['is_new'];
                    $ans['email']    = $user['User']['email'];
                    $ans['status']   = $user['User']['status'];
                    $ans['balance']   = $user['User']['balance'];
                    $ans['credo']    = $user['Credo']['text'];

                    $ans['country']         = array();
                    $ans['country']['id']   = $user['Country']['id'];
                    $ans['country']['name'] = $user['Country']['name'];

                    $ans['city']                  = array();
                    $ans['city']['id']            = $user['City']['id'];
                    $ans['city']['name']          = $user['City']['name'];
                    $ans['city']['region_name']   = $user['City']['region_name'];

                    $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                    $data['users'][] = $ans;
                }
            } else {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            }
            
        	$this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }
    }

    /**
     * Получение информации о пользователе
     *
     * GET /users/{user_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     TUser
     *
     * @param $id
     */
    public function view($id) {
        $data = array();
        try {

	        if (!$id) {
	            throw new Exception('no user specified');
	        }

            if (strpos($id, ',')) {
                $ids = explode(',', $id);

                $data = $this->User->getUsers($ids, true, array('User.name'), true, $this->currentUserId);
            } else {
                $user = $this->User->getUser($id, false, true, $this->currentUserId);

                if (!$user) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data    = 'bad user request';
                } else {
                    $data = $user;
                }
            }
            
            $this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }

    }

    /**
     * Создать пользователя
     *
     * POST /users/
     *
     * Параметры:
     *     picture {TPicture}
     *     user {TUser}
     *
     * Ответ:
     *     TUser
     *
     * Доступно только для администратора
     */
    public function add() {
        if (!$this->adminCheck()) {
            return;
        }

        $success = true;
        $data    = array();

        if (!isset($this->request->data['user'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } else {
            $user_data = $this->request->data['user'];
            if ((!isset($user_data['fb_id']) or empty($user_data['fb_id'])) and (!isset($user_data['vk_id']) or empty($user_data['vk_id']))) {
                $this->response->statusCode(400);
                $success = false;
                $data = 'no user fb_id or vk_id specified';
            }
        }

        $country = null;
        if ($success and isset($this->request->data['country'])) {
            if (isset($this->request->data['country']['id'])) {
                $country = $this->Country->findById($this->request->data['country']['id']);
            } elseif (isset($this->request->data['country']['code'])) {
                $country = $this->Country->findByCode($this->request->data['country']['code']);
            } else {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'no country specified';
            }
        } elseif ($success) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no country specified';
        }

        if ($success and $country !== null and (empty($country) or $country['Country']['is_deleted'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad country specified';
        }

        $city = null;
        if ($success and isset($this->request->data['city'])) {
            if (isset($this->request->data['city']['id'])) {
                $city = $this->City->findById($this->request->data['city']['id']);
            } else {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'no city specified';
            }
        } elseif ($success) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no city specified';
        }

        if ($success and $city !== null and (empty($city) or $city['City']['is_deleted'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad city specified';
        }

        if ($success and !isset($user_data['credo'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no credo specified';
        } elseif ($success and !mb_strlen($user_data['credo'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'empty credo specified';
        } elseif ($success and mb_strlen($user_data['credo']) > Credo::CREDO_FIELD_LENGTH) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'too long credo';
        }

        if ($success and !isset($this->request->data['picture'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no picture specified';
        } elseif ($success) {
            $picture = $this->request->data['picture'];
            if (!isset($picture['url']) or empty($picture['url'])) {
                $this->response->statusCode(400);
                $success = false;
                $data = 'no picture url specified';
            } elseif (($image_size = $this->__checkPictureUrl($picture['url'])) === false) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad picture url specified';
            } elseif (!$this->__checkPictureType($image_size)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad picture format';
            } elseif (!isset($picture['rect']) or !is_array($picture['rect']) or empty($picture['rect'])) {
                $this->response->statusCode(400);
                $success = false;
                $data = 'no picture rect specified';
            } else {
                $rect = $picture['rect'];
                if ((!isset($rect['h']) or !isset($rect['w']) or !isset($rect['x']) or !isset($rect['y'])) and
                    (!isset($rect['x']) or !isset($rect['y']) or !isset($rect['x2']) or !isset($rect['y2']))
                ) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data = 'bad picture rect parameters specified';
                }
            }
        }

        // создание пользователя
        if ($success) {
            $user_data['is_ready'] = 0;
            $user_data['country']  = $this->request->data['country'];
            $user_data['city']     = $this->request->data['city'];

            try {
            	$defaults = $this->UserDefaults->getList();
            	extract($defaults);
                $user = $this->User->add(array_merge(compact('credo_id', 'status'), $user_data));

                $this->UserRating->create();

                if ($user) {
                    $user_rating_data               = array();
                    $user_rating_data['user_id']    = $user['id'];
                    $user_rating_data['country_id'] = $user['country']['id'];
                    $user_rating_data['city_id']    = $user['city']['id'];
                    $user_rating_data = array_merge(compact('positive_votes'), $user_rating_data);

                    $success = $this->UserRating->save($user_rating_data);
                }
            } catch (Exception $e) {
                $user = false;
            }

            if (!$user) {
                $this->response->statusCode(500);
                $success = false;
                $data    = 'failed to create user';
            } else {
                $data = $user;
            }
        }

        // создание аватарок пользователя
        if ($success) {
            if (!isset($rect['x2']) or !isset($rect['y2'])) {
                $rect['x2'] = $rect['x'] + $rect['w'];
                $rect['y2'] = $rect['y'] + $rect['y'];
            }

            $w  = isset($rect['w'])  ? ($rect['w'])  : false;
            $h  = isset($rect['h'])  ? ($rect['h'])  : false;
            $x2 = isset($rect['x2']) ? ($rect['x2']) : false;
            $y2 = isset($rect['y2']) ? ($rect['y2']) : false;
            $x  = $rect['x'];
            $y  = $rect['y'];

            $this->__buildPictureAvatars($picture['url'], $user['id'], $image_size, $x, $x2, $y, $y2, $w, $h);
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data'   => $data);
        $this->set(compact('answer'));
    }

    /**
     * Обновить пользователя
     *
     * PUT /users/{user_id}/
     *
     * Параметры:
     *     picture {TPicture}
     *     user {TUser}
     *
     * Ответ:
     *     TUser
     *
     * Доступно только авторизованным и только для себя
     *
     * @param $id
     */
    public function edit($id) {
        $success = true;
        $data    = array();

        if (!$this->userCheck($id)) {
            return;
        }

        // костыль для инициализации класса
        $this->Credo;

        if (!$id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } elseif (!isset($this->request->data['user']) or !is_array($this->request->data['user'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user data specified';
        } else {
            $user = $this->User->findById($id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            }
        }

        if ($success and isset($this->request->data['user']['credo'])) {
            if (!mb_strlen($this->request->data['user']['credo'])) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'empty credo specified';
            } elseif (mb_strlen($this->request->data['user']['credo']) > Credo::CREDO_FIELD_LENGTH) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'too long credo';
            }
        }

        if ($success and isset($this->request->data['picture'])) {
            $picture = $this->request->data['picture'];
            if (!isset($picture['url']) or empty($picture['url'])) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'no picture url specified';
            } elseif (($image_size = $this->__checkPictureUrl($picture['url'])) === false) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad picture url specified';
            } elseif (!$this->__checkPictureType($image_size)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad picture format';
            } elseif (!isset($picture['rect']) or !is_array($picture['rect']) or empty($picture['rect'])) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'no picture rect specified';
            } else {
                $rect = $picture['rect'];
                if ((!isset($rect['h']) or !isset($rect['w']) or !isset($rect['x']) or !isset($rect['y'])) and
                    (!isset($rect['x']) or !isset($rect['y']) or !isset($rect['x2']) or !isset($rect['y2']))
                ) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data    = 'bad picture rect parameters specified';
                }
            }
        }

        if ($success) {
            $user_data = $this->request->data['user'];

            try {
                $data = $this->User->update($user_data, $id);

                if (!empty($data['id']) and
                    !empty($data['name']) and
                   (!empty($data['fb_id']) or !empty($data['vk_id'])) and
                    !empty($data['country']) and !empty($data['country']['id']) and
                    !empty($data['city']) and !empty($data['city']['id']) and
                    !empty($data['credo'])) {
                    $is_ready = true;
                } else {
                    $is_ready = false;
                }

                if ($data['is_ready'] != $is_ready) {
                    $data['is_ready'] = $is_ready;
                    $this->User->saveField('is_ready', $is_ready);
                }

                if ($data) {
                    $user_rating = $this->UserRating->findByUserId($id);

                    $user_rating_data = array();
                    if (empty($user_rating)) {
                        $this->UserRating->create();
                        $user_rating_data['user_id'] = $id;
                    } else {
                        $this->UserRating->create();
                        $this->UserRating->id        = $user_rating['UserRating']['id'];
                        $user_rating_data['user_id'] = $user_rating['UserRating']['user_id'];
                    }

                    $user_rating_data['country_id'] = $data['country']['id'];
                    $user_rating_data['city_id']    = $data['city']['id'];

                    $success = $this->UserRating->save($user_rating_data, true, array_keys($user_rating_data));
                }
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success or !$data) {
                $this->response->statusCode(500);
                $success = false;
                $data    = 'failed to save user data';
            } elseif (isset($this->request->data['picture'])) {
                if (!isset($rect['x2']) or !isset($rect['y2'])) {
                    $rect['x2'] = $rect['x'] + $rect['w'];
                    $rect['y2'] = $rect['y'] + $rect['y'];
                }

                $w  = isset($rect['w']) ? ($rect['w']) : false;
                $h  = isset($rect['h']) ? ($rect['h']) : false;
                $x2 = isset($rect['x2']) ? ($rect['x2']) : false;
                $y2 = isset($rect['y2']) ? ($rect['y2']) : false;
                $x  = $rect['x'];
                $y  = $rect['y'];

                $this->__buildPictureAvatars($picture['url'], $id, $image_size, $x, $x2, $y, $y2, $w, $h);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Удаление пользователя
     *
     * DELETE /users/{user_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     bool
     *
     * Доступно только для администратора
     *
     * @param $id
     */
    public function delete($id) {
        $success = true;
        $data    = array();

        if (!$this->adminCheck()) {
            return;
        }

        if (!$id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        }

        if ($success) {
            $user = $this->User->findById($id);

            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data = 'bad user request';
            } else {
                try {
                    $user_id = $user['User']['id'];

                    // TODO: удалить зал славы
                    // TODO: удалить зен-сообщения
                    // TODO: удалить прочее

                    $success = $this->UserRating->deleteAll(array('user_id' => $user_id), false);
                    $success = $success && $this->UserVote->deleteAll(array('participant_id' => $user_id), false);
                    $success = $success && $this->UserComment->deleteAll(array('participant_id' => $user_id), false);
                    // TODO: мягкое удаление сообщений пользователя и сообщений пользователю
                    // $success = $success && $this->UserMessage->deleteAll(array('OR' => array('user_id' => $user_id, 'recipient_id' => $user_id)), false);
                    $success = $success && $this->Auth->deleteAll(array('user_id' => $id), false);
                    $success = $success && $this->User->delete($id);

                    $this->HallOfFame->checkLeader();
                } catch (Exception $e) {
                    $success = false;
                }

                if (!$success) {
                    $this->response->statusCode(500);
                    $data = 'failed to delete user';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Поиск земляков пользователя по городу
     *
     * GET /users/{user_id}/neighbors/city
     *
     * Параметры:
     *     page {Number}
     *     per_page {Number}
     *
     * Ответ:
     *     users {Array of TUser},
     *     pages {TPages}
     *
     * Доступно только для авторизованных
     *
     * @param $user_id
     */
    public function cityNeighbors($user_id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if (!$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } else {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            } elseif (empty($user['User']['city_id'])) {
                $this->response->statusCode(405);
                $success = false;
                $data    = "user didn't specified a city yet";
            }
        }

        if ($success) {
            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $joins   = array();
            $joins[] = array(
                'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                'alias'      => 'Country',
                'type'       => 'LEFT',
                'conditions' => array('Country.id = User.country_id')
            );
            $joins[] = array(
                'table'      => $this->City->getDataSource()->fullTableName($this->City),
                'alias'      => 'City',
                'type'       => 'LEFT',
                'conditions' => array('City.id = User.city_id')
            );
            $joins[] = array(
                'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
                'alias'      => 'Credo',
                'type'       => 'LEFT',
                'conditions' => array('Credo.id = User.credo_id')
            );
            $joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array("UserRating.user_id = User.id")
            );

            $conditions = array();
            $conditions['User.city_id'] = $user['User']['city_id'];
            $conditions['User.id !='] = $user_id;
            $conditions['User.is_ready'] = 1;

            $users_count = $this->User->find('count', array('conditions' => $conditions,
                                                            'joins'      => $joins));

            if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            } elseif ($users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);

                $users = $this->User->find('all', array('conditions' => $conditions,
                                                       'joins'      => $joins,
                                                       'fields'     => array(
                                                           'User.id',
                                                           'User.fb_id',
                                                           'User.vk_id',
                                                           'User.name',
                                                           'User.is_ready',
                                                           'User.is_new',
                                                           'User.email',
                                                           'User.status',
                                                           'Credo.text',
                                                           'Country.id',
                                                           'Country.name',
                                                           'City.id',
                                                           'City.name',
                                                           'City.region_name',
                                                           'IFNULL(UserRating.positive_votes, 0) as likes',
                                                           'IFNULL(UserRating.negative_votes, 0) as dislikes'
                                                       ),
                                                       'order'      => array(
                                                           'City.name',
                                                           'Country.name',
                                                           'User.name',
                                                       ),
                                                       'limit'      => $per_page,
                                                       'offset'     => ($page - 1) * $per_page));

                // Временное решение для полной информации по пользователям
                $joins   = array();
                $joins[] = array(
                    'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                    'alias'      => 'UserRating',
                    'type'       => 'LEFT',
                    'conditions' => array(
                        'UserRating.user_id = User.id'
                    )
                );

                foreach ($users as $user) {
                    // Временное решение для полной информации по пользователям
                    $user_rating_sum = $user[0]['likes'] - $user[0]['dislikes'];

                    // Временное решение для полной информации по пользователям
                    $world_rating = $this->User->find(
                        'first',
                        array(
                             'conditions' => array(
                                 'User.is_ready' => 1,
                                 'OR' => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                     'AND'                                                                           => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                         'User.name <'                                                                 => $user['User']['name']
                                     )
                                 )
                             ),
                             'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                             'joins'      => $joins
                        ));
                    // Временное решение для полной информации по пользователям
                    if (!empty($user['Country']['id'])) {
                        $country_rating = $this->User->find(
                            'first',
                            array(
                                 'conditions' => array(
                                     'User.is_ready' => 1,
                                     'User.country_id' => $user['Country']['id'],
                                     'OR'                    => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                         'AND'                                                                           => array(
                                             'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                             'User.name <'                                                                 => $user['User']['name']
                                         )
                                     )
                                 ),
                                 'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                                 'joins'      => $joins
                            ));
                    } else {
                        $country_rating = array(array('rating' => null));
                    }
                    // Временное решение для полной информации по пользователям
                    if (!empty($user['City']['id'])) {
                        $city_rating = $this->User->find(
                            'first',
                            array(
                                 'conditions' => array(
                                     'User.is_ready' => 1,
                                     'User.city_id' => $user['City']['id'],
                                     'OR'                 => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                         'AND'                                                                           => array(
                                             'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                             'User.name <'                                                                 => $user['User']['name']
                                         )
                                     )
                                 ),
                                 'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                                 'joins'      => $joins
                            ));
                    } else {
                        $city_rating = array(array('rating' => null));
                    }

                    $ans = array();

                    $ans['id']       = $user['User']['id'];
                    $ans['fb_id']    = $user['User']['fb_id'];
                    $ans['vk_id']    = $user['User']['vk_id'];
                    $ans['name']     = $user['User']['name'];
                    $ans['is_ready'] = $user['User']['is_ready'];
                    $ans['is_new']   = $user['User']['is_new'];
                    $ans['email']    = $user['User']['email'];
                    $ans['status']   = $user['User']['status'];
                    $ans['credo']    = $user['Credo']['text'];

                    $ans['country']         = array();
                    $ans['country']['id']   = $user['Country']['id'];
                    $ans['country']['code'] = $user['Country']['code'];
                    $ans['country']['name'] = $user['Country']['name'];

                    $ans['city']                  = array();
                    $ans['city']['id']            = $user['City']['id'];
                    $ans['city']['name']          = $user['City']['name'];
                    $ans['city']['region_name']   = $user['City']['region_name'];

                    // Временное решение для полной информации по пользователям
                    $ans['likes']    = intval($user[0]['likes']);
                    $ans['dislikes'] = intval($user[0]['dislikes']);

                    // Временное решение для полной информации по пользователям
                    $ans['world_position']   = intval($world_rating[0]['rating']);
                    $ans['country_position'] = intval($country_rating[0]['rating']);
                    $ans['city_position']    = intval($city_rating[0]['rating']);

                    $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                    $data['users'][] = $ans;
                }
            } else {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Поиск земляков пользователя по городу
     *
     * GET /users/{user_id}/neighbors/country
     *
     * Параметры:
     *     page {Number}
     *     per_page {Number}
     *
     * Ответ:
     *     users {Array of TUser},
     *     pages {TPages}
     *
     * Доступно только для авторизованных
     *
     * @param $user_id
     */
    public function countryNeighbors($user_id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if (!$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } else {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            } elseif (empty($user['User']['country_id'])) {
                $this->response->statusCode(405);
                $success = false;
                $data    = "user didn't specified a country yet";
            }
        }

        if ($success) {
            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $joins   = array();
            $joins[] = array(
                'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                'alias'      => 'Country',
                'type'       => 'LEFT',
                'conditions' => array('Country.id = User.country_id')
            );
            $joins[] = array(
                'table'      => $this->City->getDataSource()->fullTableName($this->City),
                'alias'      => 'City',
                'type'       => 'LEFT',
                'conditions' => array('City.id = User.city_id')
            );
            $joins[] = array(
                'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
                'alias'      => 'Credo',
                'type'       => 'LEFT',
                'conditions' => array('Credo.id = User.credo_id')
            );
            $joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array("UserRating.user_id = User.id")
            );

            $conditions = array();
            $conditions['User.country_id'] = $user['User']['country_id'];
            $conditions['User.id !='] = $user_id;
            $conditions['User.is_ready'] = 1;

            $users_count = $this->User->find('count', array('conditions' => $conditions,
                                                           'joins'      => $joins));

            if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            } elseif ($users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);

                $users = $this->User->find('all', array('conditions' => $conditions,
                                                       'joins'      => $joins,
                                                       'fields'     => array(
                                                           'User.id',
                                                           'User.fb_id',
                                                           'User.vk_id',
                                                           'User.name',
                                                           'User.is_ready',
                                                           'User.is_new',
                                                           'User.email',
                                                           'User.status',
                                                           'Credo.text',
                                                           'Country.id',
                                                           'Country.name',
                                                           'City.id',
                                                           'City.name',
                                                           'City.region_name',
                                                           'IFNULL(UserRating.positive_votes, 0) as likes',
                                                           'IFNULL(UserRating.negative_votes, 0) as dislikes'
                                                       ),
                                                       'order'      => array(
                                                           'City.name',
                                                           'Country.name',
                                                           'User.name',
                                                       ),
                                                       'limit'      => $per_page,
                                                       'offset'     => ($page - 1) * $per_page));

                // Временное решение для полной информации по пользователям
                $joins   = array();
                $joins[] = array(
                    'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                    'alias'      => 'UserRating',
                    'type'       => 'LEFT',
                    'conditions' => array(
                        'UserRating.user_id = User.id'
                    )
                );

                foreach ($users as $user) {
                    // Временное решение для полной информации по пользователям
                    $user_rating_sum = $user[0]['likes'] - $user[0]['dislikes'];

                    // Временное решение для полной информации по пользователям
                    $world_rating = $this->User->find(
                        'first',
                        array(
                             'conditions' => array(
                                 'User.is_ready' => 1,
                                 'OR' => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                     'AND'                                                                           => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                         'User.name <'                                                                 => $user['User']['name']
                                     )
                                 )
                             ),
                             'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                             'joins'      => $joins
                        ));
                    // Временное решение для полной информации по пользователям
                    if (!empty($user['Country']['id'])) {
                        $country_rating = $this->User->find(
                            'first',
                            array(
                                 'conditions' => array(
                                     'User.is_ready' => 1,
                                     'User.country_id' => $user['Country']['id'],
                                     'OR'                    => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                         'AND'                                                                           => array(
                                             'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                             'User.name <'                                                                 => $user['User']['name']
                                         )
                                     )
                                 ),
                                 'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                                 'joins'      => $joins
                            ));
                    } else {
                        $country_rating = array(array('rating' => null));
                    }
                    // Временное решение для полной информации по пользователям
                    if (!empty($user['City']['id'])) {
                        $city_rating = $this->User->find(
                            'first',
                            array(
                                 'conditions' => array(
                                     'User.is_ready' => 1,
                                     'User.city_id' => $user['City']['id'],
                                     'OR'                 => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                         'AND'                                                                           => array(
                                             'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                             'User.name <'                                                                 => $user['User']['name']
                                         )
                                     )
                                 ),
                                 'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                                 'joins'      => $joins
                            ));
                    } else {
                        $city_rating = array(array('rating' => null));
                    }

                    $ans = array();

                    $ans['id']       = $user['User']['id'];
                    $ans['fb_id']    = $user['User']['fb_id'];
                    $ans['vk_id']    = $user['User']['vk_id'];
                    $ans['name']     = $user['User']['name'];
                    $ans['is_ready'] = $user['User']['is_ready'];
                    $ans['is_new']   = $user['User']['is_new'];
                    $ans['email']    = $user['User']['email'];
                    $ans['status']   = $user['User']['status'];
                    $ans['credo']    = $user['Credo']['text'];

                    $ans['country']         = array();
                    $ans['country']['id']   = $user['Country']['id'];
                    $ans['country']['name'] = $user['Country']['name'];

                    $ans['city']                  = array();
                    $ans['city']['id']            = $user['City']['id'];
                    $ans['city']['name']          = $user['City']['name'];
                    $ans['city']['region_name']   = $user['City']['region_name'];

                    // Временное решение для полной информации по пользователям
                    $ans['likes']    = intval($user[0]['likes']);
                    $ans['dislikes'] = intval($user[0]['dislikes']);

                    // Временное решение для полной информации по пользователям
                    $ans['world_position']   = intval($world_rating[0]['rating']);
                    $ans['country_position'] = intval($country_rating[0]['rating']);
                    $ans['city_position']    = intval($city_rating[0]['rating']);

                    $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                    $data['users'][] = $ans;
                }
            } else {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получить список друзей пользователя
     *
     * GET /users/{user_id}/friends
     *
     * Параметры:
     *     page {Number}
     *     per_page {Number}
     *     fb_id {String} фейсбук-айди друзей, через запятую
     *
     * Ответ:
     *     users {Array of TUser},
     *     pages {TPages}
     *
     * Доступно только для авторизованных
     *
     * @param $user_id
     */
    public function friends($user_id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if (!$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } else {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            }
        }

        if ($success) {
            $is_fb = true;

            if (isset($this->request->query['fb_id'])) {
                $fb_id = $this->request->query['fb_id'];
                $fb_ids = explode(',', $fb_id);

                if (empty($fb_ids)) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data    = 'empty fb_id specified';
                }
            } elseif (isset($this->request->query['vk_id'])) {
                $is_fb = false;

                $vk_id = $this->request->query['vk_id'];
                $vk_ids = explode(',', $vk_id);

                if (empty($vk_ids)) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data    = 'empty vk_id specified';
                }
            } else {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'no fb_id or vk_id specified';
            }
        }

        if ($success) {
            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $order = array('User.name');
            $conditions = array(
                'User.is_ready' => 1,
                'User.id !='    => $user_id
            );
            if ($is_fb) {
                $conditions['User.fb_id'] = $fb_ids;
            } else {
                $conditions['User.vk_id'] = $vk_ids;
            }

            $friends = $this->User->find('all',
                                         array(
                                              'conditions' => $conditions,
                                              'fields'     => array('User.id'),
                                              'order'      => $order
                                         ));

            $friends_count = count($friends);
            $friends = array_slice($friends, ($page - 1) * $per_page, $per_page);

            $friends_ids = Set::extract('/User/id', $friends);

            $data = array();
            if (!empty($friends_ids)) {
                $data['users'] = $this->User->getUsers($friends_ids, true, $order, true, $this->currentUserId);
            } else {
                $data['users'] = array();
            }
            $data['pages'] = array('per_page' => $per_page,
                                   'page'     => $page,
                                   'total'    => $friends_count);
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получить список "самых" пользователей
     *
     * GET /users/most
     *
     * Параметры:
     *     country  {String} Код страны, в пределах которой будут искаться эти пользователи
     *     city     {String} Айди города, в пределах которого будут искаться эти пользователи
     *
     * Ответ:
     *     scandalous   {TUser}
     *     positive     {TUser}
     *     negative     {TUser}
     */
    public function most() {
        $success = true;
        $data    = array();

        $city    = null;
        $country = null;
        if (isset($this->request->query['city'])) {
            $city = $this->City->findById((int) $this->request->query['city']);

            if (empty($city) or $city['City']['is_deleted']) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad city specified';
            }
        } elseif (isset($this->request->query['country'])) {
            $country = $this->Country->findByCode(preg_replace('/^\s+|\s+$/', '', $this->request->query['country']));

            if (empty($country) or $country['Country']['is_deleted']) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad country specified';
            }
        }

        if ($success) {
            $joins      = array();
            $joins[]    = array(
                'table'      => $this->User->getDataSource()->fullTableName($this->User),
                'alias'      => 'User',
                'type'       => 'INNER',
                'conditions' => array('User.id = UserComment.participant_id')
            );
            $conditions = array(
                'UserComment.deleted' => 0,
                'User.is_ready'       => 1
            );
            if ($city) {
                $conditions['User.city_id'] = $city['City']['id'];
            } elseif ($country) {
                $conditions['User.country_id'] = $city['Country']['id'];
            }
            $scandalous_id = $this->UserComment->find('first',
                                                      array(
                                                           'conditions' => $conditions,
                                                           'joins'      => $joins,
                                                           'fields'     => array('UserComment.participant_id'),
                                                           'group'      => array('UserComment.participant_id'),
                                                           'order'      => array(
                                                               'COUNT(DISTINCT UserComment.id) DESC',
                                                               'User.name'
                                                           )
                                                      ));
            if ($scandalous_id) {
                $scandalous_id = $scandalous_id['UserComment']['participant_id'];
            } else {
                $scandalous_id = null;
            }

            $joins      = array();
            $joins[]    = array(
                'table'      => $this->UserVote->getDataSource()->fullTableName($this->UserVote),
                'alias'      => 'UserVote',
                'type'       => 'INNER',
                'conditions' => array('UserVote.user_id = User.id')
            );
            $conditions = array(
                'UserVote.vote >' => 0,
                'User.is_ready'   => 1
            );
            if ($city) {
                $conditions['User.city_id'] = $city['City']['id'];
            } elseif ($country) {
                $conditions['User.country_id'] = $city['Country']['id'];
            }

            // TODO: оптимизировать запрос
//            $db     = $this->User->getDataSource();
//            $select = $db->buildStatement(
//                array(
//                     'fields'     => array('User.id'),
//                     'table'      => $db->fullTableName($this->User),
//                     'alias'      => 'User',
//                     'limit'      => 1,
//                     'offset'     => null,
//                     'joins'      => $joins,
//                     'conditions' => $conditions,
//                     'order'      => array(
//                         'SUM(UserVote.vote) DESC',
//                         'User.name'
//                     ),
//                     'group'      => array('User.id'),
//                ),
//                $this->User
//            );
//
//            print_r($select);
//            die;

            $positive_id = $this->User->find('first',
                                             array(
                                                  'conditions' => $conditions,
                                                  'joins'      => $joins,
                                                  'fields'     => array('User.id'),
                                                  'group'      => array('User.id'),
                                                  'order'      => array(
                                                      'SUM(UserVote.vote) DESC',
                                                      'User.name'
                                                  )
                                             ));

            if ($positive_id) {
                $positive_id = $positive_id['User']['id'];
            } else {
                $positive_id = null;
            }

            $conditions = array(
                'UserVote.vote <' => 0,
                'User.is_ready'   => 1
            );
            if ($city) {
                $conditions['User.city_id'] = $city['City']['id'];
            } elseif ($country) {
                $conditions['User.country_id'] = $city['Country']['id'];
            }
            $negative_id = $this->User->find('first',
                                             array(
                                                  'conditions' => $conditions,
                                                  'joins'      => $joins,
                                                  'fields'     => array('User.id'),
                                                  'group'      => array('User.id'),
                                                  'order'      => array(
                                                      'SUM(UserVote.vote) ASC',
                                                      'User.name'
                                                  )
                                             ));
            if ($negative_id) {
                $negative_id = $negative_id['User']['id'];
            } else {
                $negative_id = null;
            }

            $users = $this->User->getUsers(array($scandalous_id, $positive_id, $negative_id), true, array('User.name'), true, $this->currentUserId);
            $users = Set::combine($users, '{n}.id', '{n}');

            $scandalous = ($scandalous_id and isset($users[$scandalous_id])) ? $users[$scandalous_id] : null;
            $positive   = ($positive_id   and isset($users[$positive_id]))   ? $users[$positive_id]   : null;
            $negative   = ($negative_id   and isset($users[$negative_id]))   ? $users[$negative_id]   : null;

            $data = array(
                'scandalous' => $scandalous,
                'positive'   => $positive,
                'negative'   => $negative
            );
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получить список пользователей в зале славы
     *
     * GET /users/winners
     *
     * Параметры:
     *     page {Number}
     *     per_page {Number}
     *
     * Ответ:
     *     users {Array of TUser},
     *     pages {TPages}
     */
    public function winners() {
        $success = true;
        $data    = array();

        if ($success) {
            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $joins   = array();
            $joins[] = array(
                'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                'alias'      => 'Country',
                'type'       => 'LEFT',
                'conditions' => array('Country.id = User.country_id')
            );
            $joins[] = array(
                'table'      => $this->City->getDataSource()->fullTableName($this->City),
                'alias'      => 'City',
                'type'       => 'LEFT',
                'conditions' => array('City.id = User.city_id')
            );
            $joins[] = array(
                'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
                'alias'      => 'Credo',
                'type'       => 'LEFT',
                'conditions' => array('Credo.id = User.credo_id')
            );
            $joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array('UserRating.user_id = User.id')
            );

            $joins[] = array(
                'table'      => $this->HallOfFame->getDataSource()->fullTableName($this->HallOfFame),
                'alias'      => 'HallOfFame',
                'type'       => 'INNER',
                'conditions' => array('HallOfFame.user_id = User.id',
                                      'NOT' => array('HallOfFame.achieved' => null))
            );

            $conditions = array();
            $conditions['User.is_ready']           = 1;
            $conditions['User.is_in_hall_of_fame'] = 1;

            $users_count = $this->User->find('count', array('conditions' => $conditions,
                                                            'joins'      => $joins));

            if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            } elseif ($users_count > 0) {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);

                $users = $this->User->find('all', array('conditions' => $conditions,
                                                       'joins'      => $joins,
                                                       'fields'     => array(
                                                           'User.id',
                                                           'User.fb_id',
                                                           'User.vk_id',
                                                           'User.name',
                                                           'User.is_ready',
                                                           'User.is_new',
                                                           'User.email',
                                                           'User.status',
                                                           'Credo.text',
                                                           'Country.id',
                                                           'Country.name',
                                                           'City.id',
                                                           'City.name',
                                                           'City.region_name',
                                                           'IFNULL(UserRating.positive_votes, 0) as likes',
                                                           'IFNULL(UserRating.negative_votes, 0) as dislikes',
                                                           'HallOfFame.achieved'
                                                       ),
                                                       'order'      => array(
                                                           'HallOfFame.achieved DESC',
                                                           'User.name',
                                                       ),
                                                       'limit'      => $per_page,
                                                       'offset'     => ($page - 1) * $per_page));

                foreach ($users as $user) {
                    $ans = array();

                    $ans['id']       = $user['User']['id'];
                    $ans['fb_id']    = $user['User']['fb_id'];
                    $ans['vk_id']    = $user['User']['vk_id'];
                    $ans['name']     = $user['User']['name'];
                    $ans['is_ready'] = $user['User']['is_ready'];
                    $ans['is_new']   = $user['User']['is_new'];
                    $ans['email']    = $user['User']['email'];
                    $ans['status']   = $user['User']['status'];
                    $ans['credo']    = $user['Credo']['text'];

                    $ans['country']         = array();
                    $ans['country']['id']   = $user['Country']['id'];
                    $ans['country']['name'] = $user['Country']['name'];

                    $ans['city']                  = array();
                    $ans['city']['id']            = $user['City']['id'];
                    $ans['city']['name']          = $user['City']['name'];
                    $ans['city']['region_name']   = $user['City']['region_name'];

                    $ans['likes']    = intval($user[0]['likes']);
                    $ans['dislikes'] = untval($user[0]['dislikes']);

                    $ans['hall_of_fame_date'] = $user['HallOfFame']['achieved'];

                    $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                    $data['users'][] = $ans;
                }
            } else {
                $data['users'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $users_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }


    protected function __getJSON($url) {
        $result = array();

        try {
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);

            $json = curl_exec($curl);

            echo 'curl error - ' . curl_error($curl);

            curl_close($curl);

            $result = json_decode($json, true);
        } catch (Exception $e) {
            echo 'Exception: ' . $e->getMessage();
        }

        return $result;
    }
    public function post($user_id) {
        try {
            $this->json_render = false;

            $text = $this->request->data['text'];

            echo "text = <pre>{$text}</pre><br>";

            $user = $this->User->findById($user_id);
            $access_token = $user['User']['vk_token'];

            $url = 'https://api.vk.com/method/wall.post' .
                '?owner_id=' . $user['User']['vk_id'] .
                '&friends_only=' . '0' .
                '&message=' . urlencode($text) .
                '&access_token=' . $access_token;

            $params = $this->__getJSON($url);

            echo 'url = <pre>';
            print_r($url);
            echo '</pre><br>';

            echo 'params = <pre>';
            print_r($params);
            echo '</pre><br>';


        } catch (Exception $e) {
            echo 'Great Exception:' . $e->getMessage();
        }
    }
}