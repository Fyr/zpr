<?php
App::uses('AppController', 'Controller');
App::uses('BalanceHistory', 'Model');
App::uses('City', 'Model');
App::uses('Country', 'Model');
App::uses('Credo', 'Model');
App::uses('Leader', 'Model');
App::uses('User', 'Model');
App::uses('Referal', 'Model');
class TestsController extends AppController {
    public $uses = array(
	'Auth',
	'Cities',
	'ChatMessage',
	'ChatMessageRead',
	'HallOfFame',
	'Post',
	'ShellLog',
        'BalanceHistory',
        'City',
        'Country',
        'Credo',
        'Leader',
        'User',
	'UserComment',
	'UserDefaults',
	'UserLock',
	'UserMessage',
	'UserRating',
	'UserVote',
	'Zen',
	'Vk',
	'Referal'
    );

    public function index() {
	foreach ($this->uses as $model) {
	    $this->$model->setDataSource('test');
	}
        $this->saveBonusLeaderTest();
	$this->saveBonusDailyTest();
	$this->saveBonusReferalTest();
    }

    /**
     * Тестирование фунционала ежедневного начисления ИВ лидерам
     */
    private function saveBonusLeaderTest() {
	echo '
Тест №1
Проверка алгоритма начисления ИВ лидерам регионов и кредо';
        // Перед тестом очистим таблицы истории, лидеров и обнулим баланс пользователей
	$this->BalanceHistory->query('TRUNCATE TABLE balance_history');
	$this->Leader->query('TRUNCATE TABLE leaders');
	$this->User->updateAll(array('balance' => 0));
	    
	// По тестовым данным пользователь является лидером города и лидером кредо
	$user_id = 1704;
	$region_type = 6;
	$credo = 7;
	
	// При первом запуске начислим лидеру бонус независимо от того сколько он потратил за прошлый день
	$operBonus = $this->BalanceHistory->getOperationBonus();
	$operType = $this->BalanceHistory->getOperationOptions();
	$this->BalanceHistory->addOperation($region_type, $operBonus[$region_type], $user_id, $operType[$region_type]);
	$this->Leader->saveLeader(array('user_id' => $user_id, 'type' => $region_type));
	if ($credo) {
	    $this->BalanceHistory->addOperation($credo, $operBonus[$credo], $user_id, $operType[$credo]);
	    $this->Leader->saveLeader(array('user_id' => $user_id, 'type' => $credo));
	}
	// Добавим пользователю 1000 ИВ
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, 1000, $user_id, '++');
	
	// *** Первый тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -43, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '

    Тест №1.1
    Пользователь является лидером города и лидером кредо, потратил за прошлый день 43 ИВ.
    Нужно начислить как лидеру города 43 ИВ, как лидеру кредо ничего не начисляем.
    Результат: ';
	$this->assertEqual(43, $sum[0]['points']);
	$this->restoreBalance(43, $user_id);

	// *** Второй тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -127, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '

    Тест №1.2
    Пользователь является лидером города и лидером кредо, потратил за прошлый день 127 ИВ.
    Нужно начислить как лидеру города 50 ИВ, Как лидер кредо должен получить 77 ИВ.
    Результат: ';
	$this->assertArray(array(50, 77), array($sum[0]['points'], $sum[1]['points']));
	$this->restoreBalance(127, $user_id);
	
	// *** Третий тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -180, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '
	    
    Тест №1.3
    Пользователь является лидером города и лидером кредо, потратил за прошлый день 180 ИВ.
    Нужно начислить как лидеру города 50 ИВ, Как лидер кредо должен получить 100 ИВ.
    Результат: ';
	$this->assertArray(array(50, 100), array($sum[0]['points'], $sum[1]['points']));
	$this->restoreBalance(150, $user_id);
	
	// На следующих тестах пользователь не является лидером кредо
	$credo = false;
	
	// *** Четвертый тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -23, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '
	    
    Тест №1.4
    Пользователь является только лидером города, потратил за прошлый день 23 ИВ.
    Нужно начислить как лидеру города 23 ИВ.
    Результат: ';
	$this->assertEqual(23, $sum[0]['points']);
	$this->restoreBalance(23, $user_id);
	
	// *** Пятый тест ***
	
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -68, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	echo '
	    
    Тест №1.5
    Пользователь является только лидером города, потратил за прошлый день 68 ИВ.
    Нужно начислить как лидеру города 50 ИВ.
    Результат: ';
	$this->assertEqual(50, $sum[0]['points']);
	$this->restoreBalance(50, $user_id);
    }
    
    /**
     * Тестирование фунционала начисления ИВ за серию ежедневного прибывания на сайте
     */
    private function saveBonusDailyTest() {
	echo '
	    
Тест №2
Проверка алгоритма начисления ИВ за серию ежедневных посещений';

	// *** Первый тест ***
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 2');
	echo '
	    
    Тест №2.1
    Пользователь заходил на сайт каждый день в течении 10 дней.
    Общая сумма начисленных ИВ должна составить 116
    Результат: ';
	for ($i = 1; $i <= 10; $i++) {
	    $this->BalanceHistory->calcEveryDayBonus(1704);
	    $this->BalanceHistory->query('UPDATE balance_history SET created = created - INTERVAL 24 HOUR');
	    $this->User->query('UPDATE users SET date_auth = date_auth - INTERVAL 1 DAY WHERE id = 1704');
	}
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) as sum'),
	    'conditions' => array('oper_type' => 2)
	));
	$this->assertEqual(116, $result[0][0]['sum']);
	
	// *** Второй тест ***
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 2');
	echo '
	    
    Тест №2.2
    Пользователь был на сайте каждый день в течении 3 дней. Затем день не заходил, после чего составил серию заходов из 5 денй.
    Общая сумма начисленных ИВ должна составить 57
    Результат: ';
	for ($i = 1; $i <= 9; $i++) {
	    if ($i != 4) {
		$this->BalanceHistory->calcEveryDayBonus(1704);
		$this->BalanceHistory->query('UPDATE balance_history SET created = created - INTERVAL 24 HOUR');
		if ($i == 5) {
		    $this->BalanceHistory->save(array(
			'id' => $this->BalanceHistory->getLastInsertID(),
			'points' => 3
		    ));
		}
		$this->User->query('UPDATE users SET date_auth = date_auth - INTERVAL 1 DAY WHERE id = 1704');
	    } else {
		$this->BalanceHistory->query('UPDATE balance_history SET created = created - INTERVAL 24 HOUR');
		$this->User->query('UPDATE users SET date_auth = NOW() WHERE id = 1704');
	    }
	}
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) as sum'),
	    'conditions' => array('oper_type' => 2)
	));
	$this->assertEqual(57, $result[0][0]['sum']);
    }
    
    /**
     * Тестирование функционала начисления ИВ за рефералов
     */
    private function saveBonusReferalTest() {
	// Создадим 50 случайных vk_id
	$vk_ids = array();
	$i = 0;
	while($i < 50){
	    $vk_id = mt_rand(10000000, 99999999);
	    if(!in_array($vk_id, $vk_ids)){
		$vk_ids[$i] = $vk_id;
		$i++;
	    }
	}
	
	echo '

Тест №3
Проверка алгоритма начисления ИВ за приглашенных пользователей';
	
	// *** Первый тест ***
	echo '
	    
    Тест №3.1
    У пользователя в друзьях в ВК есть есть 50 друзей. Все они по очереди становятся рефералами текущего пользователя.
    В результате должно получиться 50 записей в таблице рефералов и +85 ИВ
    Результат: ';
	$this->generateDataForReferal(1704, $vk_ids, 50);
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) AS sum'),
	    'conditions' => array('user_id' => 1704, 'oper_type' => 8)
	));
	$this->assertArray(array(85, 50), array($result[0][0]['sum'], $this->Referal->find('count')));
	
	// *** Второй тест ***
	echo '
	    
    Тест №3.2
    У пользователя в друзьях в ВК есть 50 друзей. Из них только 27 стали рефералами.
    В результате должно получиться 27 записей в таблице рефералов и +10 ИВ
    Результат: ';
	$this->generateDataForReferal(1704, $vk_ids, 27);
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) AS sum'),
	    'conditions' => array('user_id' => 1704, 'oper_type' => BalanceHistory::BH_REFERAL)
	));
	$this->assertArray(array(10, 27), array($result[0][0]['sum'], $this->Referal->find('count')));
	
	// *** Третий тест ***
	echo '
	    
    Тест №3.3
    У пользователя в друзьях в ВК есть 50 друзей. Из них только 35 стали рефералами.
    В результате должно получиться 35 записи в таблице рефералов и +35 ИВ
    Результат: ';
	$this->generateDataForReferal(1704, $vk_ids, 35);
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) AS sum'),
	    'conditions' => array('user_id' => 1704, 'oper_type' => BalanceHistory::BH_REFERAL)
	));
	$this->assertArray(array(35, 35), array($result[0][0]['sum'], $this->Referal->find('count')));
    }
    
    /**
     * Генерация тестовых данных для тестирования функционала начисления ИВ за рефералов
     */
    private function generateDataForReferal($user_id, $vk_ids, $limit = 50) {
	// Очистим таблицу рефералов
	$this->Referal->query('TRUNCATE TABLE user_referals');
	// Очистим поле vk_id в таблице пользователей
	$this->User->query('UPDATE users SET vk_id = NULL');
	// Очистим историю начислений
	$this->BalanceHistory->query('TRUNCATE TABLE balance_history');
	// Выберем из всех случайных юзеров
	$randUsers = $this->User->find('all', array('order' => 'RAND()', 'limit' => $limit, 'conditions' => array('id !='.$user_id)));
	// Пропишем для выбранных пользователей генерированный vk_id
	foreach ($randUsers as $key => $user) {
	    $this->User->save(array('id' => $user['User']['id'], 'vk_id' => $vk_ids[$key]));
	    //добавим пользователя в таблицу рефералов
	    $this->Referal->create();
	    $this->Referal->save(array('user_id' => $user_id, 'referal_id' => $user['User']['id']));
	    // реферал успешно зарегался, запускаем механизм подсчета бонусов
	    $this->BalanceHistory->saveReferalBonus($vk_ids, $this->Referal->getAllReferal($user_id), $user_id);
	}
    }

    private function restoreBalance($summ, $user_id) {
	// Удалим из БД расходную операцию и вернем баланс пользователя в исходное состояние
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, $summ, $user_id, '+');
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = '.BalanceHistory::BH_SYS_CHANGE);
    }
    
    private function assertEqual($expected, $result) {
	//echo "Expected: $expected, Result: $result";
	return $this->assertTrue($expected == $result);
    }
    
    private function assertArray($expected, $result) {
	$diff = array_diff_assoc($expected, $result);
	return $this->assertTrue($diff ? false : true);
    }
    
    private function assertTrue($result) {
	echo ($result) ? 'OK' : 'FAILED';
	// echo '<br>';
    }
}