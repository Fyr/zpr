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
            /* Получаем лидеров по городам */
            $data = $this->UserRating->getCitiesLeader();
            foreach ($data as $key => $value) {
                $citiesLeaders[$data[$key]['User']['user_id']] = array(
                    'type' => BalanceHistory::BH_LEADER_CITY,
                    'user_id' => $data[$key]['User']['user_id']
                );
            }
            
            /* Получаем лидеров по странам */
            $data = $this->UserRating->getCountriesLeader();
            foreach ($data as $key => $value) {
                $countriesLeaders[$data[$key]['User']['user_id']] = array(
                    'type' => BalanceHistory::BH_LEADER_COUNTRY,
                    'user_id' => $data[$key]['User']['user_id']
                );
            }
            
            /* Получаем лидера Мира */
            $data = $this->UserRating->getWorldLeader();
            foreach ($data as $key => $value) {
                $worldLeader[$data[$key]['User']['user_id']] = array(
                    'type' => BalanceHistory::BH_LEADER_WORLD,
                    'user_id' => $data[$key]['User']['user_id']
                );
            }
            
            /* Объеденяем всех лидеров в один массив */
            $dataLeaders = Hash::merge($citiesLeaders, $countriesLeaders, $worldLeader);
            
            /* Получаем лидера по кредо */
            $data = $this->UserRating->getCredoLeader();
            foreach ($data as $key => $value) {
                $credoLeaders[$data[$key]['User']['id']] = array(
                    'type' => BalanceHistory::BH_LEADER_CREDO,
                    'user_id' => intval($data[$key]['User']['id'])
                    
                );
            }
            
            /* Добавим к массиву с лидерами массив с лидерами кредо */
            $dataLeaders = array_merge($dataLeaders, $credoLeaders);
            
            $operType  = $this->BalanceHistory->getOperationOptions();
            $operBonus = $this->BalanceHistory->getOperationBonus();
            $accruals = array();
            foreach ($dataLeaders as $key => $value) {
                $addBonus = false;
                /* Получим дату последнего лидерства */
                $lastDate = $this->Leader->getDateLeader(
                        $dataLeaders[$key]['user_id'],
                        $dataLeaders[$key]['type']
                );
                /* Если вчера пользователь получал ИВ за лидерство... */
                if ($lastDate != 0 && (time() - strtotime($lastDate) < 86400)) {
                    /* ...проверим сколько потратил (24ч) */
                    $sumOut = $this->BalanceHistory->getPointsSpent($dataLeaders[$key]['user_id']) * -1;
                    /* Подсчитаем сколько нужно начислить ИВ исходя из потраченной суммы */
                    $pointsFull = $operBonus[$dataLeaders[$key]['type']] - ($operBonus[$dataLeaders[$key]['type']] - $sumOut);
                    /* Прировняем Points к максимальному значению по текущей операции */
                    $points = ($pointsFull > $operBonus[$dataLeaders[$key]['type']]) ? $operBonus[$dataLeaders[$key]['type']] : $pointsFull;
                    
                    if (isset($accruals[$dataLeaders[$key]['user_id']])) {
                        if (($pointsFull - $accruals[$dataLeaders[$key]['user_id']]) > $operBonus[$dataLeaders[$key]['type']]) {
                            $points = $operBonus[$dataLeaders[$key]['type']];
                        } else {
                            $points = $pointsFull - $accruals[$dataLeaders[$key]['user_id']];
                        }
                    }
                    /* Начисляем бонус если points больше 0 */
                    if ($points > 0 ) {
                        $addBonus = true;
                        if(!isset($accruals[$dataLeaders[$key]['user_id']])) {
                            $accruals = Hash::merge($accruals, array($dataLeaders[$key]['user_id'] => 0));
                        }
                        $accruals[$dataLeaders[$key]['user_id']] += $points; 
                    }
                } else {
                    /* Если вчера он не был лидером - начисляем */
                    $addBonus = true;
                    $points = $operBonus[$dataLeaders[$key]['type']];
                }
                if ($addBonus) {
                    /* Увеличиваем баланс пользователя */
                    $this->BalanceHistory->addOperation(
                            $dataLeaders[$key]['type'],
                            $points,
                            $dataLeaders[$key]['user_id'],
                            $operType[$dataLeaders[$key]['type']]
                    );
                }
                /* Добавим пользователя в таблицу лидеров */
                $this->Leader->create();
                $this->Leader->save(array(
                    'user_id' => $dataLeaders[$key]['user_id'],
                    'type' => $dataLeaders[$key]['type']
                ));
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}