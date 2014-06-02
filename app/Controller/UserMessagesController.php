<?php
class UserMessagesController extends AppController {
    public $uses = array('User',
                         'UserMessage',
                         'City',
                         'ChatMessage');
    public $components = array('RequestHandler');

    /**
     * Получить диалоги пользователя
     *
     * GET /users/messages/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     {Array of TMessageGroup},
     *
     * Доступно только для авторизованных
     */
    public function index() {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $sub1_db = $this->UserMessage->getDataSource();
        $sub1    = $sub1_db->buildStatement(
            array(
                 'fields'     => array(
                     'UserMessage_sub.id',
                 ),
                 'table'      => $sub1_db->fullTableName($this->UserMessage),
                 'alias'      => 'UserMessage_sub',
                 'limit'      => 1,
                 'offset'     => null,
                 'joins'      => array(),
                 'conditions' => array(
                     'OR' => array(array('UserMessage_sub.user_id = User_sub.id',
                                         'UserMessage_sub.recipient_id' => $this->currentUserId,
                                         'not' => array('UserMessage_sub.is_deleted' => array(UserMessage::ID_DELETION_TYPE_RECIPIENT,
                                                                                              UserMessage::ID_DELETION_TYPE_BOTH))),
                                   array('UserMessage_sub.user_id' => $this->currentUserId,
                                         'UserMessage_sub.recipient_id = User_sub.id',
                                         'not' => array('UserMessage_sub.is_deleted' => array(UserMessage::ID_DELETION_TYPE_AUTHOR,
                                                                                              UserMessage::ID_DELETION_TYPE_BOTH)))
                     ),
                 ),
                 'order'      => array('UserMessage_sub.created DESC', 'UserMessage_sub.id DESC'),
                 'group'      => array()
            ),
            $this->UserMessage
        );

        $sub2_db = $this->User->getDataSource();
        $sub2    = $sub2_db->buildStatement(
            array(
                 'fields'     => array(
                     'User_sub.*',
                     "({$sub1}) as message_id"
                 ),
                 'table'      => $sub2_db->fullTableName($this->User),
                 'alias'      => 'User_sub',
                 'limit'      => null,
                 'offset'     => null,
                 'joins'      => array(),
                 'conditions' => array(),
                 'order'      => null,
                 'group'      => array()
            ),
            $this->User
        );

        $joins   = array();
        $joins[] = array(
            'table'      => "({$sub2})",
            'alias'      => 'User',
            'type'       => 'INNER',
            'conditions' => array('User.message_id = UserMessage.id')
        );

        $messages = $this->UserMessage->find('all',
                                             array(
                                                  'joins'  => $joins,
                                                  'fields' => array('*'),
                                                  'order'  => array('UserMessage.created DESC', 'UserMessage.id DESC')));

        $user_ids = Set::extract('/User/id', $messages);
        $users = $this->User->getUsers($user_ids, false, array('User.name'), true, $this->currentUserId);
        $users = Set::combine($users, '{n}.id', '{n}');

        foreach ($messages as $row) {
            $res = array();

            $res['user'] = $users[$row['User']['id']];

            $res['message']           = array();
            $res['message']['id']     = $row['UserMessage']['id'];
            $res['message']['text']   = htmlspecialchars($row['UserMessage']['text']);
            $res['message']['status'] = $row['UserMessage']['status'];
            $res['message']['date']   = $row['UserMessage']['created'];

            $res['message']['author']       = array();
            $res['message']['author']['id'] = $row['UserMessage']['user_id'];

            $data[] = $res;
        }

        $currentUser = $this->User->findById($this->currentUserId);
        if ($currentUser['User']['is_ready'] and $currentUser['User']['credo_id']) {
            $lastChatMessage = $this->ChatMessage->find('first', array(
                'conditions' => array(
                    'ChatMessage.chat_id'       => ChatMessage::ID_CHAT_CREDO,
                    'ChatMessage.chat_group_id' => $currentUser['User']['credo_id']
                ),
                'fields'     => array('ChatMessage.id'),
                'order'      => array(
                    'ChatMessage.created DESC',
                    'ChatMessage.id DESC'
                )
            ));

            if ($lastChatMessage and !empty($lastChatMessage)) {
                $chatMessage = $this->ChatMessage->getMessage($lastChatMessage['ChatMessage']['id'], $this->currentUserId);

                if ($chatMessage) {
                    $data[] = array(
                        'credo' => $chatMessage
                    );
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получить диалог
     *
     * GET /users/messages/{user_id}/
     *
     * Параметры:
     *     per_page {Number}
     *     page {Number}
     *
     * Ответ:
     *     user {TUser}
     *     messages {Array of TMessage}
     *     pages {TPage}
     *
     * @param $user_id
     *
     * Доступно только для авторизованных
     */
    public function view($user_id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if ($user_id == $this->currentUserId) {
            $this->response->statusCode(400);
            $success = false;
            $data    = "can't get message group for yourself";
        } else {
            $user = $this->User->getUser($user_id, false, true, $this->currentUserId);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
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

            // костыль для инициализации класса
            $this->UserMessage;

            $conditions = array('OR' => array(array('UserMessage.user_id'      => $this->currentUserId,
                                                    'UserMessage.recipient_id' => $user_id,
                                                    'not' => array('UserMessage.is_deleted' => array(UserMessage::ID_DELETION_TYPE_AUTHOR,
                                                                                                     UserMessage::ID_DELETION_TYPE_BOTH))),
                                              array('UserMessage.user_id'      => $user_id,
                                                    'UserMessage.recipient_id' => $this->currentUserId,
                                                    'not' => array('UserMessage.is_deleted' => array(UserMessage::ID_DELETION_TYPE_RECIPIENT,
                                                                                                     UserMessage::ID_DELETION_TYPE_BOTH)))));

            $messages_count = $this->UserMessage->find('count', array('conditions' => $conditions));

            if (($page - 1) * $per_page >= $messages_count && $messages_count > 0) {
                $data['user']     = $user;
                $data['messages'] = array();
                $data['pages']    = array('per_page' => $per_page,
                                          'page'     => $page,
                                          'total'    => $messages_count);
            } elseif ($messages_count > 0) {
                $data['user']     = $user;
                $data['messages'] = array();
                $data['pages']    = array('per_page' => $per_page,
                                          'page'     => $page,
                                          'total'    => $messages_count);

                $messages = $this->UserMessage->find('all', array('conditions' => $conditions,
                                                                  'fields'     => array(
                                                                      'UserMessage.id',
                                                                      'UserMessage.text',
                                                                      'UserMessage.status',
                                                                      'UserMessage.user_id',
                                                                      'UserMessage.created'
                                                                  ),
                                                                  'order'      => array(
                                                                      'UserMessage.created DESC',
                                                                      'UserMessage.id DESC'
                                                                  ),
                                                                  'limit'      => $per_page,
                                                                  'offset'     => ($page - 1) * $per_page));

                foreach ($messages as $row) {
                    $res           = array();
                    $res['id']     = $row['UserMessage']['id'];
                    $res['text']   = htmlspecialchars($row['UserMessage']['text']);
                    $res['status'] = $row['UserMessage']['status'];
                    $res['date']   = $row['UserMessage']['created'];

                    $res['author']       = array();
                    $res['author']['id'] = $row['UserMessage']['user_id'];

                    $data['messages'][] = $res;
                }
            } else {
                $data['user']     = $user;
                $data['messages'] = array();
                $data['pages']    = array('per_page' => $per_page,
                                          'page'     => $page,
                                          'total'    => $messages_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Добавить сообщение
     *
     * POST /users/messages/{user_id}/
     *
     * Параметры:
     *     {TMessage}
     *
     * Ответ:
     *     TMessage
     *
     * @param $user_id
     *
     * Доступно только для авторизованных
     */
    public function add($user_id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if (!isset($user_id) or !$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } else {
            if ($user_id == $this->currentUserId) {
                $this->response->statusCode(405);
                $success = false;
                $data    = "can't send message to yourself";
            } else {
                $user = $this->User->findById($user_id);
                if (!$user or empty($user)) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data    = 'bad user request';
                }
            }
        }

        if ($success && !isset($this->request->data['text'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no text specified';
        }

        if ($success) {
            try {
                $this->UserMessage->create();

                $user_data                 = array();
                $user_data['user_id']      = $this->currentUserId;
                $user_data['recipient_id'] = $user_id;
                $user_data['text']         = $this->request->data['text'];
                $user_data['status']       = UserMessage::ID_STATUS_UNREAD;

                $success = $this->UserMessage->save(array('UserMessage' => $user_data));
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to save message';
            } else {
                $data = $this->UserMessage->getMessage($this->UserMessage->id, $this->currentUserId);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get message';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Редактировать сообщение
     *
     * PUT /users/messages/{user_id}/{message_id}/
     *
     * Параметры:
     *     {TMessage}
     *
     * Ответ:
     *     TMessage
     *
     * Доступно только авторизованным и только для полученных сообщений с целью установки флага прочтения
     *
     * @param $user_id
     * @param $id
     */
    public function edit($user_id, $id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $message = $this->UserMessage->findById($id);
        if (!$message or empty($message)) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message request';
        } elseif ($message['UserMessage']['user_id'] != $user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad message group request';
        }

        if ($success and !$this->userCheck($message['UserMessage']['recipient_id'])) {
            return;
        }

        if ($success and ($message['UserMessage']['is_deleted'] == UserMessage::ID_DELETION_TYPE_BOTH) or
            ($this->currentUserId == $message['UserMessage']['recipient_id'] and
             $message['UserMessage']['is_deleted'] == UserMessage::ID_DELETION_TYPE_RECIPIENT)
        ) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message request';
        }

        if ($success and !isset($this->request->data['status'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no status specified';
        } elseif ($success and !in_array($this->request->data['status'], UserMessage::$statuses)) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad status specified';
        }

        if ($success and $this->request->data['status'] == $message['UserMessage']['status']) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no changes specified';
        }

        if ($success) {
            $this->UserMessage->id = $id;

            try {
                $success = $this->UserMessage->saveField('status', $this->request->data['status']);
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to save message';
            } else {
                $data = $this->UserMessage->getMessage($this->UserMessage->id, $this->currentUserId);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get message';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Удалить сообщение
     *
     * DELETE /users/messages/{user_id}/{message_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     <отсутствует>
     *
     * Доступно только авторизованным
     *
     * @param $user_id
     * @param $id
     */
    public function delete($user_id, $id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $message = $this->UserMessage->findById($id);
        if (!$message or empty($message)) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message request';
        }

        if ($success and $user_id == $this->currentUserId) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message group request';
        }

        if ($success and $user_id != $message['UserMessage']['recipient_id'] and $user_id != $message['UserMessage']['user_id']) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message group request';
        }

        if ($success) {
            $is_author = false;
            if ($message['UserMessage']['user_id'] == $this->currentUserId) {
                $is_author = true;
            }
            if (!$this->userCheck($is_author ? $message['UserMessage']['user_id'] : $message['UserMessage']['recipient_id'])) {
                return;
            }
        }

        $current_delete_type = $message['UserMessage']['is_deleted'];

        if ($success and ($current_delete_type == UserMessage::ID_DELETION_TYPE_BOTH or
                          ($current_delete_type == UserMessage::ID_DELETION_TYPE_RECIPIENT and !$is_author) or
                          ($current_delete_type == UserMessage::ID_DELETION_TYPE_AUTHOR and $is_author))) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message request';
        }

        if ($success) {
            $this->UserMessage->id = $id;

            $new_delete_type = null;

            switch ($current_delete_type) {
                case (UserMessage::ID_DELETION_TYPE_RECIPIENT):
                case (UserMessage::ID_DELETION_TYPE_AUTHOR):
                    $new_delete_type = UserMessage::ID_DELETION_TYPE_BOTH;
                    break;
                case (UserMessage::ID_DELETION_TYPE_NONE):
                default:
                    $new_delete_type = $is_author ? UserMessage::ID_DELETION_TYPE_AUTHOR : UserMessage::ID_DELETION_TYPE_RECIPIENT;
            }

            try {
                $success = $this->UserMessage->saveField('is_deleted', $new_delete_type);
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to delete message';
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Удалить диалог
     *
     * DELETE /users/messages/{user_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     <отсутствует>
     *
     * Доступно только авторизованным
     *
     * @param $user_id
     */
    public function deleteAll($user_id) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if ($success and $user_id == $this->currentUserId) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad message group request';
        }

        if ($success) {
            try {
                $success = $this->UserMessage->updateAll(array('UserMessage.is_deleted' => UserMessage::ID_DELETION_TYPE_AUTHOR),
                                                         array(
                                                             'UserMessage.is_deleted'   => UserMessage::ID_DELETION_TYPE_NONE,
                                                             'UserMessage.recipient_id' => $user_id,
                                                             'UserMessage.user_id'      => $this->currentUserId
                                                         ));
                $success = $success && $this->UserMessage->updateAll(array('UserMessage.is_deleted' => UserMessage::ID_DELETION_TYPE_RECIPIENT),
                                                                     array(
                                                                         'UserMessage.is_deleted'   => UserMessage::ID_DELETION_TYPE_NONE,
                                                                         'UserMessage.recipient_id' => $this->currentUserId,
                                                                         'UserMessage.user_id'      => $user_id
                                                                     ));
                $success = $success && $this->UserMessage->updateAll(
                                                         array('UserMessage.is_deleted' => UserMessage::ID_DELETION_TYPE_BOTH),
                                                         array(
                                                             'OR' => array(array('UserMessage.is_deleted'   => UserMessage::ID_DELETION_TYPE_AUTHOR,
                                                                                 'UserMessage.recipient_id' => $this->currentUserId,
                                                                                 'UserMessage.user_id'      => $user_id),
                                                                           array('UserMessage.is_deleted'   => UserMessage::ID_DELETION_TYPE_RECIPIENT,
                                                                                 'UserMessage.recipient_id' => $user_id,
                                                                                 'UserMessage.user_id'      => $this->currentUserId))
                                                         ));
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to delete message group';
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получить количество непрочитанных сообщений пользователя
     *
     * GET /users/messages/unread
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     unread {Number}
     *
     * Доступно только для авторизованных
     */
    public function unread() {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $unread = $this->UserMessage->find('count',
                                           array(
                                                'conditions' => array(
                                                    'UserMessage.recipient_id' => $this->currentUserId,
                                                    'UserMessage.status'       => UserMessage::ID_STATUS_UNREAD,
                                                    'not'                      => array(
                                                        'UserMessage.is_deleted' => array(UserMessage::ID_DELETION_TYPE_RECIPIENT,
                                                                                          UserMessage::ID_DELETION_TYPE_BOTH)))));
        $data['unread'] = $unread;

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Массовая отметка о прочтении
     *
     * PUT /users/messages/read/{message_ids}
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     <отсутствует>
     *
     * Доступно только для авторизованных
     */
    public function read($ids) {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        $unread = $this->UserMessage->find('count', array('conditions' => array('UserMessage.recipient_id' => $this->currentUserId,
                                                                                'UserMessage.status'       => UserMessage::ID_STATUS_UNREAD)));
        $data['unread'] = $unread;

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }
}