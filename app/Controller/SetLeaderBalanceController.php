<?php

/* 
 * Ежедневное начисление ИВ лидерам
 */
App::uses('BalanceHistory', 'Model');
App::uses('UserRating', 'Model');
App::uses('Leader', 'Model');
App::uses('AppController', 'Controller');
class SetLeaderBalanceController extends AppController {
    public $uses = array('UserRating', 'BalanceHistory', 'Leader');

    public function index(){
        try {
            // Получаем лидеров по городам
            $data = $this->UserRating->getCitiesLeader();
            foreach ($data as $key => $value) {
                $citiesLeaders[$data[$key]['User']['user_id']] = array(
                    'region_type' => BalanceHistory::BH_LEADER_CITY,
                    'credo' => false,
                    'user_id' => $data[$key]['User']['user_id']
                );
            }
            
            // Получаем лидеров по странам
            $data = $this->UserRating->getCountriesLeader();
            foreach ($data as $key => $value) {
                $countriesLeaders[$data[$key]['User']['user_id']] = array(
                    'region_type' => BalanceHistory::BH_LEADER_COUNTRY,
                    'credo' => false,
                    'user_id' => $data[$key]['User']['user_id']
                );
            }
            
            // Получаем лидера Мира
            $data = $this->UserRating->getWorldLeader();
            foreach ($data as $key => $value) {
                $worldLeader[$data[$key]['User']['user_id']] = array(
                    'region_type' => BalanceHistory::BH_LEADER_WORLD,
                    'credo' => false,
                    'user_id' => $data[$key]['User']['user_id']
                );
            }
            
            // Объеденяем всех лидеров в один массив
            $dataLeaders = Hash::merge($citiesLeaders, $countriesLeaders, $worldLeader);
            
            // Получаем лидеров по кредо
            $data = $this->UserRating->getCredoLeader();
            foreach ($data as $key => $value) {
                $credoLeaders[$data[$key]['User']['id']] = array(
                    'credo' => BalanceHistory::BH_LEADER_CREDO,
                    'user_id' => intval($data[$key]['User']['id'])
                );
            }
            
            // Если лидер еще является и лидером кредо, то добавим соответствующий признак
            $dataLeaders = Hash::merge($dataLeaders, $credoLeaders);

            // Обработка лидеров
	    foreach ($dataLeaders as $leader) {
		$operType = $this->BalanceHistory->getOperationOptions();
		// определим сумму для начисления
		$sumForLeader = array();
		$sumForLeader[] = $this->BalanceHistory->getPointsAdd($leader['user_id'], $leader['region_type'], $leader['credo']);
		// начисляем полученную сумму
		foreach ($sumForLeader as $operations) {
		    foreach ($operations as $data) {
			if ($data['points']) {
			    $this->BalanceHistory->addOperation(
				$data['type'],
				$data['points'],
				$leader['user_id'],
				$operType[$data['type']]
			    );
			}
			// После начислиния добавим пользователя в таблицу лидеров
			$this->Leader->saveLeader(array(
			    'user_id' => $leader['user_id'],
			    'type' => $data['type']
			));
		    }
		}
	    }       
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}