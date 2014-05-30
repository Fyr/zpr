<?php
App::uses('AppModel', 'Model');
class UserDefaults extends AppModel {
	const SETTINGS_ID = 1;
	
	public function getList() {
		$res = $this->findById(self::SETTINGS_ID);
		$res = $res['UserDefaults'];
		unset($res['id']);
		return $res;
	}
	
	public function save($data = null, $validate = true, $fieldList = array()) {
		if (isset($data['UserDefaults'])) {
			$data = $data['UserDefaults'];
		}
		$data['id'] = self::SETTINGS_ID;
		return parent::save($data);
	}
}