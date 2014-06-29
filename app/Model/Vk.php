<?php
App::uses('AppModel', 'Model');
class Vk extends AppModel {
	const VK_API_URL = 'https://api.vk.com/method/';
	
    public $useTable = false;
    
    /**
     * Получить друзей пользователя
     * @param type $userVkId
     * @return type
     */
	public function getAllFrends($userVkId) {
		return $this->requestVk('friends.get', array('user_id' => $userVkId));
	}
    
    /**
     * Проверяем наличие пользователя в группе ВК для приложения
     * @param int $vk_id - ID пользователя
     * @return obj
     */
    function isMemberVk($vk_id) {
		return intval($this->requestVk('groups.isMember', array('gid' => 'zupersu', 'uid' => $vk_id)));
	}
    
    private function requestVk($method, $params = array()) {
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket();
		$data = $http->get(VK_API_URL.$method, $params);
		
		// проверим пришел ли ответ
		if ($data) {
			// пробуем декодировать json 
			$data = json_decode($data, true);
			if ($data) { // анализируем ответ от сервера
				if (isset($data['response'])) {
					return $data['response'];
				} elseif (isset($data['error'])) {
					$error = Hash::get($data, 'error.error_msg');
					if ($error) {
						throw new Exception($error);
					}
				}
			}
		} else {
			throw new Exception('Server not responds');
		}
		
		throw new Exception('Incorrect server response');
    }
}
