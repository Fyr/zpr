<?php
App::uses('AppModel', 'Model');
App::uses('Vk', 'Model');
class Referal extends AppModel {
	public $useTable = 'user_referals';
	public $belongsTo = array(
		'User' => array(
			'foreignKey' => 'referal_id', 
			'className' => 'User'
		)
	);
	
	/**
	 * Генерация hash для приглашения друзей
	 * @param type $user_id
	 */
	public function getHash($user_id) {
		return md5($user_id.Configure::read('Security.salt').'ref_hash');
	}

	/**
	 * Получить список всех рефералов пользователя
	 * @param type $user_id
	 */
	public function getFriends($user_id) {
		return $this->find('all', array('conditions' => array('user_id' => $user_id)));
	}
}