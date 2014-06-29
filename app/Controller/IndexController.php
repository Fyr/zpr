<?php
class IndexController extends AppController {
	public $uses = array('User');
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->json_render = false;
	}
	
    public function index() {
    	$this->set('user_data', $this->User->getUser($this->currentUserId, false));
    }
}
