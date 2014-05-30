<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 04.10.13
 * Time: 21:13
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class CountersController
 * @property User        User
 * @property UserComment UserComment
 * @property UserMessage UserMessage
 */
class CountersController extends AppController {
    public $uses = array('User',
                         'UserComment',
                         'UserMessage');
    public $components = array('RequestHandler');

    /**
     * Получить счётчики
     *
     * GET /counters/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     unread {Number}
     *     comments {
     *         total {Number}
     *         positive {Number}
     *         negative {Number}
     *     }
     *
     * Доступно только для авторизованных
     */
    public function index() {
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

        $comments = $this->UserComment->find('first',
                                             array(
                                                  'conditions' => array(
                                                      'UserComment.participant_id' => $this->currentUserId,
                                                      'UserComment.parent_id'      => null,
                                                      'UserComment.deleted'        => 0
                                                  ),
                                                  'fields'     => array(
                                                      'SUM(CASE WHEN UserComment.type = ' . UserComment::ID_TYPE_POSITIVE . ' THEN 1 ELSE 0 END) as positive',
                                                      'SUM(CASE WHEN UserComment.type = ' . UserComment::ID_TYPE_NEGATIVE . ' THEN 1 ELSE 0 END) as negative',
                                                      'COUNT(*) as total'
                                                  )
                                             ));

        $data['comments'] = array('total'    => (int) $comments[0]['total'],
                                  'positive' => (int) $comments[0]['positive'],
                                  'negative' => (int) $comments[0]['negative']);

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }
}