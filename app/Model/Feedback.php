<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.12.13
 * Time: 01:03
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class Feedback
 */
class Feedback extends AppModel {
    public $useTable = 'feedback';

    const ID_STATUS_NEW               = 0;
    const ID_STATUS_NOTIFICATION_SENT = 1;
    const ID_STATUS_CLOSED            = 10;

    public $validate = array(
        'email' => array('rule' => array('email', true))
    );
}