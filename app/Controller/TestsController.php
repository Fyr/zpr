<?php
App::uses('AppController', 'Controller');
App::uses('BalanceHistory', 'Model');
App::uses('City', 'Model');
App::uses('Country', 'Model');
App::uses('Credo', 'Model');
App::uses('Leader', 'Model');
App::uses('User', 'Model');
class TestsController extends AppController {
    public $name = 'Tests';
    public $uses = array(
        'BalanceHistory',
        'City',
        'Country',
        'Credo',
        'Leader',
        'User'
    );
    
    public function index() {
        $this->setBonusLeaderTest();
	$this->saveBonusDailyTest();
    }

    /**
     * Тестирование фунционала ежедневного начисления ИВ лидерам
     */
    public function setBonusLeaderTest() {
	echo '
Тест №1
Проверка алгоритма начисления ИВ лидерам регионов и кредо';
        /* Перед тестом очистим таблицы истории, лидеров и обнулим баланс пользователей */
	$this->BalanceHistory->query('TRUNCATE TABLE balance_history');
	$this->Leader->query('TRUNCATE TABLE leaders');
	$this->User->updateAll(array('balance' => 0));
	    
	/* По тестовым данным пользователь является лидером города и лидером кредо */
	$user_id = 1704;
	$region_type = 6;
	$credo = 7;
	
	/* При первом запуске начислим лидеру бонус независимо от того сколько он потратил за прошлый день */
	$operBonus = $this->BalanceHistory->getOperationBonus();
	$operType = $this->BalanceHistory->getOperationOptions();
	$this->BalanceHistory->addOperation($region_type, $operBonus[$region_type], $user_id, $operType[$region_type]);
	$this->Leader->saveLeader(array('user_id' => $user_id, 'type' => $region_type));
	if ($credo) {
	    $this->BalanceHistory->addOperation($credo, $operBonus[$credo], $user_id, $operType[$credo]);
	    $this->Leader->saveLeader(array('user_id' => $user_id, 'type' => $credo));
	}
	/* Добавим пользователю 1000 ИВ */
	$this->BalanceHistory->addOperation(100, 1000, $user_id, '++');
	
	/*
	 * Первый тест
	 * Пользователь потратил 43 ИВ
	 * Нужно начислить 43 ИВ как лидеру города. Как лидер кредо ничего не начисляем.
	 * 
	 */
	
	$this->BalanceHistory->addOperation(100, -43, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '

    Тест №1.1
    Пользователь является лидером города и лидером кредо, потратил за прошлый день 43 ИВ.
    Нужно начислить как лидеру города 43 ИВ, как лидеру кредо ничего не начисляем.
    Результат: ';
	echo ($sum[0]['points'] == 43) ? 'OK' : 'FAILED';
	$this->deleteOut(43, $user_id);

	/*
	 * Второй тест
	 * Пользователь потратил 127 ИВ
	 * Нужно начислить 50 ИВ как лидеру города. Как лидер кредо должен получить 77 ИВ.
	 * 
	 */
	
	$this->BalanceHistory->addOperation(100, -127, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '

    Тест №1.2
    Пользователь является лидером города и лидером кредо, потратил за прошлый день 127 ИВ.
    Нужно начислить как лидеру города 50 ИВ, Как лидер кредо должен получить 77 ИВ.
    Результат: ';
	echo ($sum[0]['points'] == 50 && $sum[1]['points'] == 77) ? 'OK' : 'FAILED';
	$this->deleteOut(127, $user_id);
	
	/*
	 * Третий тест
	 * Пользователь потратил 180 ИВ
	 * Нужно начислить 50 ИВ как лидеру города. Как лидер кредо должен получить 100 ИВ.
	 * 
	 */
	
	$this->BalanceHistory->addOperation(100, -180, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '
	    
    Тест №1.3
    Пользователь является лидером города и лидером кредо, потратил за прошлый день 180 ИВ.
    Нужно начислить как лидеру города 50 ИВ, Как лидер кредо должен получить 100 ИВ.
    Результат: ';
	echo ($sum[0]['points'] == 50 && $sum[1]['points'] == 100) ? 'OK' : 'FAILED';
	$this->deleteOut(150, $user_id);
	
	/* На следующих тестах пользователь не является лидером кредо */
	$credo = false;
	
	/*
	 * Четвертый тест
	 * Пользователь не является лидером кредо
	 * Пользователь потратил 23 ИВ
	 * Нужно начислить 23 ИВ как лидеру города.
	 * 
	 */
	
	$this->BalanceHistory->addOperation(100, -23, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '
	    
    Тест №1.4
    Пользователь является только лидером города, потратил за прошлый день 23 ИВ.
    Нужно начислить как лидеру города 23 ИВ.
    Результат: ';
	echo ($sum[0]['points'] == 23) ? 'OK' : 'FAILED';
	$this->deleteOut(23, $user_id);
	
	/*
	 * Пятый тест
	 * Пользователь не является лидером кредо
	 * Пользователь потратил 68 ИВ
	 * Нужно начислить 50 ИВ как лидеру города.
	 * 
	 */
	
	$this->BalanceHistory->addOperation(100, -68, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '
	    
    Тест №1.5
    Пользователь является только лидером города, потратил за прошлый день 68 ИВ.
    Нужно начислить как лидеру города 50 ИВ.
    Результат: ';
	echo ($sum[0]['points'] == 50) ? 'OK' : 'FAILED';
	$this->deleteOut(50, $user_id);
    }
    
    /**
     * Тестирование фунционала начисления ИВ за серию ежедневного прибывания на сайте
     */
    public function saveBonusDailyTest() {
	echo '
	    
Тест №2
Проверка алгоритма начисления ИВ за серию ежедневных посещений';
	/**
	 * Первый тест
	 * Пользователь был на сайте каждый день в течении 10 дней. Общая сумма начисленных ИВ должна составить 116
	 */
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 2');
	echo '
	    
    Тест №2.1
    Пользователь заходил на сайт каждый день в течении 10 дней.
    Общая сумма начисленных ИВ должна составить 116
    Результат: ';
	for ($i = 10; $i >= 1; $i--) {
	    $this->BalanceHistory->calcEveryDayBonus(1704);
	    $date = time() - (100000 * $i);
	    $this->BalanceHistory->save(array(
		'id' => $this->BalanceHistory->getLastInsertID(),
		'created' => date('Y-m-d H:i:s', $date)
	    ));
	    if ($i == 10) $this->User->save(array('id' => 1704, 'date_auth' => date('Y-m-d H:i:s', $date)));
	}
	// проверим
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) as sum'),
	    'conditions' => array('oper_type' => 2)
	));
	echo ($result[0][0]['sum'] == 116) ? 'OK' : 'FAILED';
	
	/**
	 * Второй тест
	 * Пользователь был на сайте каждый день в течении 3 дней. Затем день не заходил, после чего опять составил серию заходов из 5 денй.
	 * Сумма начисленных ИВ должна составить 57
	 */
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 2');
	echo '
	    
    Тест №2.2
    Пользователь был на сайте каждый день в течении 3 дней. Затем день не заходил, после чего составил серию заходов из 5 денй.
    Общая сумма начисленных ИВ должна составить 57
    Результат: ';
	for ($i = 9; $i >= 1; $i--) {
	    if ($i != 6) {
		$this->BalanceHistory->calcEveryDayBonus(1704);
		$date = time() - (100000 * $i);
		$this->BalanceHistory->save(array(
		    'id' => $this->BalanceHistory->getLastInsertID(),
		    'created' => date('Y-m-d H:i:s', $date)
		));
		if ($i == 5) {
		    $this->BalanceHistory->save(array(
			'id' => $this->BalanceHistory->getLastInsertID(),
			'points' => 3
		    ));
		}
	    }
	    if ($i == 9 || $i == 5) $this->User->save(array('id' => 1704, 'date_auth' => date('Y-m-d H:i:s', $date)));
	}
	// проверим
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) as sum'),
	    'conditions' => array('oper_type' => 2)
	));
	echo ($result[0][0]['sum'] == 57) ? 'OK' : 'FAILED';
    }


    private function deleteOut($summ, $user_id) {
	/* Удалим из БД расходную операцию и вернем баланс пользователя в исходное состояние */
	$this->BalanceHistory->addOperation(100, $summ, $user_id, '+');
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 100');
    }
}