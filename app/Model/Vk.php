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
	    $data = $http->get($url, $param);
	    // проверим пришел ли ответ
	    if (!$data) {
		throw new Exception('Server not returning data');
	    }
	    // пробуем декодировать json 
	    if (!$data = json_decode($data)) {
		throw new Exception('Server returning data in not json');
	    }
	    // проверим ошибки сервиса
	    if (isset($data->error)) {
		throw new Exception($data->error->error_msg);
	    }
	    // получим response из ответа
	    if (!isset($data->response)) {
		throw new Exception('Server returning data without response');
	    }
	    return $data->response;
	} catch (Exception $e) {
	    echo json_encode(array('status' => 'error', 'data' => $e->getMessage()));
	}
    }
}
