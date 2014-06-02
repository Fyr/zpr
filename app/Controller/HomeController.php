<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.05.12
 * Time: 1:51
 * To change this template use File | Settings | File Templates.
 */
class HomeController extends AppController {
    public $helper = array('Html', 'Form');
    public $uses = array('User',
                         'UserRating',
                         'UserVote',
                         'Auth');

    public function index() {
        $this->json_render = false;

        $this->layout = 'html';

        $this->set('title_for_layout', 'Список сервисов');
    }
}
