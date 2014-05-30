<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Rem
 * Date: 14.07.13
 * Time: 18:40
 */

/**
 * Уменьшение рейтинга лидеров на 1% голосов
 *
 * Class RatingReduceTask
 *
 * @property User       User
 * @property UserRating UserRating
 * @property City       City
 * @property UserLock   UserLock
 * @property UserVote   UserVote
 * @property HallOfFame HallOfFame
 */
class RatingReduceTask extends AppShell {
    const DAILY_RATING_REDUCE_PERCENT     = 1;
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
        $reduce_result   = false;

        $sub_db      = $this->User->getDataSource();
        $sub_joins   = array();
        $sub_joins[] = array(
            'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
            'alias'      => 'UserRating_sub',
            'type'       => 'LEFT',
            'conditions' => array('UserRating_sub.user_id = User_sub.id')
        );
        $sub         = $sub_db->buildStatement(
            array(
                 'fields'     => array('User_sub.id'),
                 'table'      => $sub_db->fullTableName($this->User),
                 'alias'      => 'User_sub',
                 'limit'      => 1,
                 'offset'     => null,
                 'joins'      => $sub_joins,
                 'conditions' => array(
                     'User_sub.city_id = City.id',
                     'User_sub.is_ready'           => 1,
                     'User_sub.is_in_hall_of_fame' => 0
                 ),
                 'order'      => array(
                     'IFNULL(UserRating_sub.positive_votes - UserRating_sub.negative_votes, 0) DESC',
                     'User_sub.name'
                 ),
                 'group'      => null
            ),
            $this->User
        );

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
                     'UserVote_sub.vote_type'  => UserVote::VOTE_TYPE_SYSTEM_REDUCE,
                 ),
                 'order'      => null,
                 'group'      => array('UserVote_sub.user_id')
            ),
            $this->UserVote
        );

        $joins   = array();
        $joins[] = array(
            'table'      => $this->User->getDataSource()->fullTableName($this->User),
            'alias'      => 'User',
            'type'       => 'INNER',
            'conditions' => array(
                'User.city_id = City.id',
                "User.id = ({$sub})"
            )
        );
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

        $conditions = array(
            'User.is_ready'           => 1,
            'User.is_in_hall_of_fame' => 0,
            'IFNULL(UserVote.cnt, 0)' => 0
        );

        $leaders = $this->City->find('all',
                                     array(
                                          'conditions' => $conditions,
                                          'joins'      => $joins,
                                          'fields'     => array(
                                              'User.id',
                                              'IFNULL(UserRating.positive_votes, 0) as likes',
                                              'IFNULL(UserRating.negative_votes, 0) as dislikes'
                                          ),
                                          'order'      => array(
                                              'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                              'User.name'
                                          )
                                     ));

        $users = Set::combine($leaders, '{n}.User.id', '{n}.0');
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

//        debug($ids);
//        debug($count);
//        debug($total_ids_amount);
//        debug($all_users_locked);
//        debug($total_ids_amount);

        $count = 0;
        if ($all_users_locked) {
            foreach ($users as $id => $user) {
                $likes    = $user['likes'];
                $dislikes = $user['dislikes'];
                $rating   = $likes - $dislikes;

                $reduce_sum = ceil($rating * self::DAILY_RATING_REDUCE_PERCENT / 100);
                if ($rating - $reduce_sum < self::DAILY_INCREASE_RATING_MIN_VALUE) {
                    $reduce_sum = $rating - self::DAILY_INCREASE_RATING_MIN_VALUE;
                }

                if ($reduce_sum > 0) {
                    $res = $this->UserVote->vote(null, $id, -$reduce_sum, UserVote::VOTE_TYPE_SYSTEM_REDUCE, false);
                } else {
                    $res = true;
                }

                if ($res) {
                    ++$count;
                }
//                debug($id);
//                debug($rating);
//                debug($reduce_sum);
//                debug($res);
//                break;
            }

            if ($count == $total_ids_amount) {
                $reduce_result = true;
            }

            $this->HallOfFame->checkLeader();
        }

        foreach ($users as $user) {
            if (isset($user['lock_id'])) {
                $this->UserLock->release($user['lock_id']);
            }
        }

        $this->_out(__CLASS__ . ($reduce_result ? '_SUCCESS_' : '_FAILED_'));
    }
}