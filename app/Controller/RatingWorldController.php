<?php
class RatingWorldController extends AppController {
    public $uses = array('Country',
                         'City',
                         'User',
                         'Credo',
                         'UserRating');
    public $components = array('RequestHandler');

    /**
     * Лидеры мира
     *
     * GET /rating/world/
     *
     * Параметры:
     *     per_page {Number} Кол-во результатов на страницу
     *     page {Number} Номер страницы начиная с первой
     *
     * Ответ:
     *     leader {TUser},
     *     users {Array of TUser},
     *     pages {TPages}
     */
    public function index() {
    	try {
        $data    = array();

        $page = ($page = intval($this->request->query('page'))) ? $page : 1;
        $per_page = ($per_page = intval($this->request->query('per_page'))) ? $per_page : 25;

        $conditions = array(
            'User.is_ready'           => 1,
            'User.is_in_hall_of_fame' => 0
        );

        $joins = array();
        $joins[] = array(
            'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array('UserRating.user_id = User.id')
        );
        $joins[] = array(
            'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
            'alias'      => 'Country',
            'type'       => 'LEFT',
            'conditions' => array('Country.id = User.country_id')
        );
        $joins[] = array(
            'table'      => $this->City->getDataSource()->fullTableName($this->City),
            'alias'      => 'City',
            'type'       => 'LEFT',
            'conditions' => array('City.id = User.city_id')
        );
        $joins[] = array(
            'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
            'alias'      => 'Credo',
            'type'       => 'LEFT',
            'conditions' => array('Credo.id = User.credo_id')
        );
        $users_count = $this->User->find('count', array('conditions' => $conditions,
                                                        'joins'      => $joins));

        // Если не первая страница рейтинга, то лидера придётся выбирать отдельно
        $leader = array();
        if ($page != 1) {
            $leader = $this->User->find('first', array('conditions' => $conditions,
                                                       'joins'      => $joins,
                                                       'fields'     => array(
                                                           'User.id',
                                                       ),
                                                       'order'      => array(
                                                           'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                                           'User.name'
                                                       )));

            if (!empty($leader)) {
                $leader_id = $leader['User']['id'];
                $leader = $this->User->getUser($leader_id, true, true, $this->currentUserId);
            }
        }

        if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
            $data['leader'] = $leader;
            $data['users']  = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $users_count
            );
        } elseif ($users_count > 0) {
            $data['leader'] = $leader;
            $data['users']  = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $users_count
            );

            $users = $this->User->find('all', array('conditions' => $conditions,
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
                                                        'Credo.text',
                                                        'Country.id',
                                                        'Country.name',
                                                        'City.id',
                                                        'City.name',
                                                        'City.region_name',
                                                        'IFNULL(UserRating.positive_votes, 0) as likes',
                                                        'IFNULL(UserRating.negative_votes, 0) as dislikes',
//                                                        "({$world_rating_select}) as world_rating"
                                                    ),
                                                    'order'      => array(
                                                        'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                                        'User.name'
                                                    ),
                                                    'limit'      => $per_page,
                                                    'offset'     => ($page - 1) * $per_page));

            // Временное решение для полной информации по пользователям
            $joins   = array();
            $joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array(
                    'UserRating.user_id = User.id'
                )
            );

            $row_count = 1 + ($page - 1) * $per_page;
            foreach ($users as $user) {
                // Временное решение для полной информации по пользователям
                $user_rating_sum = $user[0]['likes'] - $user[0]['dislikes'];

                // Временное решение для полной информации по пользователям
                if (!empty($user['Country']['id'])) {
                    $country_rating = $this->User->find(
                        'first',
                        array(
                             'conditions' => array(
                                 'User.is_ready'           => 1,
                                 'User.is_in_hall_of_fame' => 0,
                                 'User.country_id'         => $user['Country']['id'],
                                 'OR'                      => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                     'AND'                                                                           => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                         'User.name <'                                                                 => $user['User']['name']
                                     )
                                 )
                             ),
                             'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                             'joins'      => $joins
                        ));
                } else {
                    $country_rating = array(array('rating' => null));
                }
                // Временное решение для полной информации по пользователям
                if (!empty($user['City']['id'])) {
                    $city_rating = $this->User->find(
                        'first',
                        array(
                             'conditions' => array(
                                 'User.is_ready'           => 1,
                                 'User.is_in_hall_of_fame' => 0,
                                 'User.city_id'            => $user['City']['id'],
                                 'OR'                      => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                     'AND'                                                                           => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                         'User.name <'                                                                 => $user['User']['name']
                                     )
                                 )
                             ),
                             'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                             'joins'      => $joins
                        ));
                } else {
                    $city_rating = array(array('rating' => null));
                }

                $ans = array();

                $ans['id']       = $user['User']['id'];
                $ans['fb_id']    = $user['User']['fb_id'];
                $ans['vk_id']    = $user['User']['vk_id'];
                $ans['name']     = $user['User']['name'];
                $ans['is_ready'] = $user['User']['is_ready'];
                $ans['is_new']   = $user['User']['is_new'];
                $ans['email']    = $user['User']['email'];
                $ans['status']   = $user['User']['status'];
                $ans['credo']    = $user['Credo']['text'];

                $ans['country']         = array();
                $ans['country']['id']   = $user['Country']['id'];
                $ans['country']['name'] = $user['Country']['name'];

                $ans['city']                  = array();
                $ans['city']['id']            = $user['City']['id'];
                $ans['city']['name']          = $user['City']['name'];
                $ans['city']['region_name']   = $user['City']['region_name'];

                $ans['likes']    = intval($user[0]['likes']);
                $ans['dislikes'] = intval($user[0]['dislikes']);

//                $ans['world_position'] = $user[0]['world_rating'];
                $ans['world_position']   = $row_count;
                // Временное решение для полной информации по пользователям
                $ans['country_position'] = intval($country_rating[0]['rating']);
                $ans['city_position']    = intval($city_rating[0]['rating']);

                $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                $data['users'][] = $ans;

                if ($page == 1 and empty($data['leader'])) {
                    $data['leader'] = $ans;
                }

                ++$row_count;
            }
        } else {
            $data['leader'] = $leader;
            $data['users']  = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $users_count
            );
        }

        	$this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }
    }

    /**
     * Лидеры мира по странам
     *
     * GET /rating/world/countries/
     *
     * Параметры:
     *     <отсутсвуют>
     *
     * Ответ:
     *     leader {TUser},
     *     users {Array of TUser}
     */
    public function countries() {
        try {
        $data    = array();

        $page = ($page = intval($this->request->query('page'))) ? $page : 1;
        $per_page = ($per_page = intval($this->request->query('per_page'))) ? $per_page : 25;
        
        if (isset($this->request->query['leaders'])) {
            $num_of_leaders = (int)$this->request->query['leaders'];
        }
        $num_of_leaders = (isset($num_of_leaders) and $num_of_leaders > 0) ? $num_of_leaders : 1;
        if ($num_of_leaders > 1){
            $top_joins = array();
            $top_joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array('UserRating.user_id = User.id')
            );
        }

        $sub_db = $this->User->getDataSource();
        $sub_joins = array();
        $sub_joins[] = array(
            'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
            'alias'      => 'UserRating_sub',
            'type'       => 'LEFT',
            'conditions' => array('UserRating_sub.user_id = User_sub.id')
        );
        $sub = $sub_db->buildStatement(
            array(
                'fields'     => array('User_sub.id'),
                'table'      => $sub_db->fullTableName($this->User),
                'alias'      => 'User_sub',
                'limit'      => 1,
                'offset'     => null,
                'joins'      => $sub_joins,
                'conditions' => array(
                    'User_sub.country_id = Country.id',
                    'User_sub.is_ready'           => 1,
                    'User_sub.is_in_hall_of_fame' => 0
                ),
                'order'      => array('IFNULL(UserRating_sub.positive_votes - UserRating_sub.negative_votes, 0) DESC',
                                      'User_sub.name'),
                'group'      => null
            ),
            $this->User
        );

        $joins   = array();
        $joins[] = array(
            'table'      => $this->User->getDataSource()->fullTableName($this->User),
            'alias'      => 'User',
            'type'       => 'INNER',
            'conditions' => array("User.id = ({$sub})",
                                  'User.country_id = Country.id')
        );
        $joins[] = array(
            'table'      => $this->City->getDataSource()->fullTableName($this->City),
            'alias'      => 'City',
            'type'       => 'LEFT',
            'conditions' => array('City.id = User.city_id')
        );
        $joins[] = array(
            'table'      => $this->Credo->getDataSource()->fullTableName($this->Credo),
            'alias'      => 'Credo',
            'type'       => 'LEFT',
            'conditions' => array('Credo.id = User.credo_id')
        );
        $joins[] = array(
            'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array("UserRating.user_id = User.id")
        );

        $conditions = array(
            'User.is_ready'           => 1,
            'User.is_in_hall_of_fame' => 0,
        );

        $users_count = $this->Country->find('count', array('conditions' => $conditions,
                                                           'joins'      => $joins));

        // Если не первая страница рейтинга, то лидера придётся выбирать отдельно
        $leader = array();
        if ($page != 1) {
            $leader = $this->Country->find('first', array('conditions' => $conditions,
                                                          'joins' => $joins,
                                                          'fields' => array(
                                                              'User.id',
                                                          ),
                                                          'order' => array(
                                                              'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                                              'User.name'
                                                          )));

            if (!empty($leader)) {
                $leader_id = $leader['User']['id'];
                $leader = $this->User->getUser($leader_id, true, true, $this->currentUserId);
            }
        }

        if (($page - 1) * $per_page >= $users_count && $users_count > 0) {
            $data['leader'] = $leader;
            $data['users']  = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $users_count
            );
        } elseif ($users_count > 0) {
            $leaders = $this->Country->find('all', array('conditions' => $conditions,
                                                         'joins' => $joins,
                                                         'fields' => array(
                                                             'User.id',
                                                             'User.fb_id',
                                                             'User.vk_id',
                                                             'User.name',
                                                             'User.is_ready',
                                                             'User.is_new',
                                                             'User.email',
                                                             'User.status',
                                                             'Credo.text',
                                                             'Country.id',
                                                             'Country.name',
                                                             'City.id',
                                                             'City.name',
                                                             'City.region_name',
                                                             'IFNULL(UserRating.positive_votes, 0) as likes',
                                                             'IFNULL(UserRating.negative_votes, 0) as dislikes'
                                                         ),
                                                         'order' => array(
                                                             'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                                             'User.name'
                                                         ),
                                                         'limit'  => $per_page,
                                                         'offset' => ($page - 1) * $per_page));

            $data['leader'] = $leader;
            $data['users']  = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $users_count
            );

            // Временное решение для полной информации по пользователям
            $joins   = array();
            $joins[] = array(
                'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                'alias'      => 'UserRating',
                'type'       => 'LEFT',
                'conditions' => array(
                    'UserRating.user_id = User.id'
                )
            );

            foreach ($leaders as $leader) {
                // Временное решение для полной информации по пользователям
                $user_rating_sum = $leader[0]['likes'] - $leader[0]['dislikes'];

                // Временное решение для полной информации по пользователям
                $world_rating = $this->User->find(
                    'first',
                    array(
                         'conditions' => array(
                             'User.is_ready'           => 1,
                             'User.is_in_hall_of_fame' => 0,
                             'OR'                      => array(
                                 'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                 'AND'                                                                           => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                     'User.name <'                                                                 => $leader['User']['name']
                                 )
                             )
                         ),
                         'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                         'joins'      => $joins
                    ));
                // Временное решение для полной информации по пользователям
                if (!empty($leader['Country']['id'])) {
                    $country_rating = $this->User->find(
                        'first',
                        array(
                             'conditions' => array(
                                 'User.is_ready'           => 1,
                                 'User.is_in_hall_of_fame' => 0,
                                 'User.country_id'         => $leader['Country']['id'],
                                 'OR'                      => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                     'AND'                                                                           => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                         'User.name <'                                                                 => $leader['User']['name']
                                     )
                                 )
                             ),
                             'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                             'joins'      => $joins
                        ));
                } else {
                    $country_rating = array(array('rating' => null));
                }
                // Временное решение для полной информации по пользователям
                if (!empty($leader['City']['id'])) {
                    $city_rating = $this->User->find(
                        'first',
                        array(
                             'conditions' => array(
                                 'User.is_ready'           => 1,
                                 'User.is_in_hall_of_fame' => 0,
                                 'User.city_id'            => $leader['City']['id'],
                                 'OR'                      => array(
                                     'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user_rating_sum,
                                     'AND'                                                                           => array(
                                         'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user_rating_sum,
                                         'User.name <'                                                                 => $leader['User']['name']
                                     )
                                 )
                             ),
                             'fields'     => array('COUNT(DISTINCT User.id) + 1 as rating'),
                             'joins'      => $joins
                        ));
                } else {
                    $city_rating = array(array('rating' => null));
                }

                $ans = array();

                $ans['id']       = $leader['User']['id'];
                $ans['fb_id']    = $leader['User']['fb_id'];
                $ans['vk_id']    = $leader['User']['vk_id'];
                $ans['name']     = $leader['User']['name'];
                $ans['is_ready'] = $leader['User']['is_ready'];
                $ans['is_new']   = $leader['User']['is_new'];
                $ans['email']    = $leader['User']['email'];
                $ans['status']   = $leader['User']['status'];
                $ans['credo']    = $leader['Credo']['text'];

                $ans['country']         = array();
                $ans['country']['id']   = $leader['Country']['id'];
                $ans['country']['name'] = $leader['Country']['name'];

                $ans['city']                  = array();
                $ans['city']['id']            = $leader['City']['id'];
                $ans['city']['name']          = $leader['City']['name'];
                $ans['city']['region_name']   = $leader['City']['region_name'];

                // Временное решение для полной информации по пользователям
                $ans['likes']    = intval($leader[0]['likes']);
                $ans['dislikes'] = intval($leader[0]['dislikes']);

                // Временное решение для полной информации по пользователям
                $ans['world_position']   = intval($world_rating[0]['rating']);
                $ans['country_position'] = intval($country_rating[0]['rating']);
                $ans['city_position']    = intval($city_rating[0]['rating']);

                $ans['actions'] = $this->User->getUserActions($this->currentUserId, $ans['id']);

                if ($page == 1 and empty($data['leader'])) {
                    $data['leader'] = $ans;
                }

                if ($num_of_leaders > 1) {
                    $top_users = $this->User->find('all',
                                                   array(
                                                        'conditions' => array(
                                                            'User.is_ready'           => 1,
                                                            'User.is_in_hall_of_fame' => 0,
                                                            'User.country_id'         => $leader['Country']['id']
                                                        ),
                                                        'joins'      => $top_joins,
                                                        'fields'     => array('User.id'),
                                                        'order'      => array(
                                                            'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                                                            'User.name'
                                                        ),
                                                        'offset'     => 1,
                                                        'limit'      => $num_of_leaders - 1
                                                   ));
                    if (!empty($top_users)) {
                        $top_users_ids = Set::extract('/User/id', $top_users);
                        $top_users     = $this->User->getUsers($top_users_ids, true, array('likes - dislikes DESC'), false, $this->currentUserId);
                        $ans           = array_merge(array($ans), $top_users);
                    }
                }

                $data['users'][] = $ans;
            }
        } else {
            $data['leader'] = $leader;
            $data['users']  = array();
            $data['pages']  = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $users_count
            );
        }

        	$this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }
    }
}