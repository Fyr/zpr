<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 14.07.12
 * Time: 18:41
 * To change this template use File | Settings | File Templates.
 */
class HallOfFame extends AppModel {
    public $useTable = 'hall_of_fame';
    public $primaryKey = 'user_id';

    public function checkLeader() {
        /** @var User $userModel */
        $userModel = ClassRegistry::init('User');
        /** @var UserRating $userRatingModel */
        $userRatingModel = ClassRegistry::init('UserRating');

        $joins   = array();
        $joins[] = array(
            'table'      => $userRatingModel->getDataSource()->fullTableName($userRatingModel),
            'alias'      => 'UserRating',
            'type'       => 'LEFT',
            'conditions' => array('UserRating.user_id = User.id')
        );
        $joins[] = array(
            'table'      => $this->getDataSource()->fullTableName($this),
            'alias'      => 'HallOfFame',
            'type'       => 'LEFT',
            'conditions' => array(
                'HallOfFame.user_id = User.id',
                'HallOfFame.achieved' => null
            )
        );

        // Выберем лидера со связкой зала славы
        $leader = $userModel->find(
            'first',
            array(
                 'conditions' => array(
                     'User.is_ready' => 1,
                 ),
                 'joins'      => $joins,
                 'fields'     => array(
                     'User.id',
                     'HallOfFame.created',
                     'now() as achievement_time',
                     '(now() - INTERVAL 14 DAY > HallOfFame.created) as is_achieved'
                 ),
                 'order'      => array(
                     'IFNULL(UserRating.positive_votes - UserRating.negative_votes, 0) DESC',
                     'User.name'
                 )
            )
        );

        if (!$leader or empty($leader)) {
            return false;
        }

        // Если лидер не учитывается в зале славы, то добавим его туда
        if ($leader['HallOfFame']['created'] === null) {
            try {
                // Попробуем стартануть транзакцию
                $dataSource = $this->getDataSource();
                $dataSource->begin();
            } catch (Exception $e) {
                return false;
            }

            try {
                // перед удалением надо проверить кто там у нас был в зале?
                // может его давно пора перенести, просто процедура не инициировалась с тех пор как ему пора стало...
                $current_hall = $this->find('all',
                                            array(
                                                 'conditions' => array(
                                                     'achieved' => null,
                                                     'created < now() - INTERVAL 14 DAY'
                                                 ),
                                                 'fields'     => array(
                                                     'user_id',
                                                     'now() as achievement_time'
                                                 )
                                            ));
                if ($current_hall and !empty($current_hall)) {
                    try {
                        // Попробуем стартануть транзакцию
                        $dataSource = $this->getDataSource();
                        $dataSource->begin();
                    } catch (Exception $e) {
                        return false;
                    }

                    try {
                        foreach ($current_hall as $hall) {
                            // Установим флаг попадения в зал славы у пользователя
                            $userModel->create();
                            $userModel->id = $hall['HallOfFame']['user_id'];
                            $userModel->saveField('is_in_hall_of_fame', 1);

                            // Установим дату попадения в зал славы
                            $this->create();
                            $this->id = $hall['HallOfFame']['user_id'];
                            $this->saveField('achieved', $hall[0]['achievement_time']);
                        }

                        // Закрепляем транзакцию
                        $dataSource->commit();
                    } catch (Exception $e) {
                        $dataSource->rollback();

                        return false;
                    }
                }

                // Удалим всех, кто до этого учитывался в зале славы
                $this->deleteAll(array('achieved' => null), false);

                // Создадим запись в зале славы для подсчёта успеха текущего лидера
                $this->create();

                $data            = array();
                $data['user_id'] = $leader['User']['id'];

                $this->save($data);

                // Закрепляем транзакцию
                $dataSource->commit();
            } catch (Exception $e) {
                $dataSource->rollback();

                return false;
            }
        } elseif ($leader[0]['is_achieved'] == 1) { // Если лидер уже учитывается в зале славы, то проверим не пора ли его туда прописать
            try {
                // Попробуем стартануть транзакцию
                $dataSource = $this->getDataSource();
                $dataSource->begin();
            } catch (Exception $e) {
                return false;
            }

            try {

                // Установим флаг попадения в зал славы у пользователя
                $userModel->create();
                $userModel->id = $leader['User']['id'];
                $userModel->saveField('is_in_hall_of_fame', 1);

                // Установим дату попадения в зал славы
                $this->create();
                $this->id = $leader['User']['id'];
                $this->saveField('achieved', $leader[0]['achievement_time']);

                // Закрепляем транзакцию
                $dataSource->commit();
            } catch (Exception $e) {
                $dataSource->rollback();

                return false;
            }
        }

        return true;
    }
}