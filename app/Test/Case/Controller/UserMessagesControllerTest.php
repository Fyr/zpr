<?

App::uses('AppController', 'Controller');
App::uses('UserMessagesController', 'Controller');

require_once(APPLIBS . 'ResponseControllerTestCase.php');

class UserMessagesControllerTest extends ResponseControllerTestCase {
    public $fixtures = array(
        'app.Country',
        'app.City',
        'app.Auth',
        'app.User',
        'app.Credo',
        'app.UserRating',
        'app.UserMessage',
    );

    private function errorTemplate($error_message) {
        return array('answer' => array('status' => 'error', 'data' => $error_message));
    }

    private function addActionTest($data, $expected_output, $user_id, $auth_user_id = null, $code = null) {
        $testData = array(
            'data'     => $data,
            'method'   => 'post',
            'return'   => 'vars'
        );

        if ($code !== null) {
            $response = $this->getMock('CakeResponse', array('send', 'statusCode'));
            $response->expects($this->any())->method('statusCode')->will($this->returnValue(200));

            $testData['response'] = $response;
        }

        $result = $this->testAction(
                       "/users/messages/{$user_id}" . ($auth_user_id === null ? '' : "?user_id={$auth_user_id}"),
                       $testData
        );

        if (isset($result['answer']) && isset($result['answer']['data']) && isset($result['answer']['data']['date'])) {
            $expected_output['answer']['data']['date'] = $result['answer']['data']['date'];
        }

        $this->assertEqual($result, $expected_output);
    }

    public function testAdd() {
        $data = array();
        $user_id = 1;
        $auth_user_id = null;

        $this->addActionTest($data, $this->errorTemplate('unauthorized'), $user_id, $auth_user_id);

        $user_data = array();
        $user_data['name'] = 'User1';
        $user1 = $this->controller->User->add($user_data);

        $user_data = array();
        $user_data['name'] = 'User2';
        $user2 = $this->controller->User->add($user_data);

        $this->assertEquals(1, $user1['id']);
        $this->assertEquals(2, $user2['id']);

        $auth_user_id = 1;
        $this->addActionTest($data, $this->errorTemplate("can't send message to yourself"), $user_id, $auth_user_id);

        $user_id = 3;
        $this->addActionTest($data, $this->errorTemplate('bad user request'), $user_id, $auth_user_id);

        $user_id = 2;
        $this->addActionTest($data, $this->errorTemplate('no text specified'), $user_id, $auth_user_id);

        $data['text'] = 'test message';
        $this->addActionTest($data, array('answer' => json_decode('{"status":"success","data":{"id":"1","text":"test message","status":"1","date":"now()","author":{"id":"1"},"recipient":{"id":"2"}}}', true)), $user_id, $auth_user_id);

        $user_id = 1;
        $auth_user_id = 2;
        $this->addActionTest($data, array('answer' => json_decode('{"status":"success","data":{"id":"2","text":"test message","status":"1","date":"now()","author":{"id":"2"},"recipient":{"id":"1"}}}', true)), $user_id, $auth_user_id);
    }
}