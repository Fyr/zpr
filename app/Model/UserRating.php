<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.05.12
 * Time: 2:26
 * To change this template use File | Settings | File Templates.
 */
App::uses('AppModel', 'Model');
class UserRating extends AppModel {
    
    /**
     * Получаем лидера Мира
     * @return array
     */
    public function getWorldLeader() {
        $data = $this->find('first', array(
            'fields' => array('user_id, (positive_votes - negative_votes) AS credits'),
            'order' => array('credits' => 'DESC')
        ));
        return $data;
    }
    
    /**
     * Получаем лидеров по кредо
     * @return array
     */
    public function getCredoLeader() {
        $data = $this->query(
            'SELECT User.*, points
            FROM (
                SELECT a.id, a.credo_id, (ur.positive_votes - ur.negative_votes) AS points
                FROM users AS a
                LEFT JOIN user_ratings AS ur ON (a.id = ur.user_id)
                ORDER BY points DESC
            ) AS User
            WHERE User.credo_id is not NULL
            GROUP BY User.credo_id
            ORDER BY User.credo_id DESC'
        );
        return $data;
    }
    
    /**
     * Получаем лидеров по каждой стране
     * @param array $exception - массив ID пользователей для исключения
     * @return array
     */
    public function getCountriesLeader($exception = array()) {
        return $this->getQueryLeader($exception, 'country_id');
    }
    
    /**
     * Получаем лидеров по каждому городу
     * @param array $exception - массив ID пользователей для исключения
     * @return array
     */
    public function getCitiesLeader($exception = array()) {
        return $this->getQueryLeader($exception, 'city_id');
    }
    
    /**
     * Получаем лидеров по городам или странам
     * @param array $exception - массив ID пользователей для исключения
     * @param str $column - country_id или city_id
     * @return array
     */
    private function getQueryLeader($exception = array(), $column = 'country_id') {
        $data = $this->query(
            'SELECT User.user_id, (User.positive_votes - User.negative_votes) AS points
            FROM (
                SELECT ur.*
                FROM user_ratings AS ur
                ORDER BY ur.positive_votes - ur.negative_votes DESC
            ) AS User
            WHERE User.user_id NOT IN ('.implode(',', $exception).') AND User.'.$column.' >= 0
            GROUP BY User.'.$column.'
            ORDER BY points DESC'
        );
        return $data;
    }
}
