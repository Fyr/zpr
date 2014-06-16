<?php
App::uses('AppModel', 'Model');
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
}