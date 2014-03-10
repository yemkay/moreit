<?php

require_once('twitter_config.php');
require_once('error_handler.php');

abstract class pipe_processor
{

    var $_stopped = false;
    var $_receive_option = NULL;

    function pipe_processor()
    {
        $this->start_time = microtime(true);
        
        $this->change_dir();

        //echo ini_get('max_execution_time');
        ini_set('max_execution_time', 12000);
        //echo ini_get('max_execution_time');
        ignore_user_abort(true);
        //impose a comfortable time limit
        set_time_limit(0);
        $this->init_memory = memory_get_usage();
        trace("Current memory usage: ".memory_get_usage());
    }

    function change_dir()
    {
        //echo 'Current Directory is: '.getcwd().chr(13).chr(10);
        //echo 'Changing to: '.preg_replace('/\\/[^\\/]+$/',"",$_SERVER['PHP_SELF']).chr(13).chr(10);
        @chdir(preg_replace('/\\/[^\\/]+$/',"",$_SERVER['PHP_SELF']));
        //echo 'Current Directory is: '.getcwd().chr(13).chr(10);
    }

    function is_running($script, $max = 1)
    {
        $pids = array();
        exec("ps aux | grep $script | grep -v grep | grep -v /tmp | grep -v stream_startd.php | awk '{print \$2}'", $pids);
        return (count($pids) > $max);
    }

    function can_start()
    {
        $this->file_name = get_class($this);
        trace('Script: '.$this->file_name);
        if ($this->is_running($this->file_name, 1)) 
        {
            //echo 'Another process is already running';
            exit;
        }
    }

    function run($pipe_id)
    {
        $this->can_start();

        $queue = msg_get_queue($pipe_id);
        // Receive options
        $msgtype_receive = 0;       // Whiche type of Message we want to receive ? (Here, the type is the same as the type we send,
                                  // but if you set this to 0 you receive the next Message in the Queue with any type.
        $maxsize = 1000000;             // How long is the maximal data you like to receive.
        $option_receive = $this->_receive_option; // If there are no messages of the wanted type in the Queue continue without wating.
                                  // If is set to NULL wait for a Message.

        $this->_stopped = false;

        while(1 && $this->can_continue())
        {
            try
            {
                trace('Listening to queue #'.$pipe_id);
                if (msg_receive($queue, $msgtype_receive, $msgtype_erhalten, $maxsize, $data, TRUE, $option_receive, $err)===true)
                {
                    trace('Received a message...');
                    $this->process($data);
                }
                else
                {
                    $this->post_message();
                    $message = ('Failed to receive message from queue: '.print_r($err, true));
                    trace($message);
                    EmailFailReport($this->file_name, $message);
                    continue;
                }
                $queue_status = msg_stat_queue($queue);
                trace('Queue length: '.$queue_status['msg_qnum']);
            }
            catch(Exception $exp)
            {
                $message = FormatExceptionMessage($exp);
                trace($message);
            }
        }

        EmailFailReport($this->file_name.' is terminating');
        trace($this->file_name.' is terminating...');
        trace("Memory consumed: ".memory_get_usage()-$this->init_memory);
        trace("Time taken: " .(microtime(true)-$this->start_time));
    }

    function can_continue()
    {
        return !$this->_stopped;
    }

    function stop()
    {
        $this->_stopped = true;;
    }

    function post_message()
    {
        //do nothing
        //handle in child class and sleep if needed
    }

    abstract function process($data);
}
?>