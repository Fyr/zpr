<?

App::uses('AppController', 'Controller');
App::uses('AuthController', 'Controller');

require_once(APPLIBS . 'ResponseControllerTestCase.php');

class AuthControllerTest extends ResponseControllerTestCase {
    public $fixtures = array(
        'app.Auth',
        'app.User',
        'app.Credo',
        'app.Country',
        'app.City',
        'app.UserRating',
    );

    private function errorTemplate($error_message) {
        return array('answer' => array('status' => 'error', 'data' => $error_message));
    }

    private function vkActionTest($data, $expected_output, $code = null, $mock = false, $return = null) {
        $testData = array(
            'data'     => $data,
            'method'   => 'get',
            'return'   => 'vars'
        );

        if ($code !== null) {
            $response = $this->getMock('CakeResponse', array('send', 'statusCode'));
            $response->expects($this->any())->method('statusCode')->will($this->returnValue(200));

            $testData['response'] = $response;
        }

        if ($mock) {
            $Auth = $this->generate(
                          'Auth',
                          array(
                              'methods' => array('__getJSON'),
                          )
            );

            if ($return !== null) {
                $Auth->expects($this->once())->method('__getJSON')->will($this->returnValue($return));
            }
        }

        $result = $this->testAction(
                       '/auth/vk',
                       $testData
        );

        $this->assertEqual($result, $expected_output);
    }

    public function testVk() {
        $data = array();
        $answer = array();

        $this->vkActionTest($data, $this->errorTemplate('bad vk api answer'));

        $data['error'] = 'test error';
        $this->vkActionTest($data, $this->errorTemplate('bad vk api answer. error = test error'));

        $data['error_description'] = 'test description';
        $this->vkActionTest($data, $this->errorTemplate('bad vk api answer. error = test error. error_description = test description'));

        unset($data['error'], $data['error_description']);
        $data['code'] = 1;
        $this->vkActionTest($data, $this->errorTemplate('bad vk request token answer'), null, true, $answer);

        $answer['error'] = 'test error';
        $answer['error_description'] = 'test description';
        $this->vkActionTest($data, $this->errorTemplate('vk api error: test error - test description'), null, true, $answer);

        unset($answer['error'], $answer['error_description']);
        $answer['access_token'] = 2;
        $this->vkActionTest($data, $this->errorTemplate('bad vk request token answer params'), null, true, $answer);

        $answer['user_id'] = 1;
        $this->vkActionTest($data, $this->errorTemplate('bad vk request token answer params'), null, true, $answer);

        $answer['expires_in'] = 3600;
        $this->vkActionTest($data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":null,"vk_id":"1","name":null,"is_ready":false,"status":null,"credo":null,"country":{"id":null,"code":null,"name":null},"city":{"id":null,"name":null,"region_name":null,"position":{"x":null,"y":null}},"likes":"0","dislikes":"0","world_position":"1","country_position":null,"city_position":null}}', true)), null, true, $answer);

        $user = $this->controller->User->findById(1);
        $this->assertEquals($answer['access_token'], $user['User']['vk_token']);
        $this->assertTrue(strtotime("-{$answer['expires_in']} seconds") - strtotime($user['User']['vk_token_expires']) < 10, 'expires difference more then 10 seconds');

        $answer['access_token'] = 3;
        $this->vkActionTest($data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":null,"vk_id":"1","name":null,"is_ready":false,"status":null,"credo":null,"country":{"id":null,"code":null,"name":null},"city":{"id":null,"name":null,"region_name":null,"position":{"x":null,"y":null}},"likes":"0","dislikes":"0","world_position":"1","country_position":null,"city_position":null}}', true)), null, true, $answer);

        $user = $this->controller->User->findById(1);
        $this->assertEquals($answer['access_token'], $user['User']['vk_token']);
        $this->assertTrue(strtotime("-{$answer['expires_in']} seconds") - strtotime($user['User']['vk_token_expires']) < 10, 'expires difference more then 10 seconds');
    }
}