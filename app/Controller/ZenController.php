<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 21.07.13
 * Time: 21:59
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class ZenController
 *
 * @property Zen     Zen
 * @property User    User
 * @property City    City
 * @property Country Country
 */
class ZenController extends AppController {
    public $uses = array(
        'Zen',
        'User',
        'City',
        'Country'
    );
    public $components = array('RequestHandler');

    /**
     * Получить сообщения с зен-экрана
     *
     * GET /zen/
     *
     * Параметры:
     *     per_page {Number}
     *     page {Number}
     *
     * Ответ:
     *     messages Array of {TZen}
     *     pages {TPage}
     */
    public function index() {
        $success = true;
        $data    = array();

        if (isset($this->request->query['per_page'])) {
            $per_page = (int)$this->request->query['per_page'];
        }
        $per_page = (isset($per_page) and $per_page > 0) ? $per_page : 25;

        if (isset($this->request->query['page'])) {
            $page = (int)$this->request->query['page'];
        }
        $page = (isset($page) and $page > 0) ? $page : 1;

        $sub_db = $this->Zen->getDataSource();
        $sub    = $sub_db->buildStatement(
                         array(
                             'fields'     => array('Zen_sub.id'),
                             'table'      => $sub_db->fullTableName($this->Zen),
                             'alias'      => 'Zen_sub',
                             'limit'      => 1,
                             'offset'     => null,
                             'joins'      => array(),
                             'conditions' => array(
                                 'Zen_sub.user_id = User.id'
                             ),
                             'order'      => array('Zen_sub.modified DESC'),
                             'group'      => null
                         ),
                         $this->Zen
        );

        $joins   = array();
        $joins[] = array(
            'table'      => $this->Zen->getDataSource()->fullTableName($this->Zen),
            'alias'      => 'Zen',
            'type'       => 'INNER',
            'conditions' => array('Zen.user_id = User.id',
                                  "Zen.id = ({$sub})")
        );
        $joins[] = array(
            'table'      => $this->City->getDataSource()->fullTableName($this->City),
            'alias'      => 'City',
            'type'       => 'LEFT',
            'conditions' => array('City.id = User.city_id')
        );
        $joins[] = array(
            'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
            'alias'      => 'Country',
            'type'       => 'LEFT',
            'conditions' => array('Country.id = User.country_id',
                                  'Country.is_deleted' => 0)
        );

        $conditions = array('User.is_ready' => 1);

        $zen_count = $this->User->find('count',
                                       array(
                                           'conditions' => $conditions,
                                           'joins'      => $joins
                                       ));

        if (($page - 1) * $per_page >= $zen_count && $zen_count > 0) {
            $data['messages'] = array();
            $data['pages']    = array(
                'per_page' => $per_page,
                'page'     => $page,
                'total'    => $zen_count
            );
        } elseif ($zen_count > 0) {
            $data['messages'] = array();
            $data['pages']    = array('per_page' => $per_page,
                                      'page'     => $page,
                                      'total'    => $zen_count);

            $zens = $this->User->find('all',
                                      array(
                                          'conditions' => $conditions,
                                          'fields'     => array(
                                              'Zen.id',
                                              'Zen.text',
                                              'Zen.modified',
                                              'User.id',
                                              'User.name',
                                              'City.id',
                                              'City.name',
                                              'City.region_name',
                                              'City.longitude',
                                              'City.latitude',
                                              'Country.id',
                                              'Country.code',
                                              'Country.name',
                                          ),
                                          'joins'      => $joins,
                                          'order'      => array('Zen.modified DESC'),
                                          'limit'      => $per_page,
                                          'offset'     => ($page - 1) * $per_page
                                      ));

            foreach ($zens as $zen) {
                $ans         = array();
                $ans['id']   = $zen['Zen']['id'];
                $ans['text'] = htmlspecialchars($zen['Zen']['text']);
                $ans['date'] = $zen['Zen']['modified'];

                $ans['user']         = array();
                $ans['user']['id']   = $zen['User']['id'];
                $ans['user']['name'] = $zen['User']['name'];

                $ans['user']['city']                  = array();
                $ans['user']['city']['id']            = $zen['City']['id'];
                $ans['user']['city']['name']          = $zen['City']['name'];
                $ans['user']['city']['region_name']   = $zen['City']['region_name'];
                $ans['user']['city']['position']      = array();
                $ans['user']['city']['position']['x'] = $zen['City']['longitude'];
                $ans['user']['city']['position']['y'] = $zen['City']['latitude'];

                $ans['user']['country']         = array();
                $ans['user']['country']['id']   = $zen['Country']['id'];
                $ans['user']['country']['code'] = $zen['Country']['code'];
                $ans['user']['country']['name'] = $zen['Country']['name'];

                $data['messages'][] = $ans;
            }
        } else {
            $data['messages'] = array();
            $data['pages']    = array('per_page' => $per_page,
                                      'page'     => $page,
                                      'total'    => $zen_count);
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Получить сообщение с зен-экрана
     *
     * GET /zen/{zen_message_id}/
     *
     * Параметры:
     *     <отсутствуют>
     *
     * Ответ:
     *     {TZen}
     *
     * @param $id
     */
    public function view($id) {
        $success = true;
        $data    = array();

        $zen = $this->Zen->getZen($id);
        if (!$zen) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'bad zen message request';
        } else {
            $data = $zen;
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }

    /**
     * Добавить сообщение для зен-экрана
     *
     * POST /zen/
     *
     * Параметры:
     *     {TZen}
     *
     * Ответ:
     *     TZen
     *
     * Доступно только для авторизованных
     */
    public function add() {
        $success = true;
        $data    = array();

        if (!$this->authCheck()) {
            return;
        }

        if (!isset($this->request->data['text']) or empty($this->request->data['text'])) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'no text specified';
        } elseif (mb_strlen($this->request->data['text']) > 160) {
            $this->response->statusCode(400);
            $success = false;
            $data    = 'text is too long';
        }

        if ($success) {
            $this->Zen->create();

            $zen_data            = array();
            $zen_data['user_id'] = $this->currentUserId;
            $zen_data['text']    = $this->request->data['text'];

            try {
                $success = $this->Zen->save(array('Zen' => $zen_data));
            } catch (Exception $e) {
                $success = false;
            }

            if (!$success) {
                $this->response->statusCode(500);
                $data = 'failed to save zen message';
            } else {
                $data = $this->Zen->getZen($this->Zen->id);

                if (!$data) {
                    $this->response->statusCode(500);
                    $success = false;
                    $data    = 'failed to get zen message';
                }
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);
        $this->set(compact('answer'));
    }
}