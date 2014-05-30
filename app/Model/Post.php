<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.05.12
 * Time: 1:16
 * To change this template use File | Settings | File Templates.
 */

class Post extends AppModel {
    public $validate = array(
        'title' => array(
            'rule' => 'notEmpty'
        ),
        'body' => array(
            'rule' => 'notEmpty'
        )
    );
}