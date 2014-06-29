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
        $data = $this->getCountriesLeader(true);
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
     * @param bollean $first = если true вернем только первую строку
     * @return array
     */
    public function getCountriesLeader($first = false) {
        return $this->getQueryLeader('country_id', $first);
    }
    
    /**
     * Получаем лидеров по каждому городу
     * @param array $countries - страны для поиска лидеров по городам
     * @return array
     */
    public function getCitiesLeader() {
        return $this->getQueryLeader('city_id');
    }
    
    /**
     * Получаем лидеров по городам или странам
     * @param str $column - country_id или city_id
     * @param boolean $first - вернуть только первую строку
     * @return array
     */
    private function getQueryLeader($column = 'country_id', $first = false) {
        $first = $first ? ' LIMIT 1' : '';
        $data = $this->query(
            'SELECT User.*, (User.positive_votes - User.negative_votes) AS points
            FROM (
                SELECT ur.*
                FROM user_ratings AS ur
                WHERE (ur.positive_votes - ur.negative_votes) > 0
                ORDER BY ur.positive_votes - ur.negative_votes DESC
            ) AS User
            GROUP BY User.'.$column.'
            ORDER BY points DESC'.$first
        );
        return $data;
    }
}
