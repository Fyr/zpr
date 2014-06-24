<?php

App::uses('SetLeaderBalanceController', 'AppController');
class BonusLeaderShell extends AppShell {
    public function index() {
	$this->SetLeaderBalance->index();
    }
}

