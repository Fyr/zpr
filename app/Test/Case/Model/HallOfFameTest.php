<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Андрей
 * Date: 14.07.13
 * Time: 20:32
 * To change this template use File | Settings | File Templates.
 */

App::uses('HallOfFame', 'Model');
App::uses('User', 'Model');
App::uses('UserRating', 'Model');

/**
 * Class HallOfFameTest
 *
 * @property HallOfFame HallOfFame
 * @property User       User
 * @property UserRating UserRating
 */
class HallOfFameTest extends CakeTestCase {
    public $fixtures = array(
        'app.User',
        'app.UserRating',
        'app.HallOfFame',
    );

    public function setUp() {
        parent::setUp();
        $this->HallOfFame = ClassRegistry::init('HallOfFame');
        $this->User       = ClassRegistry::init('User');
        $this->UserRating = ClassRegistry::init('UserRating');
    }

    public function testCheckLeader() {
        // Когда ничего нет, то и проверка провалится
        $res = $this->HallOfFame->checkLeader();
        $this->assertFalse($res);

        // Сделаем тестового пользователя
        $this->User->create();
        $this->User->save(
            array(
                 'User' => array(
                     'id'       => 1,
                     'name'     => 'TestUser',
                     'is_ready' => 1
                 )
            )
        );

        // Должна создаться запись в зале славы
        $res = $this->HallOfFame->checkLeader();
        $this->assertTrue($res);

        $res = $this->HallOfFame->find('first');
        $this->assertEquals(1, $res['HallOfFame']['user_id']);
        $this->assertNull($res['HallOfFame']['achieved']);
        $row = $res;

        // Ничего не должно поменяться
        $res = $this->HallOfFame->checkLeader();
        $this->assertTrue($res);

        $res = $this->HallOfFame->find('first');
        $this->assertEquals($row, $res);

        // Сделаем второго тестового пользователя
        $this->User->create();
        $this->User->save(
            array(
                 'User' => array(
                     'id'       => 2,
                     'name'     => 'TestUser2',
                     'is_ready' => 1
                 )
            )
        );

        // Установим второму пользователю заранее высокий рейтинг
        $this->UserRating->create();
        $this->UserRating->save(
            array(
                 'UserRating' => array(
                     'id'             => 1,
                     'user_id'        => 2,
                     'positive_votes' => 100
                 )
            )
        );

        // Должна удалиться первая запись и создаться новая о втором пользователе
        $res = $this->HallOfFame->checkLeader();
        $this->assertTrue($res);

        $res = $this->HallOfFame->find('all');
        $this->assertEquals(1, count($res));

        $this->assertEquals(2, $res[0]['HallOfFame']['user_id']);
        $this->assertNull($res[0]['HallOfFame']['achieved']);

        // Изменим дату регистрации пользователя в зале славы так, чтобы уже прошло 2 недели
        $this->HallOfFame->create();
        $this->HallOfFame->id = 2;
        $this->HallOfFame->saveField('created', date('Y-m-d H:i:s', strtotime('-15 days')));

        // Пользователь должен перенестись в зал славы
        $res = $this->HallOfFame->checkLeader();
        $this->assertTrue($res);

        $res = $this->User->findById(2);
        $this->assertEquals(1, $res['User']['is_in_hall_of_fame']);

        $res = $this->User->findById(1);
        $this->assertEquals(0, $res['User']['is_in_hall_of_fame']);

        $res = $this->HallOfFame->findByUserId(2);
        $this->assertNotNull($res['HallOfFame']['achieved']);
    }
}