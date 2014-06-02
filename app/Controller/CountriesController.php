<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 24.12.12
 * Time: 00:08
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class CountriesController
 * @property Country Country
 */
class CountriesController extends AppController {
    public $uses = array('Country');
    public $components = array('RequestHandler');

    /**
     * Поиск страны по подстроке
     *
     * GET /countries/
     *
     * Параметры:
     *     q {String} Строка запроса
     *     per_page {Number} Кол-во результатов на страницу
     *     page {Number} Номер страницы начиная с первой
     *
     * Ответ:
     *     countries {Array of TCountry},
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

            $conditions = array('Country.is_deleted' => 0);
            $fields = array(
                'Country.id',
                'Country.code',
                'Country.name',
            );
            $order = array();
            if ($search_string) {
//                $conditions['OR'] = array();

                $search_array = preg_split('/[\s,.;:]+/i', $search_string);
                if (empty($search_array)) {
                    $search_array = array($search_string);
                }

                foreach ($search_array as $search_word_key => $search_word) {
//                    $conditions['OR'][] = array('LOWER(TRIM(Country.name)) LIKE LOWER(?)' => array("%{$search_word}%"));
//
//                    if (mb_strlen($search_word) <= 2) {
//                        $conditions['OR'][] = array('LOWER(Country.code) LIKE LOWER(?)' => array("%{$search_word}%"));
//                    }
                    $cond = array('OR' => array());
                    $cond['OR'][] = array('LOWER(TRIM(Country.name)) LIKE LOWER(?)' => array("%{$search_word}%"));
                    if (mb_strlen($search_word) <= 2) {
                        $cond['OR'][] = array('LOWER(Country.code) LIKE LOWER(?)' => array("%{$search_word}%"));
                    }
                    $cond['OR'][] = array('LOWER(TRIM(Country.alternative_names)) LIKE LOWER(?)' => array("%{$search_word}%"));

                    $conditions[] = $cond;
                    $fields[] = "CASE WHEN LOWER(TRIM(Country.name)) = LOWER('". addcslashes($search_word, '"\\') ."') THEN 1 ELSE 2 END AS order0_{$search_word_key}";
                    $fields[] = "CASE WHEN LOWER(TRIM(Country.name)) LIKE LOWER('". addcslashes($search_word, '"\\') ."%') THEN 1 ELSE 2 END AS order1_{$search_word_key}";
                    $fields[] = "CASE WHEN LOWER(TRIM(Country.alternative_names)) LIKE LOWER('". addcslashes($search_word, '"\\') ."%') THEN 1 ELSE 2 END AS order2_{$search_word_key}";
                    $order[] = 'order0_' . $search_word_key;
                    $order[] = 'order1_' . $search_word_key;
                    $order[] = 'order2_' . $search_word_key;
                }
            }

            $order[] = 'Country.name';

            $country_count = $this->Country->find('count', array('conditions' => $conditions));

            if (($page - 1) * $per_page >= $country_count && $country_count > 0) {
                $data['countries'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $country_count);
            } elseif ($country_count > 0) {
                $data['countries'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $country_count);

                $countries = $this->Country->find('all', array('conditions' => $conditions,
                                                               'fields'     => $fields,
                                                               'order'      => $order,
                                                               'limit'      => $per_page,
                                                               'offset'     => ($page - 1) * $per_page));

                foreach ($countries as $country) {
                    $ans = array();

                    $ans['id']   = $country['Country']['id'];
                    $ans['code'] = $country['Country']['code'];
                    $ans['name'] = $country['Country']['name'];

                    $data['countries'][] = $ans;
                }
            } else {
                $data['countries'] = array();
                $data['pages'] = array('per_page' => $per_page,
                                       'page'     => $page,
                                       'total'    => $country_count);
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'),
                        'data'   => $data);

        $this->set(compact('answer'));
    }
}