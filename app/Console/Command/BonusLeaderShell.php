<?php

App::uses('SetLeaderBalanceController', 'Controller');
class BonusLeaderShell extends AppShell {
    public function index() {
	$this->SetLeaderBalance->index();
    }
}

