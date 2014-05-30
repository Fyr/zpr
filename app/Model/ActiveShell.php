<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 07.09.13
 * Time: 16:46
 * To change this template use File | Settings | File Templates.
 */
class ActiveShell extends AppModel {
    public function startShell($task_name, $max_execution_time, $pid) {
        $hash = sha1(rand() . microtime() . $pid);

        try {
            $this->create();

            $data            = array();
            $data['name']    = $task_name;
            $data['pid']     = $pid;
            $data['hash']    = $hash;
            $data['expired'] = date('Y-m-d H:i:s', strtotime("+{$max_execution_time} seconds"));

            if (!$this->save($data)) {
                return false;
            }

            $new_hash = $this->field('hash', array('id' => $this->id));

            if ($new_hash != $hash) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        return $this->id;
    }

    public function endShell($id, $name, $started, $is_success = true, $result = '') {
        $shellLog = ClassRegistry::init('ShellLog');
        try {
            $shellLog->create();

            $data               = array();
            $data['name']       = $name;
            $data['is_success'] = $is_success ? 1 : 0;
            $data['started']    = date('Y-m-d H:i:s', strtotime($started));
            $data['result']     = $result;

            if (!$shellLog->save($data)) {
                echo "can't insert shell log: " . print_r($data, 1);
                return false;
            }

            $this->delete($id);
        } catch (Exception $e) {
            echo "exception in endShell: " . $e->getMessage();
            return false;
        }

        return true;
    }
}