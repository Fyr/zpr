<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 23.01.13
 * Time: 23:38
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class CitiesController
 * @property Country Country
 * @property City City
 */
class CitiesController extends AppController {
    public $uses = array('Country',
                         'City');
    public $components = array('RequestHandler');

    /**
     * Поиск города по подстроке
     *
     * GET /cities/
     *
     * Параметры:
     *     q {String} Строка запроса
     *     per_page {Number} Кол-во результатов на страницу
     *     page {Number} Номер страницы начиная с первой
     *     country {String} код страны, в которой искать города [опциональный]
     *
     * Ответ:
     *     cities {Array of TCity},
     *     pages {TPages}
     */
    public function index() {
        $success = true;
        $data    = array();

        if (!isset($this->request->query['q'])) {
            $this->response->statusCode(400);
            $success = false;
            $data = 'no search string specified';
        }

        if ($success) {
            $search_string = $this->request->query['q'];

            if (isset($this->request->query['per_page'])) {
                $per_page = (int)$this->request->query['per_page'];
            }
            $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

            if (isset($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
            }
            $page = (isset($page) and $page > 0) ? $page : 1;

            $conditions = array('City.is_deleted' => 0);
            $order = array();
            $fields = array(
                'City.id',
                'City.name',
                'City.latitude',
                'City.longitude',
                'City.region_name',
                'Country.name',
            );
            if ($search_string) {
//                $conditions['OR'] = array();

                $search_array = preg_split('/[\s,.;:]+/i', $search_string);
                if (empty($search_array)) {
                    $search_array = array($search_string);
                }

                foreach ($search_array as $search_word_key => $search_word) {
//                    $conditions['OR'][] = array('LOWER(TRIM(City.name)) LIKE LOWER(?)' => array("%{$search_word}%"));
//                    $conditions['OR'][] = array('LOWER(TRIM(City.alternative_names)) LIKE LOWER(?)' => array("%{$search_word}%"));
                    $cond = array('OR' => array());
                    $cond['OR'][] = array('LOWER(TRIM(City.name)) LIKE LOWER(?)' => array("%{$search_word}%"));
                    $cond['OR'][] = array('LOWER(TRIM(City.alternative_names)) LIKE LOWER(?)' => array("%{$search_word}%"));
                    // $cond['OR'][] = array('LOWER(TRIM(City.region_name)) LIKE LOWER(?)' => array("%{$search_word}%"));
                    // $cond['OR'][] = array('LOWER(TRIM(Country.name)) LIKE LOWER(?)' => array("%{$search_word}%"));
                    $conditions[] = $cond;
                    $fields[] = "CASE WHEN LOWER(TRIM(City.name)) = LOWER('". addcslashes($search_word, '"\\') ."') THEN 1 ELSE 2 END AS order0_{$search_word_key}";
                    $fields[] = "CASE WHEN LOWER(TRIM(City.name)) LIKE LOWER('". addcslashes($search_word, '"\\') ."%') THEN 1 ELSE 2 END AS order1_{$search_word_key}";
                    $fields[] = "CASE WHEN LOWER(TRIM(City.alternative_names)) LIKE LOWER('". addcslashes($search_word, '"\\') ."%') THEN 1 ELSE 2 END AS order2_{$search_word_key}";
                    $order[] = 'order0_' . $search_word_key;
                    $order[] = 'order1_' . $search_word_key;
                    $order[] = 'order2_' . $search_word_key;
                }
            }

            // TODO: ускорить запрос

            $country = null;
            if (isset($this->request->query['country']) and $this->request->query['country']) {
                $country = preg_replace('/^\s+|\s+$/iu', '', $this->request->query['country']);
            }

            if (!empty($country)) {
                $conditions['Country.code'] = $country;
            }
            $order[] = 'City.name';
            $order[] = 'Country.name';

            $joins = array();
            $joins[] = array(
                'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                'alias'      => 'Country',
                'type'       => 'LEFT',
                'conditions' => array('Country.id = City.country_id',
                                      'Country.is_deleted' => 0)
            );
            $city_count = $this->City->find('count', array('conditions' => $conditions,
                                                           'joins'      => $joins));

            if (($page - 1) * $per_page >= $city_count && $city_count > 0) {
                $data['cities'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $city_count);
            } elseif ($city_count > 0) {
                $data['cities'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $city_count);

                $cities = $this->City->find('all', array('conditions' => $conditions,
                                                         'joins'      => $joins,
                                                         'fields'     => $fields,
                                                         'order'      => $order,
                                                         'limit'      => $per_page,
                                                         'offset'     => ($page - 1) * $per_page));

                foreach ($cities as $city) {
                    $ans = array();

                    $ans['id']           = $city['City']['id'];
                    $ans['name']         = $city['City']['name'];
                    $ans['region_name']  = $city['City']['region_name'];
                    $ans['country_name'] = $city['Country']['name'];

                    $ans['position'] = array();
                    $ans['position']['x'] = $city['City']['longitude'];
                    $ans['position']['y'] = $city['City']['latitude'];

                    $data['cities'][] = $ans;
                }
            } else {
                $data['cities'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $city_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'),
                        'data'   => $data);

        $this->set(compact('answer'));
    }
}