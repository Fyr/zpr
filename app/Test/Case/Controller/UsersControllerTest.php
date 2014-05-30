<?

App::uses('AppController', 'Controller');
App::uses('UsersController', 'Controller');

require_once(APPLIBS . 'ResponseControllerTestCase.php');

class UserControllerTest extends ResponseControllerTestCase {
    public $fixtures = array(
        'app.Country',
        'app.City',
        'app.Auth',
        'app.User',
        'app.Credo',
        'app.UserRating',
    );

    private function errorTemplate($error_message) {
        return array('answer' => array('status' => 'error', 'data' => $error_message));
    }

    private function addActionTest($data, $expected_output, $code = null) {
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
                       '/users',
                       $testData
        );
        $this->assertEqual($result, $expected_output);
    }

    private function editActionTest($id, $data, $expected_output, $mock_auth_check = true, $mock_picture_type = null, $mock_build_avatar = false) {
        if ($mock_auth_check === true) {
            $methods = array(
                'userCheck',
            );
            if ($mock_picture_type !== null) {
                $methods[] = '__checkPictureType';
            }
            if ($mock_picture_type) {
                $methods[] = '__buildPictureAvatars';
            }
            $Users = $this->generate(
                'Users',
                array(
                     'methods' => $methods,
                )
            );
            $Users->expects($this->once())->method('userCheck')->with($id)->will($this->returnValue(true));
            if ($mock_picture_type !== null) {
                $Users->expects($this->once())->method('__checkPictureType')->will($this->returnValue($mock_picture_type));
            }
            if ($mock_build_avatar) {
                $Users->expects($this->once())->method('__buildPictureAvatars')->will($this->returnValue($mock_picture_type));
            }
        }

        $result = $this->testAction(
            '/users/' . $id . '/',
            array(
                 'data' => $data,
                 'method' => 'put',
                 'return' => 'vars',
            )
        );
        $this->assertEqual($result, $expected_output);
    }

    public function testAdd() {
        $data = array();

        $this->addActionTest($data, $this->errorTemplate('no user specified'));

        $data['user'] = array();
        $this->addActionTest($data, $this->errorTemplate('no user fb_id or vk_id specified'));

        $data['user']['fb_id'] = 1;
        $this->addActionTest($data, $this->errorTemplate('no country specified'));

        $data['country'] = array();
        $this->addActionTest($data, $this->errorTemplate('no country specified'));

        $data['country'] = array('code' => 'Bad country code');
        $this->addActionTest($data, $this->errorTemplate('bad country specified'));

        $data['country'] = array('code' => 'RU');
        $this->addActionTest($data, $this->errorTemplate('no city specified'));

        $data['country'] = array('id' => 36);
        $this->addActionTest($data, $this->errorTemplate('no city specified'));

        $data['city'] = array();
        $this->addActionTest($data, $this->errorTemplate('no city specified'));

        $data['city'] = array('id' => -666);
        $this->addActionTest($data, $this->errorTemplate('bad city specified'));

        $data['city'] = array('id' => 1);
        $this->addActionTest($data, $this->errorTemplate('no credo specified'));

        $data['user']['credo'] = '';
        $this->addActionTest($data, $this->errorTemplate('empty credo specified'));

        $data['user']['credo'] = str_repeat('*', Credo::CREDO_FIELD_LENGTH + 1);
        $this->addActionTest($data, $this->errorTemplate('too long credo'));

        $data['user']['credo'] = '123';
        $this->addActionTest($data, $this->errorTemplate('no picture specified'));

        $data['picture'] = array();
        $this->addActionTest($data, $this->errorTemplate('no picture url specified'));

        $data['picture']['url'] = 'bad url';
        $this->addActionTest($data, $this->errorTemplate('bad picture url specified'));

        $data['picture']['url'] = 'http://www.api.ftw.fl.ru/app/webroot/img/users/1/64.jpg';
        $Users = $this->generate(
            'Users',
            array(
                 'methods' => array(
                     '__checkPictureType',
                 ),
            )
        );
        $Users->expects($this->once())->method('__checkPictureType')->will($this->returnValue(false));
        $this->addActionTest($data, $this->errorTemplate('bad picture format'));

        $Users = $this->generate(
            'Users',
            array(
                 'methods' => array(
                     '__checkPictureType',
                 ),
            )
        );
        $Users->expects($this->once())->method('__checkPictureType')->will($this->returnValue(true));
        $this->addActionTest($data, $this->errorTemplate('no picture rect specified'));

        $data['picture']['rect'] = array('z');
        $Users = $this->generate(
            'Users',
            array(
                 'methods' => array(
                     '__checkPictureType',
                 ),
            )
        );
        $Users->expects($this->once())->method('__checkPictureType')->will($this->returnValue(true));
        $this->addActionTest($data, $this->errorTemplate('bad picture rect parameters specified'));

        $data['picture']['rect'] = array('x' => 0, 'y' => 0, 'w' => 64, 'h' => 64);
        $Users = $this->generate(
            'Users',
            array(
                 'methods' => array(
                     '__checkPictureType',
                     '__buildPictureAvatars',
                 ),
            )
        );
        $Users->expects($this->once())->method('__checkPictureType')->will($this->returnValue(true));
        $Users->expects($this->once())->method('__buildPictureAvatars')->will($this->returnValue(true));
        $this->addActionTest($data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":"1","vk_id":null,"name":null,"is_ready":false,"status":null,"credo":123,"country":{"id":"36","code":"BY","name":"Belarus"},"city":{"id":"1","name":"aaa","region_name":null,"position":{"x":null,"y":null}},"likes":"0","dislikes":"0","world_position":"1","country_position":"1","city_position":"1"}}', true)));
    }

    public function testEdit() {
        $add_data                    = array();
        $add_data['user']            = array();
        $add_data['user']['fb_id']   = 1;
        $add_data['user']['credo']   = '123';
        $add_data['country']         = array();
        $add_data['country']         = array('id' => 36);
        $add_data['city']            = array();
        $add_data['city']            = array('id' => 1);
        $add_data['picture']         = array();
        $add_data['picture']['url']  = 'http://www.api.ftw.fl.ru/app/webroot/img/users/1/64.jpg';
        $add_data['picture']['rect'] = array('x' => 0, 'y' => 0, 'w' => 64, 'h' => 64);
        $Users = $this->generate(
            'Users',
            array(
                 'methods' => array(
                     '__checkPictureType',
                     '__buildPictureAvatars',
                 ),
            )
        );
        $Users->expects($this->once())->method('__checkPictureType')->will($this->returnValue(true));
        $Users->expects($this->once())->method('__buildPictureAvatars')->will($this->returnValue(true));
        $this->addActionTest($add_data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":"1","vk_id":null,"name":null,"is_ready":false,"status":null,"credo":123,"country":{"id":"36","code":"BY","name":"Belarus"},"city":{"id":"1","name":"aaa","region_name":null,"position":{"x":null,"y":null}},"likes":"0","dislikes":"0","world_position":"1","country_position":"1","city_position":"1"}}', true)));

        $data = array();
        $this->editActionTest(1, $data, $this->errorTemplate('access denied'), false);

        $this->editActionTest(2, $data, $this->errorTemplate('no user data specified'));

        $data['user'] = array('id' => 2);
        $this->editActionTest(2, $data, $this->errorTemplate('bad user request'));

        $data['user'] = array('id' => 1, 'credo' => str_repeat('*', Credo::CREDO_FIELD_LENGTH + 1));
        $this->editActionTest(1, $data, $this->errorTemplate('too long credo'));

        $data['user'] = array('id' => 1, 'credo' => '1234');
        $data['picture'] = array();
        $this->editActionTest(1, $data, $this->errorTemplate('no picture url specified'));

        $data['picture']['url'] = 'bad url';
        $this->editActionTest(1, $data, $this->errorTemplate('bad picture url specified'));

        $data['picture']['url'] = 'http://www.api.ftw.fl.ru/app/webroot/img/users/1/256.jpg';
        $this->editActionTest(1, $data, $this->errorTemplate('bad picture format'), true, false);

        $this->editActionTest(1, $data, $this->errorTemplate('no picture rect specified'), true, true);

        $data['picture']['rect'] = array();
        $this->editActionTest(1, $data, $this->errorTemplate('no picture rect specified'), true, true);

        $data['picture']['rect'] = array('z');
        $this->editActionTest(1, $data, $this->errorTemplate('bad picture rect parameters specified'), true, true);

        $data['picture']['rect'] = array('x' => 0, 'y' => 0, 'w' => 256, 'h' => 256);
        $this->editActionTest(1, $data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":"1","vk_id":null,"name":null,"is_ready":false,"status":null,"credo":1234,"country":{"id":"36","code":"BY","name":"Belarus"},"city":{"id":"1","name":"aaa","region_name":null,"position":{"x":null,"y":null}},"likes":"0","dislikes":"0","world_position":"1","country_position":"1","city_position":"1"}}', true)), true, true, true);

        $data['user']['name'] = 'Test name';
        $this->editActionTest(1, $data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":"1","vk_id":null,"name":"Test name","is_ready":true,"status":null,"credo":1234,"country":{"id":"36","code":"BY","name":"Belarus"},"city":{"id":"1","name":"aaa","region_name":null,"position":{"x":null,"y":null}},"likes":"0","dislikes":"0","world_position":"1","country_position":"1","city_position":"1"}}', true)), true, true, true);

        $this->controller->UserRating->findById(1);

        $model_data = array();
        $model_data['positive_votes'] = 101;
        $model_data['negative_votes'] = 33;

        $this->controller->UserRating->save($model_data);

        $this->editActionTest(1, $data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":"1","vk_id":null,"name":"Test name","is_ready":true,"status":null,"credo":1234,"country":{"id":"36","code":"BY","name":"Belarus"},"city":{"id":"1","name":"aaa","region_name":null,"position":{"x":null,"y":null}},"likes":"101","dislikes":"33","world_position":"1","country_position":"1","city_position":"1"}}', true)), true, true, true);

        $this->editActionTest(1, $data, array('answer' => json_decode('{"status":"success","data":{"id":"1","fb_id":"1","vk_id":null,"name":"Test name","is_ready":true,"status":null,"credo":1234,"country":{"id":"36","code":"BY","name":"Belarus"},"city":{"id":"1","name":"aaa","region_name":null,"position":{"x":null,"y":null}},"likes":"101","dislikes":"33","world_position":"1","country_position":"1","city_position":"1"}}', true)), true, true, true);
    }
}