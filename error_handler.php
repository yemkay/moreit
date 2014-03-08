<?php

global $log;

function is_running($script, $max = 1)
{
    $pids = array();
    exec("ps aux | grep $script | grep -v grep | grep -v /tmp | grep -v stream_startd.php | awk '{print \$2}'", $pids);
    return (count($pids) > $max);
}

function EmailFailReport($subject, $message = '')
{
    $file = @$_SERVER["SCRIPT_NAME"];
    $break = explode('/', $file);
    $pfile = $break[count($break) - 1];

    $header = "From: ".PROJ_NAME." Scripts<".PROJ_EMAIL.">".chr(13).chr(10);
    ini_set('sendmail_from', PROJ_EMAIL);
    mail(PROJ_DEV, PROJ_NAME." >> ".$subject, $message.chr(13).chr(10).'File: '.$pfile , $header);
}
//http://in3.php.net/manual/en/function.set-exception-handler.php#88082
function error_handler($code, $message, $file, $line)
{
    if (!in_array($code, array(E_ERROR, E_WARNING , E_PARSE)))
        return;

    $log->logCrit('********* Error: '.$code.' **********');
    $log->logCrit('File: '.$file.' , Line: '.$line);
    $log->logCrit('Message: '.$message);
    $log->logCrit('***********************************');
    
    if (0 == error_reporting())
    {
        return;
    }
    throw new ErrorException($message, 0, $code, $file, $line);
}

function exception_handler($e)
{
    try
    {
	global $start_time;
        EmailFailReport(PROJ_NAME.' >> Exception', 'Execution started at: '.$start_time.chr(13).chr(10).'Ended at: '.microtime(true).chr(13).chr(10).
			FormatExceptionMessage($e));
    }
    catch (Exception $e)
    {
        EmailFailReport(PROJ_NAME.' >> Exception in exception block', formatExceptionMessage($e));
    }
}


function FormatExceptionMessage($e)
{
    try
    {
        if ($e != NULL)
            return " Message: ".$e->getMessage()." on line ".$e->getLine()." <br/> ".print_r($e, false);
    }
    catch (Exception $ex)
    {
        return "Exception occurred while formatting exception message. ".print_r($ex, false);
    }
    return "";
}

function error_log_db($component, $message)
{
    global $DB;
    $DB->query('INSERT IGNORE INTO error_log(component,created_at,message) VALUES(?,NOW(),?)',
                              $component, from_RESTdate($data->created_at), $message);
}

set_error_handler("error_handler");
set_exception_handler("exception_handler");

function sendToPipe($pipe_id, $data)
{
    $err = '';
    // Create System V Message Queue. Integer value is the number of the Queue
    $queue = msg_get_queue($pipe_id, 0777);
    msg_set_queue($queue, array('msg_qbytes'=>'1000000000'));

    $msgtype_send = 1;          // Any Integer above 0. It signeds every Message. So you could handle multible message type in one Queue.

    //if (stripos($data->text, 'RT ') !== FALSE) var_dump($data);
    if(msg_send($queue, $msgtype_send, $data, TRUE, FALSE, $err)===true) {
        $log->logInfo("Message sent to queue #".$pipe_id);
        return true;
    } else {
        $message = 'Failed to send message to queue: '.print_r($err, true);
        $log->logError($message);
        EmailFailReport('Streaming', $message);
        $queue_status=msg_stat_queue($queue);
        $message .= ('. Queue length: '.$queue_status['msg_qnum'].', Queue: '.print_r($queue_status, true));
        return false;
    }
}

function getPageContents($url, $method = 'GET', $post_data = array(), $timeout = 60)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ch, CURLOPT_USERAGENT, TWITTER_USER); //some web servers reject request, if user agent is not specified
    if('POST' == ($method = strtoupper($method)))
    {
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }
    else if('GET' != $method)
    {
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
    //curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $data = curl_exec($ch);
    $log->logInfo('Curl status: No: ' . curl_errno($ch).', Error info: '. curl_error($ch));
    $meta = curl_getinfo($ch);

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $log->logInfo('HTTP: '.$code);
    $code = floor(curl_getinfo($ch, CURLINFO_HTTP_CODE)/100);
    if( $code != 2 && $code != 3)
    {
	curl_close($ch);
	return false;
    }
    curl_close($ch);
    return $data;
}

function databaseErrorHandler($message, $info)
{
    if (!error_reporting()) return;
    $message = "SQL Error: $message<br><pre>"; print_r($info, true); echo "</pre>";
    $log->logCrit($message);
    EmailFailReport('Database Error', $message);
    exit();
}

//REST API returns date in this format Sat Apr 18 14:07:22 +0000 2009
if (!function_exists('from_RESTdate'))
{
function from_RESTdate($date, $unix=false)
{
    global $def_timezone;
    date_default_timezone_set($def_timezone);
    list($D, $M, $d, $h, $m, $s, $z, $y) = sscanf($date, "%3s %3s %2d %2d:%2d:%2d %5s %4d");
    //$log->logDebug("$d $M $y $h:$m:$s $z");
    $res = $unix? strtotime("$d $M $y $h:$m:$s $z"):date('Y-m-d H:i:s', strtotime("$d $M $y $h:$m:$s $z"));
    date_default_timezone_set('US/Central');
    return $res;
}
}

?>