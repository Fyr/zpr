<?php
class CitiesController extends AppController {
    public $uses = array('Country', 'City');
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
    	$searchField = 'name'; // по умолчанию ищем по русским названиям
        try {
        	$q = $this->request->query('q');
        	if (!$q) {
        		throw new Exception('no search string specified');
        	}
        	$q = mb_convert_case(addslashes($q), MB_CASE_LOWER, "UTF-8");
        	
        	$page = ($page = intval($this->request->query('page'))) ? $page : 1;
        	$per_page = ($per_page = intval($this->request->query('per_page'))) ? $per_page : 25;
        	
        	$conditions = array('LOWER(City.name) LIKE "%'.$q.'%"');
        	if ($country_id = $this->request->query('country')) {
        		$conditions['City.country_id'] = $country_id;
        	}
        	
        	$this->City->bindModel(array('belongsTo' => array('Country')), false);
        	$aRowset = $this->City->find('all', array(
        		'fields' => array('City.id', 'City.name', 'region_name', 'Country.id', 'Country.name', 'POSITION("'.$q.'" IN City.name) AS pos'),
        		'conditions' => $conditions,
        		'order' => array('pos', 'LENGTH(City.name)', 'City.name'),
        		'limit' => $per_page,
        		'page' => $page
        	));
        	
        	$data = array();
        	foreach($aRowset as $row) {
        		$data['cities'][] = array(
        			'id' => $row['City']['id'], 
        			'name' => $row['City']['name'], 
        			'region_name' => $row['City']['region_name'], 
        			'country_id' => $row['Country']['id'],
        			'country_name' => $row['Country']['name']
        		);
        	}
        	
        	$total = 0;
        	if ($aRowset) {
        		$total = $this->City->find('count', compact('conditions'));
        	}
        	$data['pages'] = compact('per_page', 'page', 'total');
        	$this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }
    }
}