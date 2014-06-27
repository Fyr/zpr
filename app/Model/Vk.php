<?php
App::uses('AppModel', 'Model');
class Vk extends AppModel {
    public $name = 'Vk';
    
    /**
     * Получить друзей пользователя
     * @param type $userVkId
     * @return type
     */
    public function getAllFrends($userVkId) {
	$data = $this->requestVk(
	    'https://api.vk.com/method/friends.get',
	    array('user_id' => $userVkId)
	);
	return $data;
    }
    
    /**
     * Проверяем наличие пользователя в группе ВК
     * @param int $vk_id
     * @return obj
     */
    function isMemberVk($vk_id) {
        $data = $this->requestVk(
	    'https://api.vk.com/method/groups.isMember',
	    array('gid' => 'zupersu', 'uid' => $vk_id)
	);
        return $data;
    }
    
    private function requestVk($url, $param) {
	try {
	    App::uses('HttpSocket', 'Network/Http');
	    $http = new HttpSocket();
	    $data = json_decode($http->get($url, $param));
	    if (!$data->response) {
		throw new Exception('string is not json');
	    }
	    return $data->response;
	} catch (Exception $e) {
	    $this->setError($e->getMessage());
	}
    }
}
