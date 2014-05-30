<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Rem
 * Date: 14.07.13
 * Time: 18:40
 */

/**
 * Доведение рейтинга до 10 у пользователей с рейтингом менее 10
 *
 * Class RatingIncreaseFromMinTask
 *
 * @property User       User
 * @property UserRating UserRating
 * @property City       City
 * @property UserLock   UserLock
 * @property UserVote   UserVote
 * @property HallOfFame HallOfFame
 */
class RatingIncreaseFromMinTask extends AppShell {
    const DAILY_INCREASE_RATING_MIN_VALUE = 10;

    public $uses = array(
        'User',
        'UserRating',
        'UserLock',
        'City',
        'UserVote',
        'HallOfFame'
    );

    public function execute() {
        $increase_result = false;

        $sub2_db = $this->UserVote->getDataSource();
        $sub2    = $sub2_db->buildStatement(
            array(
                 'fields'     => array(
                     'UserVote_sub.user_id',
                     'COUNT(*) as cnt'
                 ),
                 'table'      => $sub2_db->fullTableName($this->UserVote),
                 'alias'      => 'UserVote_sub',
                 'limit'      => 1,
                 'offset'     => null,
                 'joins'      => array(),
                 'conditions' => array(
                     'UserVote_sub.created >=' => date('Y-m-d 00:00:00'),
                     'UserVote_sub.vote_type'  => UserVote::VOTE_TYPE_SYSTEM_INCREASE,
                 ),
                 'order'      => null,
                 'group'      => array('UserVote_sub.user_id')
            ),
            $this->UserVote
        );

        $joins   = array();
        $joins[] = array(
            'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array("UserRating.user_id = User.id")
        );
        $joins[] = array(
            'table'      => "({$sub2})",
            'alias'      => 'UserVote',
            'type'       => 'LEFT',
            'conditions' => array("UserVote.user_id = User.id")
        );

        $losers = $this->User->find('all',
                                     array(
                                          'conditions' => array(
                                              'User.is_ready'           => 1,
                                              'User.is_in_hall_of_fame' => 0,
                                              'IFNULL(UserVote.cnt, 0)' => 0,
                                              'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) < 10'
                                          ),
                                          'joins'      => $joins,
                                          'fields'     => array(
                                              'User.id',
                                              'IFNULL(UserRating.positive_votes, 0) as likes',
                                              'IFNULL(UserRating.negative_votes, 0) as dislikes'
                                          )
                                     ));

        $users = Set::combine($losers, '{n}.User.id', '{n}.0');
        $ids   = array_keys($users);

        $count            = 0;
        $total_ids_amount = count($ids);
        for ($i = 0; $i < 3; ++$i) {
            $ids_amount = count($ids);
            for ($j = 0; $j < $ids_amount; ++$j) {
                $lock_id = $this->UserLock->lock($ids[$j], UserLock::LOCK_TYPE_VOTE);
                if ($lock_id) {
                    $users[$ids[$j]]['lock_id'] = $lock_id;
                    ++$count;
                    unset($ids[$j]);
                }
            }
            if (empty($ids)) {
                break;
            }
        }

        $all_users_locked = ($count == $total_ids_amount);

        $count = 0;
        if ($all_users_locked) {
            foreach ($users as $id => $user) {
                $likes    = $user['likes'];
                $dislikes = $user['dislikes'];
                $rating   = $likes - $dislikes;

                $increase_sum = self::DAILY_INCREASE_RATING_MIN_VALUE - $rating;

                if ($increase_sum > 0) {
                    $res = $this->UserVote->vote(null, $id, $increase_sum, UserVote::VOTE_TYPE_SYSTEM_INCREASE, false);
                } else {
                    $res = true;
                }

                if ($res) {
                    ++$count;
                }
            }

            if ($count == $total_ids_amount) {
                $increase_result = true;
            }

            $this->HallOfFame->checkLeader();
        }

        foreach ($users as $user) {
            if (isset($user['lock_id'])) {
                $this->UserLock->release($user['lock_id']);
            }
        }

        $this->_out(__CLASS__ . ($increase_result ? '_SUCCESS_' : '_FAILED_'));
    }
}