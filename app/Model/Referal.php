<?php
App::uses('AppModel', 'Model');
App::uses('Vk', 'Model');
class Referal extends AppModel {
    public $useTable = 'user_referals';
    public $belongsTo = array('User' => array('foreignKey' => 'referal_id', 'className' => 'User'));
    
    /**
     * Генерация hash для приглашения друзей
     * @param type $user_id
     */
    public function createReferalHash($user_id = false) {
	$salt = Configure::read('Security.referal_salt');
	$referalHash = intval($user_id) ? md5($user_id.$salt) : false;
	return $referalHash;
    }
    
    /**
     * Получить список всех рефералов пользователя
     */
    public function getAllReferal($user_id) {
	$data = $this->find('all', array('conditions' => array('user_id' => $user_id)));
	return $data;
    }
}