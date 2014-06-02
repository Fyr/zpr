<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 22.07.12
 * Time: 20:40
 * To change this template use File | Settings | File Templates.
 */
class UserVote extends AppModel {
    const MAX_DAILY_VOTE_PERCENT = 30;

    const VOTE_TYPE_STANDARD        = 1;
    const VOTE_TYPE_VOTER_RATING    = 2;
    const VOTE_TYPE_SYSTEM          = 3;
    const VOTE_TYPE_SYSTEM_REDUCE   = 4;
    const VOTE_TYPE_SYSTEM_INCREASE = 5;

    public static $VOTE_TYPES = array(
        self::VOTE_TYPE_STANDARD,
        self::VOTE_TYPE_VOTER_RATING,
        self::VOTE_TYPE_SYSTEM,
        self::VOTE_TYPE_SYSTEM_REDUCE,
        self::VOTE_TYPE_SYSTEM_INCREASE
    );

    public static $SYSTEM_VOTE_TYPES = array(
        self::VOTE_TYPE_SYSTEM,
        self::VOTE_TYPE_SYSTEM_REDUCE,
        self::VOTE_TYPE_SYSTEM_INCREASE
    );

    /**
     * Получить количество доступных на текущий момент голосов для пользователя при голосовании за указанного участника
     *
     * За день можно проголосовать на весь свой рейтинг, но не более 30% за одного участника
     *
     * @param $user_id          int ID пользователя, который голосует
     * @param $participant_id   int ID пользователя, за которого голосуют
     *
     * @return int  Количество доступных голосов
     */
    public function getAvailableDailyVotes($user_id, $participant_id) {
        $userRating = ClassRegistry::init('UserRating');

        // Рейтинг пользователя из которого рассчитывается количество возможных голосов
        $user_rating = $userRating->find('first',
                                         array(
                                              'conditions' => array('user_id' => $user_id),
                                              'fields'     => array('IFNULL(positive_votes, 0) - IFNULL(negative_votes, 0) as rating')
                                         ));

        if ($user_rating and !empty($user_rating) and isset($user_rating[0]['rating'])) {
            $user_rating = $user_rating[0]['rating'];
        } else {
            return 0; // рейтинг нулевой, следовательно и голосовать не может
        }

        if ($user_rating <= 0) {
            return 0;
        }

        // Получим голоса, которые уже отданы и которые отданы за конкретного участника
        $votes_already_res = $this->find('first',
                                         array(
                                              'conditions' => array(
                                                  'user_id'    => $user_id,
                                                  'vote_type'  => self::VOTE_TYPE_STANDARD,
                                                  'created >=' => date('Y-m-d 00:00:00')
                                              ),
                                              'fields'    => array(
                                                  'SUM(ABS(vote)) AS votes',
                                                  'SUM(CASE WHEN participant_id = ' . (int)$participant_id . ' THEN ABS(vote) ELSE 0 END) AS participant_votes'
                                              )
                                         ));

        if ($votes_already_res and !empty($votes_already_res) and isset($votes_already_res[0]['votes'])) {
            $votes_already     = $votes_already_res[0]['votes'];
            $participant_votes = $votes_already_res[0]['participant_votes'];
        } else {
            $votes_already     = 0;
            $participant_votes = 0;
        }

        // Количество для голосов доступных на текущий день с учётом уже прошедших голосований
        $daily_votes = ceil(($user_rating + $votes_already) * self::MAX_DAILY_VOTE_PERCENT / 100);

        // Количество доступных голосов за конкретного участника
        return ($participant_votes >= $daily_votes ? 0 : $daily_votes - $participant_votes);
    }

    /**
     * Проголосовать за пользователя
     *
     * @param $user_id          int         ID пользователя, который голосует
     * @param $participant_id   int         ID пользователя, за которого голосуют
     * @param $votes            int         Голоса пользователя
     * @param $vote_type        int[1]      Тип голосования
     * @param $checkLeader      bool[true]  Проверить ли лидера на зал славы
     *
     * @return bool             Удалось ли проголосовать?
     * @throws Exception        при ошибке
     */
    public function vote($user_id, $participant_id, $votes, $vote_type = self::VOTE_TYPE_STANDARD, $checkLeader = true) {
        $votes = (int) $votes;
        if ($votes == 0) {
            return true;
        }

        /** @var UserRating $userRating */
        $userRating        = ClassRegistry::init('UserRating');
        $participantRating = $userRating->findByUserId($participant_id);

        if (!in_array($vote_type, self::$SYSTEM_VOTE_TYPES)) {
            $voterRating       = $userRating->findByUserId($user_id);
            // Если не нашли рейтинга голосующего, значит какая-то ужасная ошибка произошла
            if (!$voterRating or empty($voterRating) or !isset($voterRating['UserRating'])) {
                return false;
            }
        } else {
            $user_id = null;
        }

        // Если не нашли рейтинга того, за кого голосуют, то создадим новую запись в таблице
        if (!$participantRating or empty($participantRating) or !isset($participantRating['UserRating'])) {
            $user = ClassRegistry::init('User');
            $user_data = $user->findById($participant_id);

            if (empty($user_data)) {
                return false;
            }

            $userRating->create();
            $user_rating_data = array();
            $user_rating_data['user_id'] = $participant_id;
            $user_rating_data['country_id'] = $user_data['User']['country_id'];
            $user_rating_data['city_id'] = $user_data['User']['city_id'];

            try {
                $result = $userRating->save($user_rating_data);

                if (!$result) {
                    return false;
                }

                $participantRating = $userRating->findByUserId($participant_id);
            } catch (Exception $e) {
                $message = "Can't create user rating";
                if (Configure::read('debug') > 0) {
                    $message .= ': ' . $e->getMessage();
                }

                throw(new Exception($message));
            }
        }

        // Попробуем стартануть транзакцию
        try {
            $dataSource = $this->getDataSource();
            $dataSource->begin();
        } catch (Exception $e) {
            return false;
        }

        try {
            // Сохранение записи о голосовании
            $this->create();

            $data = array();
            $data['user_id']        = $user_id;
            $data['participant_id'] = $participant_id;
            $data['vote']           = $votes;
            $data['vote_type']      = $vote_type;

            $result = $this->save($data);
            if (!$result) {
                throw(new Exception("Can't create user vote"));
            }

            // Изменим рейтинг участника на величину голосов
            $conditions = array('id' => $participantRating['UserRating']['id']);

            if ($votes > 0) {
                $fields = array('positive_votes' => 'positive_votes + ' . $votes);
            } else {
                $fields = array('negative_votes' => 'negative_votes + ' . abs($votes));
            }

            $result = $userRating->updateAll($fields, $conditions);
            if (!$result) {
                throw(new Exception("Can't change participant user rating"));
            }

            if (!in_array($vote_type, self::$SYSTEM_VOTE_TYPES)) {
                // Созадим запись о понижении рейтинга голосующего
                $this->create();

                $data = array();
                $data['user_id']        = null;
                $data['participant_id'] = $user_id;
                $data['vote']           = -$votes;
                $data['vote_type']      = self::VOTE_TYPE_VOTER_RATING;

                $result = $this->save($data);
                if (!$result) {
                    throw(new Exception("Can't create user vote for voter rating decrease"));
                }

                // Изменим собственный рейтинг голосующего
                $conditions = array('id' => $voterRating['UserRating']['id']);
                $fields = array(
                    'negative_votes' => 'negative_votes + ' . abs($votes),
                );

                $result = $userRating->updateAll($fields, $conditions);
                if (!$result) {
                    throw(new Exception("Can't change voter user rating"));
                }
            }

            // Закрепляем транзакцию
            $dataSource->commit();
        } catch (Exception $e) {
            // В случае ошибки, откатим транзакцию
            $dataSource->rollback();
            return false;
        }

        if ($checkLeader) {
            /** @var HallOfFame $HallOfFame */
            $HallOfFame = ClassRegistry::init('HallOfFame');
            $HallOfFame->checkLeader();
        }

        return true;
    }
}