<?php
class BalanceHistory extends AppModel {
	const BH_REGISTER = 1;
	const BH_SYS_CHANGE = 100;
	
    public $useTable = 'balance_history';

    public $belongsTo = 'User';
    
    public function getOperationOptions() {
    	return array(
    		self::BH_REGISTER => 'Изначальное начисление ИВ при регистрации',
    		self::BH_SYS_CHANGE => 'Изменение баланса админом'
    	);
    }
    
    public function getBalance($user_id) {
    	$res = $this->User->findById($user_id);
    	return ($res) ? intval($res['User']['balance']) : 0;
    }
    
    public function addOperation($oper_type, $points, $user_id, $comment = '') {
    	$balance = $this->getBalance($user_id);
    	$this->User->save(array('id' => $user_id, 'balance' => $balance + intval($points)));
    	$this->save(compact('oper_type', 'points', 'user_id', 'comment'));
    	return $this->getBalance($user_id);
    }
    
    public function setBalance($user_id, $points) {
    	$this->User->save(array('id' => $user_id, 'balance' => $points));
    	return $this->getBalance($user_id);
    }
}