<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 13.07.12
 * Time: 13:43
 * To change this template use File | Settings | File Templates.
 */
class UserMessage extends AppModel {
    const ID_STATUS_UNREAD = 1;
    const ID_STATUS_READ   = 2;

    const ID_DELETION_TYPE_NONE      = 0;
    const ID_DELETION_TYPE_RECIPIENT = 1;
    const ID_DELETION_TYPE_AUTHOR    = 2;
    const ID_DELETION_TYPE_BOTH      = 3;

    public static $statuses = array(
        self::ID_STATUS_READ,
        self::ID_STATUS_UNREAD,
    );

    /**
     * Получить сообщение по ID
     *
     * @param $id       int ID сообщеиния
     * @param $user_id  int ID пользователя (отправитель или получатель)
     *
     * @return array|bool   Массив с полями сообщения или false, если такого сообщения нет или оно удалено
     */
    function getMessage($id, $user_id) {
        $message = $this->findById($id);

        if (!$message or empty($message) or
            ($message['UserMessage']['user_id'] != $user_id and
            $message['UserMessage']['recipient_id'] != $user_id)) {
            return false;
        }

        $answer = array();

        $answer['id']              = $message['UserMessage']['id'];
        $answer['text']            = htmlspecialchars($message['UserMessage']['text']);
        $answer['status']          = $message['UserMessage']['status'];
        $answer['date']            = $message['UserMessage']['created'];

        $answer['author']          = array();
        $answer['author']['id']    = $message['UserMessage']['user_id'];

        $answer['recipient']       = array();
        $answer['recipient']['id'] = $message['UserMessage']['recipient_id'];

        return $answer;
    }
}