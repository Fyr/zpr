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
    public $tests = array();
    
    public function index() {
	$this->json_render = false;
	$this->layout = 'html';
	
	foreach ($this->uses as $model) {
	    $this->$model->setDataSource('test');
	}
        $this->saveBonusLeaderTest();
	$this->saveBonusDailyTest();
	$this->saveBonusReferalTest();
	
	$this->set('tests', $this->tests);
	$this->set('title_for_layout', 'Тесты');
    }

    /**
     * Тестирование фунционала ежедневного начисления ИВ лидерам
     */
    private function saveBonusLeaderTest() {
	$testDescription = 'Проверка алгоритма начисления ИВ лидерам регионов и кредо';
	$this->tests = Hash::merge(array(0 => array('testDescription' => $testDescription)), $this->tests);

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
	$testIteamDescr = 'Пользователь является лидером города и лидером кредо, потратил за прошлый день 43 ИВ. Нужно начислить как лидеру города 43 ИВ, как лидеру кредо ничего не начисляем.';
	$this->dataTests(0, 0, $testIteamDescr, 43, $sum[0]['points'], $this->assertEqual(43, $sum[0]['points']));
	$this->restoreBalance(43, $user_id);
	
	// *** Второй тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -127, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	$testIteamDescr = 'Пользователь является лидером города и лидером кредо, потратил за прошлый день 127 ИВ. Нужно начислить как лидеру города 50 ИВ, Как лидер кредо должен получить 77 ИВ.';
	$this->dataTests(0, 1, $testIteamDescr, '50 и 77', $sum[0]['points'].' и '.$sum[1]['points'], $this->assertArray(array(50, 77), array($sum[0]['points'], $sum[1]['points'])));
	$this->restoreBalance(127, $user_id);
	
	// *** Третий тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -180, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	$testIteamDescr = 'Пользователь является лидером города и лидером кредо, потратил за прошлый день 180 ИВ. Нужно начислить как лидеру города 50 ИВ, Как лидер кредо должен получить 100 ИВ.';
	$this->dataTests(0, 2, $testIteamDescr, '50 и 100', $sum[0]['points'].' и '.$sum[1]['points'], $this->assertArray(array(50, 100), array($sum[0]['points'], $sum[1]['points'])));
	$this->restoreBalance(150, $user_id);
	
	// На следующих тестах пользователь не является лидером кредо
	$credo = false;
	
	// *** Четвертый тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -23, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	$testIteamDescr = 'Пользователь является только лидером города, потратил за прошлый день 23 ИВ. Нужно начислить как лидеру города 23 ИВ.';
	$this->dataTests(0, 3, $testIteamDescr, 23, $sum[0]['points'], $this->assertEqual(23, $sum[0]['points']));
	$this->restoreBalance(23, $user_id);
	
	// *** Пятый тест ***
	$this->BalanceHistory->addOperation(BalanceHistory::BH_SYS_CHANGE, -68, $user_id, 'списание ИВ');
	$sum = $this->BalanceHistory->getPointsAdd($user_id, $region_type, $credo);
	$testIteamDescr = 'Пользователь является только лидером города, потратил за прошлый день 68 ИВ. Нужно начислить как лидеру города 50 ИВ.';
	$this->dataTests(0, 4, $testIteamDescr, 50, $sum[0]['points'], $this->assertEqual(50, $sum[0]['points']));
	$this->restoreBalance(50, $user_id);
    }
    
    /**
     * Тестирование фунционала начисления ИВ за серию ежедневного прибывания на сайте
     */
    private function saveBonusDailyTest() {
	$testDescription = 'Проверка алгоритма начисления ИВ за серию ежедневных посещений';
	$this->tests = Hash::merge(array(1 => array('testDescription' => $testDescription)), $this->tests);

	// *** Первый тест ***
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 2');
	$testIteamDescr = 'Пользователь заходил на сайт каждый день в течении 10 дней. Общая сумма начисленных ИВ должна составить 116.';
	for ($i = 1; $i <= 10; $i++) {
	    $this->BalanceHistory->calcEveryDayBonus(1704);
	    $this->BalanceHistory->query('UPDATE balance_history SET created = created - INTERVAL 24 HOUR');
	    $this->User->query('UPDATE users SET date_auth = date_auth - INTERVAL 1 DAY WHERE id = 1704');
	}
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) as sum'),
	    'conditions' => array('oper_type' => 2)
	));
	$this->dataTests(1, 0, $testIteamDescr, 116, $result[0][0]['sum'], $this->assertEqual(116, $result[0][0]['sum']));
	
	// *** Второй тест ***
	$this->BalanceHistory->query('DELETE FROM balance_history WHERE oper_type = 2');
	$testIteamDescr = 'Пользователь был на сайте каждый день в течении 3 дней. Затем день не заходил, после чего составил серию заходов из 5 денй. Общая сумма начисленных ИВ должна составить 57.';
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
	$this->dataTests(1, 1, $testIteamDescr, 57, $result[0][0]['sum'], $this->assertEqual(57, $result[0][0]['sum']));
    }
    
    /**
     * Тестирование функционала начисления ИВ за рефералов
     */
    private function saveBonusReferalTest() {
	$testDescription = 'Проверка алгоритма начисления ИВ за приглашенных пользователей.';
	$this->tests = Hash::merge(array(2 => array('testDescription' => $testDescription)), $this->tests);
	
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

	// *** Первый тест ***
	$testIteamDescr = 'У пользователя в друзьях в ВК есть есть 50 друзей. Все они по очереди становятся рефералами текущего пользователя. В результате должно получиться 50 записей в таблице рефералов и +85 ИВ.';
	$this->generateDataForReferal(1704, $vk_ids, 50);
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) AS sum'),
	    'conditions' => array('user_id' => 1704, 'oper_type' => 8)
	));
	$this->dataTests(2, 0, $testIteamDescr, '', '', $this->assertArray(array(85, 50), array($result[0][0]['sum'], $this->Referal->find('count'))));
	
	// *** Второй тест ***
	$testIteamDescr = 'У пользователя в друзьях в ВК есть 50 друзей. Из них только 27 стали рефералами. В результате должно получиться 27 записей в таблице рефералов и +10 ИВ.';
	$this->generateDataForReferal(1704, $vk_ids, 27);
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) AS sum'),
	    'conditions' => array('user_id' => 1704, 'oper_type' => BalanceHistory::BH_REFERAL)
	));
	$this->dataTests(2, 1, $testIteamDescr, '', '', $this->assertArray(array(10, 27), array($result[0][0]['sum'], $this->Referal->find('count'))));
	
	// *** Третий тест ***
	$testIteamDescr = 'У пользователя в друзьях в ВК есть 50 друзей. Из них только 35 стали рефералами. В результате должно получиться 35 записи в таблице рефералов и +35 ИВ.';
	$this->generateDataForReferal(1704, $vk_ids, 35);
	$result = $this->BalanceHistory->find('all', array(
	    'fields' => array('SUM(points) AS sum'),
	    'conditions' => array('user_id' => 1704, 'oper_type' => BalanceHistory::BH_REFERAL)
	));
	$this->dataTests(2, 2, $testIteamDescr, '', '', $this->assertArray(array(35, 35), array($result[0][0]['sum'], $this->Referal->find('count'))));
	
    }
    
    private function dataTests($idTestParrent, $idTestIteam, $testIteamDescr, $expected, $result, $assert) {
	$this->tests[$idTestParrent] = Hash::merge(array('iteam' => array(
		$idTestIteam => array(
		    'test' => $testIteamDescr,
		    'expected' => $expected,
		    'result' => $result,
		    'assert' => $assert
		)
	)), $this->tests[$idTestParrent]);
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
	    $this->BalanceHistory->saveReferalBonus($vk_ids, $this->Referal->getFriends($user_id), $user_id);
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
	return ($result) ? 'OK' : 'FAILED';
	// echo '<br>';
    }
}