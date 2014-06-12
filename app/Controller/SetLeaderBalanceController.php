<?php

/* 
 * Ежедневное начисление ИВ лидерам
 */
App::uses('BalanceHistory', 'Model');
App::uses('UserRating', 'Model');
App::uses('AppController', 'Controller');
class SetLeaderBalanceController extends AppController {
    public $uses = array('UserRating', 'BalanceHistory');

    public function index(){
        try {
            $idLeader   = array();
            $dataLeader = array();
            
            /* Получаем лидера по миру */
            $data = $this->UserRating->getWorldLeader();
            $idLeader[] = intval($data['UserRating']['user_id']);
            $dataLeader[] = array(
                'id' => intval($data['UserRating']['user_id']),
                'type' => BalanceHistory::BH_LEADER_WORLD
            );

            /* Получаем лидеров по странам */
            $data = $this->UserRating->getCountriesLeader($idLeader);
            foreach ($data as $key => $value) {
                $idLeader[] = intval($data[$key]['User']['user_id']);
                $dataLeader[] = array(
                    'id' => intval($data[$key]['User']['user_id']),
                    'type' => BalanceHistory::BH_LEADER_COUNTRY
                );
            }
            
            /* Получаем лидеров по городам */
            $data = $this->UserRating->getCitiesLeader($idLeader);
            foreach ($data as $key => $value) {
                $dataLeader[] = array(
                    'id' => intval($data[$key]['User']['user_id']),
                    'type' => BalanceHistory::BH_LEADER_CITY
                );
            }
            
            /* Получаем лидера по кредо */
            $data = $this->UserRating->getCredoLeader();
            foreach ($data as $key => $value) {
                $dataLeader[] = array(
                    'id' => intval($data[$key]['User']['id']),
                    'type' => BalanceHistory::BH_LEADER_CREDO
                );
            }
            
            /* $dataLeader - содержит массив пользователей, которые являются лидерами */
            
            $operType  = $this->BalanceHistory->getOperationOptions();
            $operBonus = $this->BalanceHistory->getOperationBonus();
            foreach ($dataLeader as $key => $value) {
                $addBonus = false;
                /* Получим дату последнего начисления ИВ за лидерство */
                $lastDate = $this->BalanceHistory->getDateLeader(
                        $dataLeader[$key]['id'],
                        $dataLeader[$key]['type']
                );
                /* Если вчера пользователь получал ИВ за лидерство... */
                if ($lastDate != 0 && (time() - strtotime($lastDate) < 86400)) {
                    /* ...проверим сколько потратил (24ч) */
                    $sumOut = ($this->BalanceHistory->getPointsSpent($dataLeader[$key]['id'])) * -1;
                    if ($sumOut > 0) {
                        /* Если потратил больше чем получил за лидерство - начисляем */
                        $addBonus = true;
                    }
                } else {
                    /* Если вчера он не был лидером - начисляем */
                    $addBonus = true;
                }
                /* Увеличиваем баланс пользователя */
                if ($addBonus) {
                    $this->BalanceHistory->addOperation(
                            $dataLeader[$key]['type'],
                            $operBonus[$dataLeader[$key]['type']],
                            $dataLeader[$key]['id'],
                            $operType[$dataLeader[$key]['type']]
                    );
                }
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}