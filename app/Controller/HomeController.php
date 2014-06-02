<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.05.12
 * Time: 1:51
 * To change this template use File | Settings | File Templates.
 */
class HomeController extends AppController {
	public $layout = 'html';
    public $helper = array('Html', 'Form');
    public $uses = array('User', 'UserRating', 'UserVote', 'UserDefaults', 'Credo', 'Auth', 'BalanceHistory');

    public function index() {
        $this->json_render = false;
        
        if ($this->request->data('UserDefaults')) {
        	$this->UserDefaults->save($this->request->data);
        	return $this->redirect('/');
        }
        $this->set('title_for_layout', 'Список сервисов');
        $this->request->data('UserDefaults', $this->UserDefaults->getList());
        $this->set('credoOptions', $this->Credo->find('list', array('fields' => array('id', 'text'))));
        $this->set('balanceOperOptions', $this->BalanceHistory->getOperationOptions());
    }
    
    public function balanceModify() {
    	$this->autoRender = false;
    	$data = $this->request->data('BalanceHistory');
    	if ($data) {
    		$user_id = intval($this->request->data('BalanceHistory.user_id'));
    		$oper_type = intval($this->request->data('BalanceHistory.oper_type'));
	        $points = intval($this->request->data('BalanceHistory.points'));
	        $comment = $this->request->data('BalanceHistory.comment');
	        $balance = $this->BalanceHistory->addOperation($oper_type, $points, $user_id, $comment);
	        $this->redirect('index');
    	}
    }
    
    public function setBalance() {
    	$this->autoRender = false;
    	$data = $this->request->data('Balance');
    	if ($data) {
    		$user_id = intval($this->request->data('Balance.user_id'));
	        $points = intval($this->request->data('Balance.points'));
	        $balance = $this->BalanceHistory->setBalance($user_id, $points);
	        $this->redirect('index');
    	}
    }
}
