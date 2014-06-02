<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 08.04.14
 * Time: 22:43
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class CredoController
 * @property User            User
 * @property UserRating      UserRating
 * @property City            City
 * @property Country         Country
 * @property Credo           Credo
 * @property ChatMessage     ChatMessage
 * @property ChatMessageRead ChatMessageRead
 */
class CredoController extends AppController {
    public $uses = array(
        'User',
        'UserRating',
        'City',
        'Country',
        'Credo',
        'ChatMessage',
        'ChatMessageRead',
    );
    public $components = array('RequestHandler');

    /**
     * Поиск кредо по подстроке
     *
     * GET /credo/
     *
     * Параметры:
     *     q {String} Строка поиска
     *     page {Number}
     *     per_page {Number}
     *
     * Ответ:
     *     credo {Array of String},
     *     pages {TPages}
     */
    public function index() {
        $success = true;
        $data    = array();

        if (!isset($this->request->query['q'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no search string specified';
        }

        if ($success) {
            $search_string = $this->request->query['q'];

            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $conditions = array(
                'text LIKE ?' => array("%{$search_string}%")
            );

            $credos_count = $this->Credo->find('count', array('conditions' => $conditions));

            if (($page - 1) * $per_page >= $credos_count && $credos_count > 0) {
                $data['credo'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $credos_count);
            } elseif ($credos_count > 0) {
                $data['credo'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $credos_count);

                $credos = $this->Credo->find('all', array('conditions' => $conditions,
                                                          'fields'     => array('Credo.text'),
                                                          'order'      => array('Credo.text'),
                                                          'limit'      => $per_page,
                                                          'offset'     => ($page - 1) * $per_page));

                foreach ($credos as $credo) {
                    $data['credo'][] = $credo['Credo']['text'];
                }
            } else {
                $data['credo'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $credos_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получение информации о использовании кредо
     *
     * GET /credo/{user_id}
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     users {Array of TUser}
     *     pages {TPage}
     *
     * @param $user_id
     */
    public function view($user_id) {
        $success = true;
        $data    = array();

        if (!$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } else {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user) or $user['User']['is_ready'] == 0 or $user['User']['is_in_hall_of_fame'] == 1) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            } else {
                $credo_id = $user['User']['credo_id'];
            }
        }

        // костыль для инициализации класса
        $this->Credo;

        if ($success) {
            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $data['users'] = array();
            $data['pages'] = array('per_page' => $per_page,
                                   'page'     => $page);
            if (!$credo_id) {
                $data['pages']['total'] = 0;
            } else {
                $conditions = array(
                    'User.is_ready'           => 1,
                    'User.is_in_hall_of_fame' => 0,
                    'User.credo_id'           => $credo_id
                );

                $joins       = array();
                $joins[]     = array(
                    'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                    'alias'      => 'UserRating',
                    'type'       => 'LEFT',
                    'conditions' => array('UserRating.user_id = User.id')
                );
                $joins[]     = array(
                    'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                    'alias'      => 'Country',
                    'type'       => 'LEFT',
                    'conditions' => array('Country.id = User.country_id',
                                          'Country.is_deleted' => 0)
                );
                $joins[]     = array(
                    'table'      => $this->City->getDataSource()->fullTableName($this->City),
                    'alias'      => 'City',
                    'type'       => 'LEFT',
                    'conditions' => array('City.id = User.city_id',
                                          'City.is_deleted' => 0)
                );
                $joins[]     = array(
                    'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
                    'alias'      => 'Credo',
                    'type'       => 'LEFT',
                    'conditions' => array('Credo.id = User.credo_id')
                );
                $users_count = $this->User->find('count', array('conditions' => $conditions,
                                                                'joins'      => $joins));

                if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
                    $data['pages']['total'] = $users_count;
                } elseif ($users_count > 0) {
                    $data['pages']['total'] = $users_count;

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
                                                                'Country.code',
                                                                'Country.name',
                                                                'City.id',
                                                                'City.name',
                                                                'City.region_name',
                                                                'City.longitude',
                                                                'City.latitude',
                                                                'IFNULL(UserRating.positive_votes, 0) as likes',
                                                                'IFNULL(UserRating.negative_votes, 0) as dislikes',
                                                            ),
                                                            'order'      => array(
                                                                'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                                                'User.name'
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
                                                           'User.is_ready'           => 1,
                                                           'User.is_in_hall_of_fame' => 0,
                                                           'OR'                      => array(
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
                                                                 'User.is_ready'           => 1,
                                                                 'User.is_in_hall_of_fame' => 0,
                                                                 'User.country_id'         => $user['Country']['id'],
                                                                 'OR'                      => array(
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
                                                              'User.is_ready'           => 1,
                                                              'User.is_in_hall_of_fame' => 0,
                                                              'User.city_id'            => $user['City']['id'],
                                                              'OR'                      => array(
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
                        $ans['city']['position']      = array();
                        $ans['city']['position']['x'] = $user['City']['longitude'];
                        $ans['city']['position']['y'] = $user['City']['latitude'];

                        $ans['likes']    = $user[0]['likes'];
                        $ans['dislikes'] = $user[0]['dislikes'];

                        // Временное решение для полной информации по пользователям
                        $ans['world_position']   = $world_rating[0]['rating'];
                        $ans['country_position'] = $country_rating[0]['rating'];
                        $ans['city_position']    = $city_rating[0]['rating'];

                        $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                        $data['users'][] = $ans;
                    }
                } else {
                    $data['pages']['total'] = $users_count;
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Лидеры кредо
     *
     * GET /rating/credo/
     *
     * Параметры:
     *     per_page {Number} Кол-во результатов на страницу
     *     page {Number} Номер страницы начиная с первой
     *
     * Ответ:
     *     leader {credo, rating, leader {TUser}},
     *     credos {Array of {credo, rating, leader {TUser}}},
     *     pages {TPages}
     */
    public function rating() {
        $success = true;
        $data    = array();

        if (isset($this->request->query['per_page'])) {
            $per_page = (int)$this->request->query['per_page'];
        }
        $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

        if (isset($this->request->query['page'])) {
            $page = (int)$this->request->query['page'];
        }
        $page = (isset($page) and $page > 0) ? $page : 1;

        $conditions = array();

        $sub_db = $this->User->getDataSource();
        $sub_joins = array();
        $sub_joins[] = array(
            'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
            'alias'      => 'UserRating_sub',
            'type'       => 'LEFT',
            'conditions' => array('UserRating_sub.user_id = User_sub.id')
        );
        $sub = $sub_db->buildStatement(
                      array(
                          'fields'     => array('User_sub.id'),
                          'table'      => $sub_db->fullTableName($this->User),
                          'alias'      => 'User_sub',
                          'limit'      => 1,
                          'offset'     => null,
                          'joins'      => $sub_joins,
                          'conditions' => array(
                              'User_sub.credo_id = Credo.id',
                              'User_sub.is_ready'           => 1,
                              'User_sub.is_in_hall_of_fame' => 0
                          ),
                          'order'      => array('IFNULL(UserRating_sub.positive_votes - UserRating_sub.negative_votes, 0) DESC',
                                                'User_sub.name'),
                          'group'      => null
                      ),
                      $this->User
        );

        $joins   = array();
        $joins[] = array(
            'table'      => $this->User->getDataSource()->fullTableName($this->User),
            'alias'      => 'User',
            'type'       => 'LEFT',
            'conditions' => array('User.credo_id = Credo.id',
                                  'User.is_ready'           => 1,
                                  'User.is_in_hall_of_fame' => 0)
        );

        $credos_count = $this->Credo->find('count', array('conditions' => $conditions,
                                                          'joins'      => $joins,
                                                          'group'      => array(
                                                              'Credo.text'
                                                          )));

        // Если не первая страница рейтинга, то лидера придётся выбирать отдельно
        $leader = array();
        if ($page != 1) {
            $leader = $this->User->find('first', array('conditions' => $conditions,
                                                       'joins'      => $joins,
                                                       'fields'     => array(
                                                           'Credo.text',
                                                           'COUNT(DISTINCT User.id) as cnt',
                                                           "({$sub}) as user_id"
                                                       ),
                                                       'order'      => array(
                                                           'COUNT(DISTINCT User.id) DESC',
                                                           'Credo.text'
                                                       ),
                                                       'group'      => array(
                                                           'Credo.text'
                                                       )));

            if (!empty($leader)) {
                $leader = array(
                    'credo' => $leader['Credo']['text'],
                    'rating' => $leader[0]['cnt'],
                    'leader' => $leader[0]['user_id']
                );
            }
        }

        if (($page - 1) * $per_page >= $credos_count && $credos_count > 0) {
            $data['leader'] = $leader;
            $data['credos'] = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $credos_count
            );
        } elseif ($credos_count > 0) {
            $data['leader'] = $leader;
            $data['credos'] = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $credos_count
            );

            $users = $this->Credo->find('all', array('conditions' => $conditions,
                                                     'joins'      => $joins,
                                                     'fields'     => array(
                                                         'Credo.text',
                                                         'COUNT(DISTINCT User.id) as cnt',
                                                         "({$sub}) as user_id"
                                                     ),
                                                     'order'      => array(
                                                         'COUNT(DISTINCT User.id) DESC',
                                                         'Credo.text'
                                                     ),
                                                     'group'      => array(
                                                         'Credo.text'
                                                     ),
                                                     'limit'      => $per_page,
                                                     'offset'     => ($page - 1) * $per_page));

            foreach ($users as $user) {
                $ans = array();

                $ans['credo']  = $user['Credo']['text'];
                $ans['rating'] = $user[0]['cnt'];
                $ans['leader'] = $user[0]['user_id'];

                $data['credos'][] = $ans;

                if ($page == 1 and empty($data['leader'])) {
                    $data['leader'] = $ans;
                }
            }
        } else {
            $data['leader'] = $leader;
            $data['credos'] = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $credos_count
            );
        }

        $answer = array('status' => ($success ? 'success' : 'error'),
                        'data'   => $data);

        $this->set(compact('answer'));
    }

    /**
     * Получение чата по кредо
     *
     * GET /users/messages/credo/
     *
     * Параметры:
     *     per_page {Number}
     *     page {Number}
     *
     * Ответ:
     *     messages {Array of TMessage}
     *     pages {TPage}
     *
     * Доступно только для авторизованных
     */
    public function messages() {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $currentUser = $this->User->findById($this->currentUserId);
        if (!$currentUser) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad user request';
        } elseif (empty($currentUser['User']['credo_id'])) {
            $this->response->statusCode(405);
            $success = false;
            $data    = 'current user has no credo';
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
                'table'      => $this->ChatMessageRead->getDataSource()->fullTableName($this->ChatMessageRead),
                'alias'      => 'ChatMessageRead',
                'type'       => 'LEFT',
                'conditions' => array('ChatMessageRead.chat_message_id = ChatMessage.id',
                                      'ChatMessageRead.user_id' => $this->currentUserId)
            );

            // костыль для инициализации класса
            $this->ChatMessage;
            $conditions = array(
                'ChatMessage.chat_id'       => ChatMessage::ID_CHAT_CREDO,
                'ChatMessage.chat_group_id' => $currentUser['User']['credo_id']
            );

            $messages_count = $this->ChatMessage->find('count', array('conditions' => $conditions,
                                                                      'joins'      => $joins));

            if (($page - 1) * $per_page >= $messages_count && $messages_count > 0) {
                $data['messages'] = array();
                $data['pages']    = array('per_page' => $per_page,
                                          'page'     => $page,
                                          'total'    => $messages_count);
            } elseif ($messages_count > 0) {
                $data['messages'] = array();
                $data['pages']    = array('per_page' => $per_page,
                                          'page'     => $page,
                                          'total'    => $messages_count);

                $messages = $this->ChatMessage->find('all', array('conditions' => $conditions,
                                                                  'joins'      => $joins,
                                                                  'fields'     => array(
                                                                      'ChatMessage.id',
                                                                      'ChatMessage.text',
                                                                      'ChatMessage.user_id',
                                                                      'ChatMessage.created',
                                                                      '(CASE WHEN ChatMessageRead.chat_message_id IS NOT NULL THEN ' .
                                                                      ChatMessage::ID_STATUS_READ . ' ELSE ' .
                                                                      ChatMessage::ID_STATUS_UNREAD . ' END) as status'
                                                                  ),
                                                                  'order'      => array(
                                                                      'ChatMessage.created DESC',
                                                                      'ChatMessage.id DESC'
                                                                  ),
                                                                  'limit'      => $per_page,
                                                                  'offset'     => ($page - 1) * $per_page));

                foreach ($messages as $row) {
                    $res           = array();
                    $res['id']     = $row['ChatMessage']['id'];
                    $res['text']   = htmlspecialchars($row['ChatMessage']['text']);
                    $res['status'] = ($row['ChatMessage']['user_id'] == $this->currentUserId ? ChatMessage::ID_STATUS_READ : $row[0]['status']);
                    $res['date']   = $row['ChatMessage']['created'];

                    $res['author']       = array();
                    $res['author']['id'] = $row['ChatMessage']['user_id'];

                    $data['messages'][] = $res;
                }
            } else {
                $data['messages'] = array();
                $data['pages']    = array('per_page' => $per_page,
                                          'page'     => $page,
                                          'total'    => $messages_count);
            }

            if (!empty($data['messages'])) {
                $leader = $this->User->getCredoLeader($currentUser['User']['credo_id']);

                $data['leader'] = $leader;
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Добавление сообщения в чат по кредо
     *
     * POST /users/messages/credo/
     *
     * Параметры:
     *     {TMessage}
     *
     * Ответ:
     *     TMessage
     *
     * Доступно только для авторизованных
     */
    public function messageAdd() {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $currentUser = $this->User->findById($this->currentUserId);
        if (!$currentUser) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad user request';
        } elseif (empty($currentUser['User']['credo_id'])) {
            $this->response->statusCode(405);
            $success = false;
            $data    = 'current user has no credo';
        }

        if ($success && !isset($this->request->data['text'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no text specified';
        }

        if ($success) {
            try {
                $this->ChatMessage->create();

                $user_data                  = array();
                $user_data['chat_group_id'] = $currentUser['User']['credo_id'];
                $user_data['chat_id']       = ChatMessage::ID_CHAT_CREDO;
                $user_data['user_id']       = $this->currentUserId;
                $user_data['text']          = $this->request->data['text'];

                $success = $this->ChatMessage->save(array('ChatMessage' => $user_data));
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to save chat message';
            } else {
                $data = $this->ChatMessage->getMessage($this->ChatMessage->id, $this->currentUserId);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get chat message';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Редактирование сообщения в чате по кредо
     *
     * PUT /users/messages/credo/{chat_message_id}/
     *
     * Параметры:
     *     {TMessage}
     *
     * Ответ:
     *     TMessage
     *
     * @param $id
     *
     * Доступно только для авторизованных
     */
    public function messageEdit($id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $currentUser = $this->User->findById($this->currentUserId);
        if (!$currentUser) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad user request';
        } elseif (empty($currentUser['User']['credo_id'])) {
            $this->response->statusCode(405);
            $success = false;
            $data    = 'current user has no credo';
        }

        $message = $this->ChatMessage->getMessage($id, $this->currentUserId);
        if ($success and (!$message or empty($message))) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad chat message request';
        }

        if ($success and !isset($this->request->data['status'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no status specified';
        } elseif ($success and !in_array($this->request->data['status'], ChatMessage::$statuses)) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad status specified';
        } elseif ($success and $this->request->data['status'] == ChatMessage::ID_STATUS_UNREAD and $message['author']['id'] == $this->currentUserId) {
            $this->response->statusCode(400);
            $success = false;
            $data    = "can't unread your chat messages";
        }

        if ($success and $this->request->data['status'] == $message['status']) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no changes specified';
        }

        if ($success) {
            $status = $this->request->data['status'];

            $this->ChatMessage->id = $id;

            try {
                switch ($status) {
                    case ChatMessage::ID_STATUS_READ:
                        $this->ChatMessageRead->create();
                        $success = $this->ChatMessageRead->save(array('ChatMessageRead' => array('chat_message_id' => $id, 'user_id' => $this->currentUserId)));
                        break;
                    case ChatMessage::ID_STATUS_UNREAD:
                        $success = $this->ChatMessageRead->deleteAll(array('ChatMessageRead.chat_message_id' => $id,
                                                                           'ChatMessageRead.user_id'         => $this->currentUserId));
                        break;
                }
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to change chat message status';
            } else {
                $data = $this->ChatMessage->getMessage($id, $this->currentUserId);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get chat message';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }
}