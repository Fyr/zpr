<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.05.12
 * Time: 2:26
 * To change this template use File | Settings | File Templates.
 */
class User extends AppModel {
    public $PictureTypes = array(
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WBMP => 'wbmb',
        IMAGETYPE_XBM  => 'xbm'
    );

    public $PictureSizes = array(
        '4'   => array('width' => 4, 'height' => 4),
        '6'   => array('width' => 6, 'height' => 6),
        '8'   => array('width' => 8, 'height' => 8),
        '12'  => array('width' => 12, 'height' => 12),
        '16'  => array('width' => 16, 'height' => 16),
        '20'  => array('width' => 20, 'height' => 20),
        '24'  => array('width' => 24, 'height' => 24),
        '28'  => array('width' => 28, 'height' => 28),
        '32'  => array('width' => 32, 'height' => 32),
        '36'  => array('width' => 36, 'height' => 36),
        '48'  => array('width' => 48, 'height' => 48),
        '50'  => array('width' => 50, 'height' => 50),
        '64'  => array('width' => 64, 'height' => 64),
        '75'  => array('width' => 75, 'height' => 75),
        '88'  => array('width' => 88, 'height' => 88),
        '96'  => array('width' => 96, 'height' => 96),
        '128' => array('width' => 128, 'height' => 128),
        '256' => array('width' => 256, 'height' => 256),
    );

    function getUserActions($current_user_id, $user_id) {
        $res = array();

        if ($current_user_id and $current_user_id != $user_id) {
            $vote = ClassRegistry::init('UserVote');
            $available_votes = $vote->getAvailableDailyVotes($current_user_id, $user_id);
            if ($available_votes > 0) {
                $res[] = 'vote';
            }

            $res[] = 'message';
            $res[] = 'comment';
        }

        return $res;
    }

    function getUser($id, $is_ready = true, $with_hall_of_fame = true, $current_user_id = 0) {
        $conditions = array();
        $conditions['User.id'] = $id;
        if ($is_ready) {
            $conditions['User.is_ready'] = 1;
        }
        if (!$with_hall_of_fame) {
            $conditions['User.is_in_hall_of_fame'] = 0;
        }

        $country    = ClassRegistry::init('Country');
        $city       = ClassRegistry::init('City');
        $credo      = ClassRegistry::init('Credo');
        $userRating = ClassRegistry::init('UserRating');

        $joins   = array();
        $joins[] = array(
            'table'      => $country->getDataSource()->fullTableName($country),
            'alias'      => 'Country',
            'type'       => 'LEFT',
            'conditions' => array('Country.id = User.country_id')
        );
        $joins[] = array(
            'table'      => $city->getDataSource()->fullTableName($city),
            'alias'      => 'City',
            'type'       => 'LEFT',
            'conditions' => array('City.id = User.city_id')
        );
        $joins[] = array(
            'table'      => $credo->getDataSource()->fullTableName($credo),
            'alias'      => 'Credo',
            'type'       => 'LEFT',
            'conditions' => array('Credo.id = User.credo_id')
        );
        $joins[] = array(
            'table'      => $userRating->getDataSource()->fullTableName($userRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array(
                'UserRating.user_id = User.id',
                'UserRating.country_id = User.country_id',
                'UserRating.city_id = User.city_id'
            )
        );

        $user = $this->find('first', array(
            'conditions' => $conditions,
            'joins'      => $joins,
            'fields' => array(
                'User.id',
                'User.fb_id',
                'User.vk_id',
                'User.name',
                'User.is_ready',
                'User.is_new',
                'User.email',
                'User.status',
                'User.balance',
                'Credo.text',
                'Country.id',
                'Country.name',
                'City.id',
                'City.name',
                'City.region_name',
                'IFNULL(UserRating.positive_votes, 0) as likes',
                'IFNULL(UserRating.negative_votes, 0) as dislikes'
            )
        ));

        if (!$user or empty($user)) {
            return false;
        }

        $user_rating_sum = $user[0]['likes'] - $user[0]['dislikes'];

        $joins   = array();
        $joins[] = array(
            'table'      => $userRating->getDataSource()->fullTableName($userRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array(
                'UserRating.user_id = User.id',
            )
        );

        $cond = array(
            'User.is_ready' => 1,
            'OR' => array(
                'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                'AND'                                                                => array(
                    'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                    'User.name <'                                                      => $user['User']['name']
                )
            )
        );
        $cond['User.is_in_hall_of_fame'] = 0;
        $world_rating = $this->find('first', array(
            'conditions' => $cond,
            'fields' => array('COUNT(DISTINCT User.id) + 1 as rating'),
            'joins'  => $joins
        ));
        if (!empty($user['Country']['id'])) {
            $cond['User.country_id'] = $user['Country']['id'];
            $country_rating = $this->find('first', array(
                'conditions' => $cond,
                'fields' => array('COUNT(DISTINCT User.id) + 1 as rating'),
                'joins'  => $joins
            ));
            unset($cond['User.country_id']);
        } else {
            $country_rating = array(array('rating' => null));
        }
        if (!empty($user['City']['id'])) {
            $cond['User.city_id'] = $user['City']['id'];
            $city_rating = $this->find('first', array(
                'conditions' => $cond,
                'fields' => array('COUNT(DISTINCT User.id) + 1 as rating'),
                'joins'  => $joins
            ));
            unset($cond['User.city_id']);
        } else {
            $city_rating = array(array('rating' => null));
        }

        // Самый полный TUser согласно спецификациям
        $answer             = array();
        $answer['id']       = $user['User']['id'];
        $answer['fb_id']    = $user['User']['fb_id'];
        $answer['vk_id']    = $user['User']['vk_id'];
        $answer['name']     = $user['User']['name'];
        $answer['is_ready'] = $user['User']['is_ready'];
        $answer['is_new']   = $user['User']['is_new'];
        $answer['email']    = $user['User']['email'];
        $answer['status']   = $user['User']['status'];
        $answer['balance']   = $user['User']['balance'];
        $answer['credo']    = $user['Credo']['text'];

        $answer['country']         = array();
        $answer['country']['id']   = $user['Country']['id'];
        $answer['country']['name'] = $user['Country']['name'];

        $answer['city']                  = array();
        $answer['city']['id']            = $user['City']['id'];
        $answer['city']['name']          = $user['City']['name'];
        $answer['city']['region_name']   = $user['City']['region_name'];

        $answer['likes']    = $user[0]['likes'];
        $answer['dislikes'] = $user[0]['dislikes'];

        $answer['world_position']  = $world_rating[0]['rating'];
        $answer['country_position'] = $country_rating[0]['rating'];
        $answer['city_position']   = $city_rating[0]['rating'];

        if ($current_user_id !== 0) {
            $answer['actions'] = $this->getUserActions($current_user_id, $answer['id']);
        }

        return $answer;
    }

    function getUsers($ids, $is_ready = true, $order = array('User.name'), $with_hall_of_fame = true, $current_user_id = 0) {
        $conditions = array();
        $conditions['User.id'] = $ids;
        if ($is_ready) {
            $conditions['User.is_ready'] = 1;
        }
        if (!$with_hall_of_fame) {
            $conditions['User.is_in_hall_of_fame'] = 0;
        }

        $country    = ClassRegistry::init('Country');
        $city       = ClassRegistry::init('City');
        $credo      = ClassRegistry::init('Credo');
        $userRating = ClassRegistry::init('UserRating');

        $joins   = array();
        $joins[] = array(
            'table'      => $country->getDataSource()->fullTableName($country),
            'alias'      => 'Country',
            'type'       => 'LEFT',
            'conditions' => array('Country.id = User.country_id')
        );
        $joins[] = array(
            'table'      => $city->getDataSource()->fullTableName($city),
            'alias'      => 'City',
            'type'       => 'LEFT',
            'conditions' => array('City.id = User.city_id')
        );
        $joins[] = array(
            'table'      => $credo->getDataSource()->fullTableName($credo),
            'alias'      => 'Credo',
            'type'       => 'LEFT',
            'conditions' => array('Credo.id = User.credo_id')
        );
        $joins[] = array(
            'table'      => $userRating->getDataSource()->fullTableName($userRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array(
                'UserRating.user_id = User.id',
                'UserRating.country_id = User.country_id',
                'UserRating.city_id = User.city_id'
            )
        );

        $users = $this->find('all',
                             array(
                                  'conditions' => $conditions,
                                  'joins'      => $joins,
                                  'fields'     => array(
                                      'User.id',
                                      'User.fb_id',
                                      'User.vk_id',
                                      'User.name',
                                      'User.is_ready',
                                      'User.is_new',
                                      'User.email',
                                      'User.status',
                                      'User.balance',
                                      'Credo.text',
                                      'Country.id',
                                      'Country.name',
                                      'City.id',
                                      'City.name',
                                      'City.region_name',
                                      'IFNULL(UserRating.positive_votes, 0) as likes',
                                      'IFNULL(UserRating.negative_votes, 0) as dislikes'
                                  ),
                                  'order' => $order
                             ));

        if (!$users or empty($users)) {
            return false;
        }

        $answer = array();
        foreach ($users as $user) {
            $user_rating_sum = $user[0]['likes'] - $user[0]['dislikes'];

            $cond = array('User.id = UserRating.user_id');
            if ($is_ready) {
                $cond['User.is_ready'] = 1;
            }
            $cond['User.is_in_hall_of_fame'] = 0;

            $joins   = array();
            $joins[] = array(
                'table'      => $this->getDataSource()->fullTableName($this),
                'alias'      => 'User',
                'type'       => 'INNER',
                'conditions' => $cond
            );

            $world_rating = $userRating->find('first', array(
                                                            'conditions' => array(
                                                                'OR' => array(
                                                                    'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                                                    'AND'                                                                => array(
                                                                        'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                                                        'User.name <'                                                      => $user['User']['name']
                                                                    )
                                                                )
                                                            ),
                                                            'fields' => array('COUNT(DISTINCT UserRating.user_id) + 1 as rating'),
                                                            'joins'  => $joins
                                                       ));
            if (!empty($user['Country']['id'])) {
                $country_rating = $userRating->find('first', array(
                                                                  'conditions' => array(
                                                                      'UserRating.country_id' => $user['Country']['id'],
                                                                      'OR' => array(
                                                                          'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                                                          'AND'                                                                => array(
                                                                              'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                                                              'User.name <'                                                      => $user['User']['name']
                                                                          )
                                                                      )
                                                                  ),
                                                                  'fields' => array('COUNT(DISTINCT UserRating.user_id) + 1 as rating'),
                                                                  'joins'  => $joins
                                                             ));
            } else {
                $country_rating = array(array('rating' => null));
            }
            if (!empty($user['City']['id'])) {
                $city_rating = $userRating->find('first', array(
                                                               'conditions' => array(
                                                                   'UserRating.city_id' => $user['City']['id'],
                                                                   'OR' => array(
                                                                       'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                                                       'AND'                                                                => array(
                                                                           'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                                                           'User.name <'                                                      => $user['User']['name']
                                                                       )
                                                                   )
                                                               ),
                                                               'fields' => array('COUNT(DISTINCT UserRating.user_id) + 1 as rating'),
                                                               'joins'  => $joins
                                                          ));
            } else {
                $city_rating = array(array('rating' => null));
            }

            // Самый полный TUser согласно спецификациям
            $ans             = array();
            $ans['id']       = $user['User']['id'];
            $ans['fb_id']    = $user['User']['fb_id'];
            $ans['vk_id']    = $user['User']['vk_id'];
            $ans['name']     = $user['User']['name'];
            $ans['is_ready'] = $user['User']['is_ready'];
            $ans['is_new']   = $user['User']['is_new'];
            $ans['email']    = $user['User']['email'];
            $ans['status']   = $user['User']['status'];
            $ans['balance']   = $user['User']['balance'];
            $ans['credo']    = $user['Credo']['text'];

            $ans['country']         = array();
            $ans['country']['id']   = $user['Country']['id'];
            $ans['country']['name'] = $user['Country']['name'];

            $ans['city']                  = array();
            $ans['city']['id']            = $user['City']['id'];
            $ans['city']['name']          = $user['City']['name'];
            $ans['city']['region_name']   = $user['City']['region_name'];

            $ans['likes']    = $user[0]['likes'];
            $ans['dislikes'] = $user[0]['dislikes'];

            $ans['world_position']   = $world_rating[0]['rating'];
            $ans['country_position'] = $country_rating[0]['rating'];
            $ans['city_position']    = $city_rating[0]['rating'];

            if ($current_user_id !== 0) {
                $ans['actions'] = $this->getUserActions($current_user_id, $ans['id']);
            }

            $answer[] = $ans;
        }

        return $answer;
    }

    function clearUserData(&$user_data) {
        if (!$user_data or !is_array($user_data) or empty($user_data)) {
            return;
        }

        unset(
            $user_data['id'],
            $user_data['created'],
            $user_data['modified'],
            $user_data['country_id'],
            $user_data['city_id'],
            $user_data['last_position'],
            $user_data['post_id'],
            $user_data['likes'],
            $user_data['dislikes'],
            $user_data['world_position'],
            $user_data['country_position'],
            $user_data['city_position']
        );
    }

    function add($user_data) {
        $this->create();
        $user_data['is_new'] = 1;
        return $this->update($user_data);
    }

    function update($user_data, $id = null) {
        if (!$user_data or !is_array($user_data) or empty($user_data)) {
            return false;
        }
        $lNew = (isset($user_data['is_new']) && $user_data['is_new']);
        $this->clearUserData($user_data);
        $user_data['is_new'] = 0;

        if ($id !== null) {
            $this->id = $id;
        }
        // Если указана страна
        if (isset($user_data['country']) and is_array($user_data['country']) and !empty($user_data['country'])) {
            if (isset($user_data['country']['id']) and $user_data['country']['id']) {
                $country = ClassRegistry::init('Country');
                $country_id = $country->field('id', array('id' => $user_data['country']['id']));
            } elseif (isset($user_data['country']['code']) and $user_data['country']['code']) {
                $country = ClassRegistry::init('Country');

                $country_id = $country->field('id', array('code' => $user_data['country']['code']));
            } else {
                return false;
            }

            if (!$country_id) {
                return false;
            }

            $user_data['country_id'] = $country_id;
            $user_data['city_id'] = null;
        }
        
        // Если указан город
        if (isset($user_data['city']) and is_array($user_data['city']) and !empty($user_data['city'])) {
            if (isset($user_data['city']['id']) and $user_data['city']['id']) {
                $city = ClassRegistry::init('City');

                $conditions = array('id' => $user_data['city']['id']);
                if (isset($user_data['country_id'])) {
                    $country_id = $user_data['country_id'];
                } elseif (!empty($this->id)) {
                    $country_id = $this->field('country_id', array('id' => $this->id));
                } else {
                    return false;
                }
                $conditions['country_id'] = $country_id;

                $city_id = $city->field('id', $conditions);
            } else {
                return false;
            }

            if (!$city_id) {
                return false;
            }

            $user_data['city_id'] = $city_id;
        }

        // Если указано кредо
        if (isset($user_data['credo']) and !empty($user_data['credo'])) {
            $credo = ClassRegistry::init('Credo');
            $user_data['credo_id'] = $credo->getCredoId($user_data['credo']);
        }
        
        try {
            $result = $this->save($user_data, true, array_keys($user_data));
            if ($lNew) {
        		$balanceModel = ClassRegistry::init('BalanceHistory');
        		$balanceModel->addOperation(BalanceHistory::BH_REGISTER, 10, $this->id);
        	}
        } catch (Exception $e) {
            $result = false;
        }

        if ($result) {
            $result = $this->getUser($this->id, false);
        }

        return $result;
    }

    /**
     * @param int $credo_id
     *
     * @return int|null ID лидера или null в случае если не смогли найти
     */
    public function getCredoLeader($credo_id) {
        $userRating = ClassRegistry::init('UserRating');

        $joins   = array();
        $joins[] = array(
            'table'      => $userRating->getDataSource()->fullTableName($userRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array('UserRating.user_id = User.id')
        );

        $leader = $this->find('first', array(
            'conditions' => array(
                'User.credo_id' => $credo_id,
                'User.is_ready' => 1
            ),
            'joins'      => $joins,
            'fields'     => array('User.id'),
            'order'      => array(
                'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                'User.name'
            )));

        if ($leader and !empty($leader)) {
            return $leader['User']['id'];
        }

        return null;
    }
}