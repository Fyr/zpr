<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 21.07.13
 * Time: 22:09
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class Zen
 */
class Zen extends AppModel {
    public $useTable = 'zen';

    public function getZen($id) {
        $zen = $this->findById($id);

        if (!$zen or empty($zen)) {
            return false;
        }

        $answer = array();

        $answer['id']   = $zen['Zen']['id'];
        $answer['text'] = htmlspecialchars($zen['Zen']['text']);
        $answer['date'] = $zen['Zen']['modified'];

        /** @var User $user */
        $user = ClassRegistry::init('User');
        $answer['user'] = $user->getUser($zen['Zen']['user_id'], false);

        return $answer;
    }
}