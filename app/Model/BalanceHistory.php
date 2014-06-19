<?php
App::uses('AppModel', 'Model');
App::uses('User', 'AppModel');
class BalanceHistory extends AppModel {
	const BH_REGISTER = 1;
        const BH_DAILY = 2;
        const BH_GROUP_VK = 3;
        const BH_LEADER_WORLD = 4;
        const BH_LEADER_COUNTRY = 5;
        const BH_LEADER_CITY = 6;
        const BH_LEADER_CREDO = 7;
	const BH_SYS_CHANGE = 100;
	
    public $useTable = 'balance_history';
    public $belongsTo = 'User';
    
    public function getOperationOptions() {
    	return array(
    		self::BH_REGISTER           => 'Изначальное начисление ИВ при регистрации',
                self::BH_DAILY              => 'Ежедневное начисление ИВ',
                self::BH_GROUP_VK           => 'Начисление ИВ за присоединение к группе в ВКонтакте',
                self::BH_LEADER_WORLD       => 'Начисление ИВ за лидерство - "Лидер Мира"',
                self::BH_LEADER_COUNTRY     => 'Начисление ИВ за лидерство - "Лидер Страны"',
                self::BH_LEADER_CITY        => 'Начисление ИВ за лидерство - "Лидер Города"',
                self::BH_LEADER_CREDO       => 'Начисление ИВ за лидерство - "Лидер Кредо"',
    		self::BH_SYS_CHANGE         => 'Изменение баланса админом'
    	);
    }
    
    public function getOperationBonus() {
    	return array(
    		self::BH_REGISTER           => 10,
                self::BH_DAILY              => array(3, 5, 8, 10, 15),
                self::BH_GROUP_VK           => 20,
                self::BH_LEADER_WORLD       => 500,
                self::BH_LEADER_COUNTRY     => 150,
                self::BH_LEADER_CITY        => 50,
                self::BH_LEADER_CREDO       => 100
    	);
    }
    
    public function getBalance($user_id) {
    	$res = $this->User->findById($user_id);
    	return ($res) ? intval($res['User']['balance']) : 0;
    }
    
    public function addOperation($oper_type, $points, $user_id, $comment = '') {
    	$balance = $this->getBalance($user_id);
    	$this->User->save(array('id' => $user_id, 'balance' => $balance + intval($points)));
        $this->create();
    	$this->save(compact('oper_type', 'points', 'user_id', 'comment'));
    	return $this->getBalance($user_id);
    }
    
    public function setBalance($user_id, $points) {
    	$this->User->save(array('id' => $user_id, 'balance' => $points));
    	return $this->getBalance($user_id);
    }
    
    /**
     * Получить сумму потраченных ИВ за последние 24 часа
     * @param int $user_id
     * @return int
     */
    public function getPointsSpent($user_id) {
        $data = $this->find('first', array(
            'fields' => array('SUM(points) AS sumOut'),
            'conditions' => array(
                'user_id' => $user_id,
                'points <' => 0,
                'BalanceHistory.created > DATE_SUB(NOW(),INTERVAL 24 HOUR)'
            )
        ));
        return $data[0]['sumOut'];
    }
    
    /**
     * Получить сумму ИВ, которую необходимо начислить пользователю если он является лидером
     * @param int $user_id
     * @param int $region_type
     * @param int or false $credo
     */
    public function getPointsAdd($user_id, $region_type, $credo) {
	$operBonus = $this->getOperationBonus();
	$leader = ClassRegistry::init('Leader');
	$accruals = array();
	$result = array();
	$addBonus = false;
	/* Получим дату последнего лидерства по региону */
	$lastDateRL = $leader->getDateLeader($user_id, $region_type);
	/* Получим дату последнего лидерства по кредо */
	$lastDateCL = $credo ? $leader->getDateLeader($user_id, $credo) : false;
	/* Проверим сколько потратил (24ч) */
	$sumOut = $this->getPointsSpent($user_id) * -1;
	/* Определим максимальную сумму начислений по региону и по кредо */
	$sumMax[0] = $operBonus[$region_type];
	$sumMax[1] = $credo ? $operBonus[$credo] : 0;
	/* Определим общую максимальную сумму для начисления */
	$totalSumMax = $sumMax[0] + $sumMax[1];
	/* Получим сумму для начислений по региону и по кредо */
	$paramCount = $credo ? 2 : 1;
	for ($i = 0; $i < $paramCount; $i++) {
	    /* Определим тип текущей операции */
	    $currType = $i ? $credo : $region_type;
	    /* Проверим был ли пользователь лидером вчера */
	    $lastDate = $i ? $lastDateCL : $lastDateRL;
	    if ($lastDate && (time() - strtotime($lastDate) < 86400)) {
		/* Подсчитаем сколько нужно начислить ИВ исходя из потраченной суммы */
		$pointsFull = $sumOut;
		/* Прировняем Points к максимальному значению по текущей операции, если points больше этого значения */
		$points = ($pointsFull > $sumMax[$i]) ? $sumMax[$i] : $pointsFull;
		if (isset($accruals[$user_id])) {
		    if (($pointsFull - $accruals[$user_id]) > $sumMax[$i]) {
			$points = $sumMax[$i];
		    } else {
			$points = $pointsFull - $accruals[$user_id];
		    }
		} else {
		    $accruals = Hash::merge($accruals, array($user_id => 0));
		}
		$accruals[$user_id] += $points;
	    } else {
		/* Если вчера он не был лидером то начисляем по максимуму для указанного типа региона (кредо) */
		$points = $sumMax[$i];
	    }
	    if ($points) $result[] = array('type' => $currType, 'points' => $points);
	}
	return $result;
    }
    
    /**
     * Начисление бонуса за серию ежедневного захода
     * @param int $countDays
     */
    public function saveEveryDayBonus($countDays, $user_id) {
        $operType  = $this->getOperationOptions();
        $operBonus = $this->getOperationBonus();
        $this->addOperation(
            BalanceHistory::BH_DAILY,
            $operBonus[BalanceHistory::BH_DAILY][$countDays],
            $user_id,
            $operType[BalanceHistory::BH_DAILY]
        );
        if (!$countDays) {
            $this->User->save(array('id' => $this->currentUserId, 'date_auth' => DboSource::expression('NOW()')));
        }
    }
    
    public function calcEveryDayBonus($user_id) {
	$dateAuth = $this->User->find('first', array(
	    'fields' => array('date_auth'),
	    'conditions' => array('id' => $user_id)
	));
	if ($dateAuth) {
	    $countDays = $this->find('count', array(
		'fields' => array('id'),
		'conditions' => array(
		    'BalanceHistory.oper_type' => BalanceHistory::BH_DAILY,
		    'BalanceHistory.user_id' => $user_id,
		    'BalanceHistory.created >=' => $dateAuth['User']['date_auth']
		)
	    ));
	}
	$countDays = ($countDays >= 5) ? 4 : $countDays;
	$this->saveEveryDayBonus($countDays, $user_id);
    }
}