<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 02.03.13
 * Time: 02:21
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class UserComment
 *
 * @method array children() children($id = null, $direct = false, $fields = null, $order = null, $limit = null, $page = 1, $recursive = null) Get the child nodes of the current model
 * @method array|bool getParentNode() getParentNode($id = null)
 */
class UserComment extends AppModel {
    const ID_TYPE_POSITIVE = 1;
    const ID_TYPE_NEGATIVE = 2;

    public static $types = array(
        self::ID_TYPE_POSITIVE => 'positive',
        self::ID_TYPE_NEGATIVE => 'negative',
    );

    public $actsAs = array(
        'Tree' => array(
            'left'   => 'lkey',
            'right'  => 'rkey',
            'parent' => 'parent_id'
        )
    );

    /**
     * Получить тип комментария по ID типа комментария
     *
     * @param $type_id int ID типа комментария
     *
     * @return String|null  Строка с типом комментария или null, если был неверный ID
     */
    public function getType($type_id) {
        if (isset(self::$types[$type_id])) {
            return self::$types[$type_id];
        } else {
            return null;
        }
    }

    /**
     * Получить ID типа комментария по типу комментария
     *
     * @param $type String  Тип комментария
     *
     * @return int|null ID типа комментария или null, если был неверный тип
     */
    public function getTypeId($type) {
        $key = array_search($type, self::$types);

        return ($key !== false ? $key : null);
    }

    /**
     * Получить комментарий по ID
     *
     * @param $id   int ID комментария
     *
     * @return array|bool   Массив с полями комментария или false, если такого комментария нет или он удалён
     */
    function getComment($id) {
        $comment = $this->findById($id);

        if (!$comment or empty($comment) or $comment['UserComment']['deleted']) {
            return false;
        }

        $answer = array();

        $answer['id']      = $comment['UserComment']['id'];
        $answer['text']    = htmlspecialchars($comment['UserComment']['text']);
        $answer['user_id'] = $comment['UserComment']['user_id'];
        $answer['type']    = $this->getType($comment['UserComment']['type']);
        $answer['date']    = $comment['UserComment']['modified'];

        // Если это комментарий (а не ответ), то получим ответы на комментарий
        if ($comment['UserComment']['parent_id'] == null) {
            $answer['replies'] = array();

            $children = $this->children($id, true, array('id', 'text', 'user_id', 'type', 'modified'), 'modified');
            foreach ($children as $child) {
                $reply = array();

                $reply['id']      = $child['UserComment']['id'];
                $reply['text']    = htmlspecialchars($child['UserComment']['text']);
                $reply['user_id'] = $child['UserComment']['user_id'];
                $reply['type']    = $this->getType($child['UserComment']['type']);
                $reply['date']    = $child['UserComment']['modified'];

                $answer['replies'][] = $reply;
            }
        }

        return $answer;
    }
}
