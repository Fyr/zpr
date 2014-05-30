<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Андрей
 * Date: 09.02.14
 * Time: 14:50
 * To change this template use File | Settings | File Templates.
 */

App::uses('HallOfFame', 'Model');
App::uses('UserVote', 'Model');
App::uses('User', 'Model');
App::uses('UserRating', 'Model');

/**
 * Class UserVote
 *
 * @property HallOfFame HallOfFame
 * @property UserVote   UserVote
 * @property User       User
 * @property UserRating UserRating
 */
class UserVoteTest extends CakeTestCase {
    public $fixtures = array(
        'app.User',
        'app.UserRating',
        'app.UserVote',
        'app.HallOfFame',
    );

    public function setUp() {
        parent::setUp();
        $this->HallOfFame = ClassRegistry::init('HallOfFame');
        $this->UserVote   = ClassRegistry::init('UserVote');
        $this->User       = ClassRegistry::init('User');
        $this->UserRating = ClassRegistry::init('UserRating');
    }

    public function testVote() {
        // Сделаем тестового пользователя
        $this->User->create();
        $this->User->save(
                   array(
                       'User' => array(
                           'id'         => 1,
                           'name'       => 'TestUser',
                           'is_ready'   => 1,
                           'country_id' => 1
                       )
                   )
        );
        $this->User->create();
        $this->User->save(
                   array(
                       'User' => array(
                           'id'         => 2,
                           'name'       => 'TestUser2',
                           'is_ready'   => 1,
                           'country_id' => 1
                       )
                   )
        );

        $res = $this->UserVote->vote(1, 2, 0, UserVote::VOTE_TYPE_SYSTEM, false);
        $this->assertEquals(true, $res);

        $res = $this->UserVote->vote(1, 2, 10, UserVote::VOTE_TYPE_SYSTEM, false);
        $this->assertEquals(true, $res);
    }

    public function testGetAvailableDailyVotes() {
        $res = $this->UserVote->getAvailableDailyVotes(2, 1);
        $this->assertEquals(0, $res);

        // Сделаем тестового пользователя
        $this->User->create();
        $this->User->save(
                   array(
                       'User' => array(
                           'id'         => 1,
                           'name'       => 'TestUser',
                           'is_ready'   => 1,
                           'country_id' => 1
                       )
                   )
        );
        $this->User->create();
        $this->User->save(
                   array(
                       'User' => array(
                           'id'         => 2,
                           'name'       => 'TestUser2',
                           'is_ready'   => 1,
                           'country_id' => 1
                       )
                   )
        );

        $res = $this->UserVote->vote(null, 2, 10, UserVote::VOTE_TYPE_SYSTEM, false);
        $this->assertEquals(true, $res);

        $res = $this->UserVote->vote(null, 1, 10, UserVote::VOTE_TYPE_SYSTEM, false);
        $this->assertEquals(true, $res);

        $res = $this->UserVote->getAvailableDailyVotes(2, 1);
        $this->assertEquals(3, $res);

        $res = $this->UserVote->getAvailableDailyVotes(1, 2);
        $this->assertEquals(3, $res);

        $res = $this->UserVote->vote(1, 2, 2);
        $this->assertEquals(true, $res);

        $res = $this->UserVote->getAvailableDailyVotes(1, 2);
        $this->assertEquals(1, $res);

        $res = $this->UserVote->vote(1, 2, -1);
        $this->assertEquals(true, $res);

        $res = $this->UserVote->getAvailableDailyVotes(1, 2);
        $this->assertEquals(0, $res);

        $res = $this->UserVote->getAvailableDailyVotes(2, 1);
        $this->assertEquals(4, $res);
    }
}