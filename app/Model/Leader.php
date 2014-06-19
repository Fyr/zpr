<?php
App::uses('AppModel', 'Model');
class Leader extends AppModel {
    /**
     * Получить время последнего лидерства пользователя
     * @param int $user_id
     * @param int $type
     * @return str
     */
    public function getDateLeader($user_id, $type) {
        $data = $this->find('first', array(
            'fields' => array('created'),
            'conditions' => array('user_id' => $user_id, 'type' => $type),
            'order' => array('created' => 'DESC')
        ));
        $data = $data ? $data['Leader']['created'] : false;
        return $data;
    }
    
    /**
     * Сохранение пользователя в таблице лидеров
     * @param array $leaders => array('user_id' => 1, 'type' => 1)
     */
    public function saveLeader($leaders) {
        $this->create();
        $this->save($leaders);
    }
}