<?

App::uses('AppController', 'Controller');
App::uses('CredoController', 'Controller');

require_once(APPLIBS . 'ResponseControllerTestCase.php');

/**
 * Class CredoControllerTest
 *
 * @property CredoController controller
 */
class CredoControllerTest extends ResponseControllerTestCase {
    public $fixtures = array(
        'app.Country',
        'app.City',
        'app.User',
        'app.Auth',
        'app.Credo',
        'app.UserRating',
    );

    public $credo = null;

    public function setUp() {
        $this->credo = $this->generate(
                            'Credo',
                            array()
        );
    }

    public function testModel() {
        $credo_id = $this->controller->Credo->getCredoId('test');

        $this->assertEquals(1, $credo_id);

        $this->assertEquals($credo_id, $this->controller->Credo->getCredoId('test'));
    }

    public function testIndex() {
        $res      = null;
        $testData = array(
            'method' => 'get',
            'return' => 'vars',
            'data'   => array()
        );
        $testUrl  = '/credo';

        $this->controller->Credo->getCredoId('test1');
        $this->controller->Credo->getCredoId('test2');
        $this->controller->Credo->getCredoId('test3');

        $res = $this->_testAction($testUrl, $testData);
        $this->assertEquals('no search string specified', $res['answer']['data']);

        $testData['data']['q'] = 'te';
        $res                   = $this->_testAction($testUrl, $testData);
        $this->assertEquals(array('test1', 'test2', 'test3'), $res['answer']['data']['credo']);

        $testData['data']['q']        = 'test1';
        $testData['data']['per_page'] = 2;
        $testData['data']['page']     = 1;
        $res                          = $this->_testAction($testUrl, $testData);
        $this->assertEquals(array('test1'), $res['answer']['data']['credo']);

        $testData['data']['q']        = 'test1';
        $testData['data']['per_page'] = 2;
        $testData['data']['page']     = 10;
        $res                          = $this->_testAction($testUrl, $testData);
        $this->assertEquals(array(), $res['answer']['data']['credo']);

        $testData['data']['q']        = 'test33';
        $testData['data']['per_page'] = 2;
        $testData['data']['page']     = 10;
        $res                          = $this->_testAction($testUrl, $testData);
        $this->assertEquals(array(), $res['answer']['data']['credo']);
    }

    private function addActionTest($data) {
        $testData = array(
            'data'   => $data,
            'method' => 'post',
            'return' => 'vars'
        );

        $this->testAction(
             '/users',
             $testData
        );
    }

    public function testView() {
        $res = null;

        $testData = array(
            'method' => 'get',
            'return' => 'vars',
            'data'   => array()
        );
        $testUrl  = '/credo/';

        $this->controller->Credo->getCredoId('test1');
        $this->controller->Credo->getCredoId('test2');
        $this->controller->Credo->getCredoId('test3');

        $user_id = '';
        $res     = $this->_testAction($testUrl . $user_id . '/', $testData);
        $this->assertEquals('no search string specified', $res['answer']['data']);

        $add_data                    = array();
        $add_data['user']            = array();
        $add_data['user']['fb_id']   = 1;
        $add_data['user']['credo']   = 'test1';
        $add_data['country']         = array();
        $add_data['country']         = array('id' => 36);
        $add_data['city']            = array();
        $add_data['city']            = array('id' => 1);
        $add_data['picture']         = array();
        $add_data['picture']['url']  = 'http://www.api.ftw.fl.ru/app/webroot/img/users/1/64.jpg';
        $add_data['picture']['rect'] = array('x' => 0, 'y' => 0, 'w' => 64, 'h' => 64);
        $Users                       = $this->generate(
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
        $this->addActionTest($add_data);
        $this->controller->User->saveField('is_ready', 1);

        $user_id = 1;
        $res = $this->_testAction($testUrl . $user_id . '/', $testData);
        $this->assertEquals(1, $res['answer']['data']['users'][0]['id']);
        $this->assertEquals(1, $res['answer']['data']['pages']['total']);
    }
}