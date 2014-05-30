<?
/**
 * Created by JetBrains PhpStorm.
 * User: Rem
 * Date: 07.09.13
 * Time: 14:43
 */

/**
 * Class ExecutorShell
 *
 * @property ActiveShell ActiveShell
 * @property ShellLog    ShellLog
 */
class ExecutorShell extends AppShell {
    public $tasks = array(
        'RatingReduce',
        'RatingIncreaseFromMin'
    );

    public $uses = array(
        'ActiveShell',
        'ShellLog'
    );

    protected $running_system;
    protected $active_tasks;

    function getOS() {
        $os = strtolower(PHP_OS);
        if (substr($os, 0, 3) == 'win') {
            return 2;
        } elseif ($os == 'linux') {
            return 1;
        }

        return 2;
    }

    function checkProcessRun($pid) {
        switch ($this->running_system) {
            case 1: // linux
                exec("ps -p {$pid}", $output);
                // $this->console_log(print_r($output, 1));
                if (isset($output[1]) and preg_match('/' . $pid . '/i', $output[1])) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 2: // win
            default:
                exec("tasklist /FI \"PID eq {$pid}\" /FO CSV /NH", $output);
                // $this->console_log(print_r($output, 1));
                if (isset($output[0]) and preg_match('/"' . $pid . '"/i', $output[0])) {
                    return true;
                } else {
                    return false;
                }
        }

        return false;
    }

    function killProcess($pid) {
        switch ($this->running_system) {
            case 1: // linux
                exec("kill -9 {$pid}");
                break;
            case 2: // win
            default:
                exec("taskkill /PID {$pid} /F");
        }
    }

    public function getActiveShells() {
        $this->active_tasks = array();
        $running_shells = $this->ActiveShell->find('all');
        foreach ($running_shells as $row) {
            $shell = $row['ActiveShell'];

            if (strtotime($shell['expired']) < time()) {
                if ($this->checkProcessRun($shell['pid'])) {
                    $this->killProcess($shell['pid']);
                    $result = 'process terminated';
                } else {
                    $result = 'process lost';
                }

                if (!$this->ActiveShell->endShell($shell['id'], $shell['name'], $shell['created'], false, $result)) {
                    $this->_out("Can't end shell: name={$shell['name']}; id={$shell['id']}; created={$shell['created']}; result={$result}");
                }
            } else {
                $this->active_tasks[$shell['name']] = $shell;
            }
        }
    }

    public function runTask($task_name, $max_execution_time = 120, $parameters = array()) {
        $this->getActiveShells();

        if (isset($this->active_tasks[$task_name])) {
            $this->_out("$task_name no need to run: it already runs");
            return false;
        }

        if (!$this->hasTask($task_name) and !($task = $this->Tasks->load($task_name))) {
            $this->_out("$task_name no need to run: it cannot be located");
            return false;
        } elseif (!isset($task)) {
            $task = $this->$task_name;
        }

        if (method_exists($task, 'needToRun') and !$task->needToRun()) {
            $this->_out("$task_name no need to run: it no need to run");
            return false;
        }

        if (!($task_id = $this->ActiveShell->startShell($task_name, $max_execution_time, getmypid()))) {
            $this->_out("Can't start shell: name={$task_name}; pid=" . getmypid());
            return false;
        }

        try {
            $this->_out("starting {$task_name}...");
            $this->runCommand($task_name, $parameters);
//            $task->runCommand('execute', $parameters);
            // $task->execute();
            $task_started = $this->ActiveShell->field('created', array('id' => $task_id));
            if (!$this->ActiveShell->endShell($task_id, $task_name, $task_started)) {
                $this->_out("Can't end shell: name={$task_name}; id={$task_id}; created={$task_started}");
                return false;
            }
        } catch (Exception $e) {
            $this->_out("Can't run task: exception={$e->getMessage()}; name={$task_name}; parameters=" . print_r($parameters, 1));
            return false;
        }

        return true;
    }

    public function main() {
        $this->running_system = $this->getOS();

        // здесь идёт список запускаемых задач
        $this->runTask('RatingReduce', 10);
        $this->runTask('RatingIncreaseFromMin', 10);
    }
}