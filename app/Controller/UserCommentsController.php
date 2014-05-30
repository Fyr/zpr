<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 01.03.13
 * Time: 22:32
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class UserCommentsController
 * @property User User
 * @property UserComment UserComment
 * @property City City
 * @property Country Country
 */
class UserCommentsController extends AppController {
    public $uses = array('User',
                         'UserComment',
                         'City',
                         'Country');
    public $components = array('RequestHandler');

    /**
     * Получить комментарии
     *
     * GET /users/{user_id}/comments/
     *
     * Параметры:
     *     per_page {Number}
     *     page {Number}
     *
     * Ответ:
     *     comments {Array of {
     *         votes {Number}
     *         comment {TComment}
     *     }}
     *     pages {TPage}
     *
     * @param $user_id
     */
    public function index($user_id) {
        try {
        	$data    = array();

	        if (!$user_id) {
	        	throw new Exception('no user specified');
	        } else {
	            $user = $this->User->findById($user_id);
	            if (!$user) {
	            	throw new Exception('bad user request');
	            }
	        }

            $page = ($page = intval($this->request->query('page'))) ? $page : 1;
        	$per_page = ($per_page = intval($this->request->query('per_page'))) ? $per_page : 25;

            $joins = array();
            $joins[] = array(
                'table'      => $this->User->getDataSource()->fullTableName($this->User),
                'alias'      => 'User',
                'type'       => 'INNER',
                'conditions' => array('User.id = UserComment.user_id')
            );
            $joins[] = array(
                'table'      => $this->City->getDataSource()->fullTableName($this->City),
                'alias'      => 'City',
                'type'       => 'LEFT',
                'conditions' => array('City.id = User.city_id')
            );
            $joins[] = array(
                'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                'alias'      => 'Country',
                'type'       => 'LEFT',
                'conditions' => array('Country.id = User.country_id')
            );

            $conditions = array('UserComment.participant_id' => $user_id,
                                'UserComment.parent_id'      => null,
                                'UserComment.deleted'        => 0);

            $comments_count = $this->UserComment->find('count', array('conditions' => $conditions,
                                                                      'joins'      => $joins));
            $comment_type_count = $this->UserComment->find(
                'first',
                array(
                     'conditions' => $conditions,
                     'joins'      => $joins,
                     'fields'     => array(
                         'SUM(CASE WHEN UserComment.type = ' . UserComment::ID_TYPE_POSITIVE . ' THEN 1 ELSE 0 END) AS positive_cnt',
                         'SUM(CASE WHEN UserComment.type = ' . UserComment::ID_TYPE_NEGATIVE . ' THEN 1 ELSE 0 END) AS negative_cnt',
                     )
                )
            );

            if (!empty($comment_type_count)) {
                $positive_count = $comment_type_count[0]['positive_cnt'];
                $negative_count = $comment_type_count[0]['negative_cnt'];
            } else {
                $positive_count = 0;
                $negative_count = 0;
            }

            if (($page - 1) * $per_page >= $comments_count && $comments_count > 0) {
                $data['comments']  = array();
                $data['pages']  = array('per_page' => $per_page,
                                        'page'     => $page,
                                        'total'    => $comments_count);
                $data['stats'] = array('positive' => $positive_count,
                                       'negative' => $negative_count);
            } elseif ($comments_count > 0) {
                $data['comments']  = array();
                $data['pages']  = array('per_page' => $per_page,
                                        'page'     => $page,
                                        'total'    => $comments_count);
                $data['stats'] = array('positive' => $positive_count,
                                       'negative' => $negative_count);

                $comments = $this->UserComment->find('all',
                                                     array(
                                                          'conditions' => $conditions,
                                                          'fields'     => array(
                                                              'UserComment.id',
                                                              'UserComment.text',
                                                              'UserComment.type',
                                                              'UserComment.modified',
                                                              'User.id',
                                                              'User.name',
                                                              'City.id',
                                                              'City.name',
                                                              'City.region_name',
                                                              'Country.id',
                                                              'Country.name',
                                                          ),
                                                          'joins'      => $joins,
                                                          'order'      => array('UserComment.modified ASC'),
                                                          'limit'      => $per_page,
                                                          'offset'     => ($page - 1) * $per_page
                                                     ));
                foreach ($comments as $comment) {
                    $ans = array();

                    $ans['comment']         = array();
                    $ans['comment']['id']   = $comment['UserComment']['id'];
                    $ans['comment']['text'] = htmlspecialchars($comment['UserComment']['text']);
                    $ans['comment']['type'] = $this->UserComment->getType($comment['UserComment']['type']);
                    $ans['comment']['date'] = $comment['UserComment']['modified'];

                    $ans['comment']['user']         = array();
                    $ans['comment']['user']['id']   = $comment['User']['id'];
                    $ans['comment']['user']['name'] = $comment['User']['name'];

                    $ans['comment']['user']['city']                  = array();
                    $ans['comment']['user']['city']['id']            = $comment['City']['id'];
                    $ans['comment']['user']['city']['name']          = $comment['City']['name'];
                    $ans['comment']['user']['city']['region_name']   = $comment['City']['region_name'];

                    $ans['comment']['user']['country']         = array();
                    $ans['comment']['user']['country']['id']   = $comment['Country']['id'];
                    $ans['comment']['user']['country']['name'] = $comment['Country']['name'];

                    // Получим ответы на комментарий
                    $ans['comment']['replies'] = array();

                    $children = $this->UserComment->children($comment['UserComment']['id'], true, array('id', 'text', 'user_id', 'type', 'modified'), 'modified');
                    foreach ($children as $child) {
                        $reply = array();

                        $reply['id']      = $child['UserComment']['id'];
                        $reply['text']    = htmlspecialchars($child['UserComment']['text']);
                        $reply['user_id'] = $child['UserComment']['user_id'];
                        $reply['type']    = $this->UserComment->getType($child['UserComment']['type']);
                        $reply['date']    = $child['UserComment']['modified'];

                        $ans['comment']['replies'][] = $reply;
                    }

                    $data['comments'][] = $ans;
                }
            } else {
                $data['comments']  = array();
                $data['pages']  = array('per_page' => $per_page,
                                        'page'     => $page,
                                        'total'    => $comments_count);
                $data['stats'] = array('positive' => $positive_count,
                                       'negative' => $negative_count);
            }
            
	        if (isset($data['comments'])) {
	            $users = array();
	            // Подтянем данные пользователей, которые писали ответы на комментарии
	            foreach ($data['comments'] as &$comment) {
	                unset($reply);
	                foreach ($comment['comment']['replies'] as &$reply) {
	                    if (!isset($users[$reply['user_id']])) {
	                        $users[$reply['user_id']] = array();
	                    }
	
	                    $users[$reply['user_id']][] = &$reply;
	                }
	            }
	
	            if (!empty($users)) {
	                $users_full = $this->User->getUsers(array_keys($users), false, array(), true, $this->currentUserId);
	                foreach ($users_full as $user) {
	                    $user_id = $user['id'];
	
	                    foreach ($users[$user_id] as &$reply) {
	                        array_splice($reply, 2, 1);
	                        $first_array = array_splice($reply, 0, 2);
	                        $reply       = array_merge($first_array, array('user' => $user), $reply);
	                    }
	                }
	            }
	        }

        	$this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }
    }

    /**
     * Получить комментарий
     *
     * GET /users/{user_id}/comments/{comment_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     votes {Number}
     *     comment {TComment}
     *
     * @param $user_id
     * @param $id
     */
    public function view($user_id, $id) {
        $success = true;
        $data    = array();

        $comment = $this->UserComment->getComment($id);
        if (!$comment) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad comment request';
        }

        if ($success and !$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } elseif ($success) {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            }
        }

        if ($success) {
            $data = array(
                'comment' => $comment
            );
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Добавить комментарий
     *
     * POST /users/{user_id}/comments/
     *
     * Параметры:
     *     {TComment}
     *
     * Ответ:
     *     TComment
     *
     * Доступно только для авторизованных
     *
     * @param $user_id
     */
    public function add($user_id) {
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

        if ($success and !isset($this->request->data['text'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no text specified';
        }

        if ($success and !isset($this->request->data['type'])) {
            $this->request->data['type'] = $this->UserComment->getType(UserComment::ID_TYPE_POSITIVE);
        } elseif ($success and $this->UserComment->getTypeId($this->request->data['type']) === null) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad type specified';
        }

        if ($success and !isset($this->request->data['parent_id'])) {
            $parent_id = null;

            if ($user_id == $this->currentUserId) {
                $this->response->statusCode(405);
                $success = false;
                $data    = "can't comment yourself";
            }
        } elseif ($success) {
            $parent_id = $this->request->data['parent_id'];
            if ($parent_id != null) {
                $parent_comment = $this->UserComment->findById($parent_id);

                if (!$parent_comment or empty($parent_comment)) {
                    $this->response->statusCode(400);
                    $success = false;
                    $data    = 'bad parent comment request';
                } elseif ($parent_comment['UserComment']['parent_id'] !== null) {
                    $this->response->statusCode(405);
                    $success = false;
                    $data    = "can't comment reply to comment";
                }
            }
        }

        if ($success) {
            $this->UserComment->create();

            $user_data                   = array();
            $user_data['user_id']        = $this->currentUserId;
            $user_data['participant_id'] = $user_id;
            $user_data['text']           = $this->request->data['text'];
            $user_data['type']           = $this->UserComment->getTypeId($this->request->data['type']);
            $user_data['parent_id']      = $parent_id;

            try {
                $success = $this->UserComment->save(array('UserComment' => $user_data));
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to save comment';
            } else {
                $data = $this->UserComment->getComment($this->UserComment->id);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get comment';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Редактировать комментарий
     *
     * PUT /users/{user_id}/comments/{comment_id}/
     *
     * Параметры:
     *     {TComment}
     *
     * Ответ:
     *     TComment
     *
     * Доступно только авторизованным и только для себя
     *
     * @param $user_id
     * @param $id
     */
    public function edit($user_id, $id) {
        $success = true;
        $data    = array();

        $comment = $this->UserComment->findById($id);
        if (!$comment or empty($comment) or $comment['UserComment']['deleted']) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'bad comment request';
        }

        if ($success and !$this->userCheck($comment['UserComment']['user_id'])) {
            return;
        }

        if ($success and !$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } elseif ($success) {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            }
        }

        if ($success and !isset($this->request->data['text'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no text specified';
        }

        if ($success and !isset($this->request->data['type'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no type specified';
        } elseif ($success and !in_array($this->request->data['type'], UserComment::$types)) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad type specified';
        }

        if ($success and ($this->request->data['text'] == $comment['UserComment']['text'] or $this->UserComment->getTypeId($this->request->data['type']) == $comment['UserComment']['type'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no changes specified';
        }

        if ($success) {
            $this->UserComment->id = $id;

            $user_data                   = array();
            $user_data['user_id']        = $this->currentUserId;
            $user_data['participant_id'] = $user_id;
            $user_data['text']           = $this->request->data['text'];
            $user_data['type']           = $this->UserComment->getTypeId($this->request->data['type']);

            try {
                $success = $this->UserComment->save($user_data, true, array_keys($user_data));
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to save comment';
            } else {
                $data = $this->UserComment->getComment($this->UserComment->id);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get comment';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Удалить комментарий
     *
     * DELETE /users/{user_id}/comments/{comment_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     <отсутствует>
     *
     * Доступно только авторизованным и только для себя
     *
     * @param $user_id
     * @param $id
     */
    public function delete($user_id, $id) {
        $success = true;
        $data    = array();

        $comment = $this->UserComment->findById($id);
        if (!$comment or empty($comment) or $comment['UserComment']['deleted']) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad comment request';
        }

        if ($success and !$this->userCheck($comment['UserComment']['user_id'])) {
            return;
        }

        if ($success and !$user_id) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no user specified';
        } elseif ($success) {
            $user = $this->User->findById($user_id);
            if (!$user or empty($user)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            }
        }

        if ($success) {
            try {
                $this->UserComment->id = $id;
                $success = $this->UserComment->saveField('deleted', 1);
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to delete comment';
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }
}