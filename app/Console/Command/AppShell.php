<?php
/**
 * AppShell file
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Shell', 'Console');

/**
 * Application Shell
 *
 * Add your application-wide methods in the class below, your shells
 * will inherit them.
 *
 * @package       app.Console.Command
 */
class AppShell extends Shell {
    protected function _out($message) {
        $this->out(date("Y-m-d H:i:s\t") . $message);
    }

    public function needToRun() {
        $shellLog   = ClassRegistry::init('ShellLog');
        $class_name = get_class($this);
        $class_name = substr($class_name, 0, -4);
        $cnt = $shellLog->find('count', array('conditions' => array('name'       => $class_name,
                                                                    'created >'  => date('Y-m-d 00:00:00'),
                                                                    'is_success' => 1)));
        return $cnt == 0;
    }
}
