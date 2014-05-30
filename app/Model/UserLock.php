<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 02.03.13
 * Time: 02:21
 * To change this template use File | Settings | File Templates.
 */
class UserLock extends AppModel {
    const LOCK_TYPE_AUTH_BONUS = 1;
    const LOCK_TYPE_VOTE       = 2;
    const LOCK_TYPE_COMMENT    = 3;

    private static $types = array(
        self::LOCK_TYPE_AUTH_BONUS,
        self::LOCK_TYPE_VOTE,
        self::LOCK_TYPE_COMMENT,
    );

    /**
     * Заблокировать указанного пользователя на указанное изменение
     *
     * @param $user_id  int Id пользователя
     * @param $type     int Тип блокировки
     * @param $timeout  int Время жизни блокировки в секундах (по умолчанию 10)
     *
     * @return int|bool   ID блокировки или false, если заблокировать не удалось
     */
    function lock($user_id, $type, $timeout = 10) {
        if ((int) $user_id == 0 or !in_array($type, self::$types) or (int) $timeout == 0) {
            return false;
        }

        $lock = $this->find('first', array('conditions' => array('user_id' => $user_id/*, 'lock_type' => $type*/)));
        if ($lock and !empty($lock)) {
            if (time() <= strtotime($lock['UserLock']['expired'])) {
                return false;
            }
        }

        $check = microtime(true);

        $this->create();

        if ($lock and !empty($lock)) {
            $this->id = $lock['UserLock']['id'];
        }

        $data              = array();
        $data['user_id']   = $user_id;
        $data['lock_type'] = $type;
        $data['expired']   = date('Y-m-d H:i:s', strtotime('+' . $timeout . ' seconds'));
        $data['check']     = $check;

        try {
            $result = $this->save($data);
        } catch (Exception $e) {
            $result = false;
        }

        if ($result) {
            if (!$lock or empty($lock)) {
                $id = $this->id;
            } else {
                $id = $lock['UserLock']['id'];
            }
            $check_total = $this->field('check', array('id' => $id));
            if ((string) $check_total == (string) $check) {
                $result = (int) $id;
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Снять указанную блокировку
     *
     * @param $lock_id int   ID блокировки
     *
     * @return bool Удалось ли снять блокировку?
     */
    function release($lock_id) {
        $id = (int) $lock_id;

        if (empty($id)) {
            return false;
        }

        $expire_date = date('Y-m-d H:i:s', strtotime('-1 second'));

        try {
            $result = $this->updateAll(
                array('expired' => "'{$expire_date}'"),
                array(
                     'id' => $id,
                ));
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }
}