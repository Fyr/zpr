<?php
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
        try {
        	$q = $this->request->query('q');

        	$q = mb_convert_case(addslashes($q), MB_CASE_LOWER, "UTF-8");
        	
        	$page = ($page = intval($this->request->query('page'))) ? $page : 1;
        	$per_page = ($per_page = intval($this->request->query('per_page'))) ? $per_page : 25;
        	
                $conditions = ($q) ? array('LOWER(name) LIKE "%'.$q.'%"') : array();

        	$aRowset = $this->Country->find('all', array(
        		'fields' => array('id', 'name', 'POSITION("'.$q.'" IN name) AS pos'),
        		'conditions' => $conditions,
        		'order' => array('pos', 'LENGTH(name)', 'name'),
        		'limit' => $per_page,
        		'page' => $page
        	));
        	
        	$data = array();
        	foreach($aRowset as $row) {
        		$data['countries'][] = array('id' => $row['Country']['id'], 'name' => $row['Country']['name']);
        	}
        	
        	$total = 0;
        	if ($aRowset) {
        		$total = $this->Country->find('count', compact('conditions'));
        	}
        	$data['pages'] = compact('per_page', 'page', 'total');
        	
        	$this->setResponse($data);
        } catch (Exception $e) {
        	$this->setError($e->getMessage());
        }
    }
}