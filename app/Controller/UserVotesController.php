<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 22.07.12
 * Time: 15:47
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class UserVotesController
 *
 * @property User       User
 * @property UserRating UserRating
 * @property UserVote   UserVote
 * @property UserLock   UserLock
 */
class UserVotesController extends AppController {
    public $uses = array(
        'User',
        'UserRating',
        'UserVote',
        'UserLock'
    );
    public $components = array('RequestHandler');

    /**
     * Проголосовать
     *
     * POST /users/{user_id}/vote/
     *
     * Параметры:
     *     votes: {Number}
     *
     * Ответ:
     *     bool
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
            if (!$user or empty($user) or $user['User']['is_ready'] == 0 or $user['User']['is_in_hall_of_fame'] == 1) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad user request';
            }
        }

        if ($success and $user_id == $this->currentUserId) {
            $this->response->statusCode(405);
            $success = false;
            $data    = "can't vote for yourself";
        }

        if ($success and !isset($this->request->data['votes'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no votes specified';
        } elseif ($success) {
            $votes = (int) $this->request->data['votes'];
            if (empty($votes)) {
                $this->response->statusCode(400);
                $success = false;
                $data    = 'bad votes requested';
            }
        }

        // TODO: убрать блокировки пользователя-участника после рефакторинга UserVote: снимать голоса сначала и возвращать в случае неуспешного голосования?
        if ($success) {
            $lock             = $this->UserLock->lock($this->currentUserId, UserLock::LOCK_TYPE_VOTE);
            $lock_participant = $this->UserLock->lock($user_id, UserLock::LOCK_TYPE_VOTE);
            if (!$lock or !$lock_participant) {
                $this->response->statusCode(503);
                $success = false;
                $data    = "can't lock user, try later";
            }
        }

        if ($success) {
//            if (Configure::read('debug') == 0) {
                $available_daily_votes = $this->UserVote->getAvailableDailyVotes($this->currentUserId, $user_id);
//            } else {
//                $available_daily_votes = 100;
//            }

            if ($available_daily_votes == 0) {
                $this->response->statusCode(405);
                $success = false;
                $data    = "can't vote for this user today anymore";
            } elseif ($votes > $available_daily_votes) {
                $this->response->statusCode(405);
                $success = false;
                $data    = "can't vote so much for this user anymore";
            } else {
                try {
                    $result = $this->UserVote->vote($this->currentUserId, $user_id, $votes);
                    $data = $this->User->getUser($user_id, true, true, $this->currentUserId);
                } catch (Exception $e) {
                    $result = false;
                }

                if (!$result) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to save votes';
                }
            }
        }

        if (isset($lock)) {
            if ($lock) {
                $this->UserLock->release($lock);
            }
            if ($lock_participant) {
                $this->UserLock->release($lock_participant);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }
}