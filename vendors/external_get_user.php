<?php

// include('external_auth_check.php');

class ClassRegistry extends Dummy {
    static function init($class) {
        return new $class;
    }
}

class Country extends AppModel {

}
class City extends AppModel {

}
class UserRating extends AppModel {

}
class Credo extends AppModel {

}

include_once($API_DIRECTORY . 'app/Model/User.php');

global $user_id, $user_data;
$user = new User();
$user_data = $user->getUser($user_id, false);

