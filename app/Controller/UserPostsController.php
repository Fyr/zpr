<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 27.09.12
 * Time: 22:58
 * To change this template use File | Settings | File Templates.
 */
class UserPostsController extends AppController {
    public $uses = array('User',
                         'UserRating');
    public $components = array('RequestHandler');

    public function edit($id) {
        $success = true;
        $data = array();

        if (!$id) {
            $success = false;
            $data = 'no user specified';
        }

        if (Configure::read('debug') == 0 and $success and !$this->Auth->authenticate($id, $this->authCookie)) {
            $this->response->statusCode(403);

            $success = false;
            $data = 'access denied';
        }

        if ($success) {
            $joins = array();
            $joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array('UserRating.user_id = User.id',
                                      'UserRating.country_id = User.country_id')
            );
            $user = $this->User->find('first', array('conditions' => array('fb_id' => $id),
                                                     'fields'     => array('User.id',
                                                                           'User.fb_id',
                                                                           'User.country_id',
                                                                           'User.created',
                                                                           'User.last_position',
                                                                           'IFNULL(UserRating.positive_votes, 0) as positive_votes',
                                                                           'IFNULL(UserRating.negative_votes, 0) as negative_votes'),
                                                     'joins' => $joins));
            if (!$user or empty($user)) {
                $success = false;
                $data = 'bad user request';
            }
        }

        if ($success) {
            $post_id = isset($this->data['post_id']) ? ($this->data['post_id']) : false;
            if (!$post_id) {
                $success = false;
                $data = 'no post_id specified';
            }
        }

        if ($success) {
            $joins   = array();
            $joins[] = array(
                'table'      => $this->User->getDataSource()->fullTableName($this->User),
                'alias'      => 'User',
                'type'       => 'INNER',
                'conditions' => array('User.id = UserRating.user_id',
                                      'User.is_ready' => 1)
            );

            $res = $this->UserRating->find('first', array(
                'conditions' => array(
                    'UserRating.country_id' => $user['User']['country_id'],
                    'OR' => array(
                        'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user[0]['positive_votes'] - $user[0]['negative_votes'],
                        'AND' => array(
                            'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user[0]['positive_votes'] - $user[0]['negative_votes'],
                            'User.created <'                                                   => $user['User']['created']
                        )
                    )
                ),
                'fields'     => array('COUNT(DISTINCT UserRating.user_id) + 1 as rating'),
                'joins'      => $joins));

            try {
                $user['User']['post_id']       = $post_id;
                $user['User']['last_position'] = $res[0]['rating'];

                if (!$this->User->save($user)) {
                    $success = false;
                    $data = 'user saving error';
                }
            } catch (Exception $e) {
                $success = false;
                $data = 'user saving error';
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);

        $this->set(compact('answer'));
    }
}