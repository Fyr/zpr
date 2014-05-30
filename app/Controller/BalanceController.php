<?php
App::uses('AppController', 'Controller');
class BalanceController extends AppController {
    public $uses = array('BalanceHistory');
    public $components = array('RequestHandler');

    public function modify() {
        $success = true;
        $data = array();
        
        $user_id = ($this->data['user_id']) ? $this->data['user_id'] : $this->currentUserId;
        $oper_type = intval($this->request->data('oper_type'));
        $points = intval($this->request->data('points'));
        $comment = $this->request->data('comment');
        $balance = $this->BalanceHistory->addOperation($oper_type, $points, $user_id, $comment);

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => array('balance' => $balance));

        $this->set(compact('answer'));
    }
}