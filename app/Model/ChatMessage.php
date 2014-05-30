<?php

/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 11.04.14
 * Time: 01:06
 * To change this template use File | Settings | File Templates.
 */
class ChatMessage extends AppModel {
    const ID_CHAT_CREDO = 1;

    const ID_STATUS_UNREAD = 1;
    const ID_STATUS_READ   = 2;

    public static $statuses = array(
        self::ID_STATUS_READ,
        self::ID_STATUS_UNREAD,
    );

    /**
     * Получить сообщение из чата по ID
     *
     * @param $id       int ID сообщеиния
     * @param $user_id  int ID просматривающего пользователя
     *
     * @return array|bool   Массив с полями сообщения или false, если такого сообщения нет или оно удалено
     */
    function getMessage($id, $user_id) {
        $chatMessageRead = ClassRegistry::init('ChatMessageRead');

        $joins   = array();
        $joins[] = array(
            'table'      => $chatMessageRead->getDataSource()->fullTableName($chatMessageRead),
            'alias'      => 'ChatMessageRead',
            'type'       => 'LEFT',
            'conditions' => array('ChatMessageRead.chat_message_id = ChatMessage.id',
                                  'ChatMessageRead.user_id' => $user_id)
        );

        $conditions = array(
            'ChatMessage.id' => $id
        );

        $message = $this->find('first', array('conditions' => $conditions,
                                              'joins'      => $joins,
                                              'fields'     => array(
                                                  'ChatMessage.id',
                                                  'ChatMessage.text',
                                                  'ChatMessage.user_id',
                                                  'ChatMessage.created',
                                                  '(CASE WHEN ChatMessageRead.chat_message_id IS NOT NULL THEN ' .
                                                  self::ID_STATUS_READ . ' ELSE ' .
                                                  self::ID_STATUS_UNREAD . ' END) as status'
                                              )));

        if (!$message or empty($message)) {
            return false;
        }

        $answer = array();

        $answer['id']     = $message['ChatMessage']['id'];
        $answer['text']   = htmlspecialchars($message['ChatMessage']['text']);
        $answer['status'] = ($message['ChatMessage']['user_id'] == $user_id ? self::ID_STATUS_READ : $message[0]['status']);
        $answer['date']   = $message['ChatMessage']['created'];

        $answer['author']       = array();
        $answer['author']['id'] = $message['ChatMessage']['user_id'];

        return $answer;
    }
}