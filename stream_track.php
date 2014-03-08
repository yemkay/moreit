<?php

set_include_path(get_include_path() . PATH_SEPARATOR . './lib/');

echo 'Include Path: '.get_include_path().chr(13).chr(10);
echo 'Current Directory is: '.getcwd().chr(13).chr(10);
echo 'Changing to: '.preg_replace('/\\/[^\\/]+$/',"",$_SERVER['PHP_SELF']).chr(13).chr(10);
@chdir(preg_replace('/\\/[^\\/]+$/',"",$_SERVER['PHP_SELF']));
echo 'Current Directory is: '.getcwd().chr(13).chr(10);

require_once('twitter_config.php');
require_once('error_handler.php');
require_once('OauthPhirehose.php');
require_once('data_helper.php');

if (is_running('stream_track.php'))
{
    echo 'Another process is already running';
    exit;
}


/**
 * Example of using Phirehose to display a live filtered stream using track words 
 */
class TrackConsumer extends OauthPhirehose
{
  /**
   * Enqueue each status
   *
   * @param string $status
   */
  public function enqueueStatus($status)
  {
        global $log;
	$data = json_decode($status);

        $log->logDebug('Received data!!');
        $log->logDebug($status);

        if (!is_object($data))
        {
            //$log->logDebug('Payload is not an object');
            return;
        }

        $log->logDebug($data->user->screen_name.': '.$data->text);
        sendToPipe(PIPE_ID_TWEETS, $data);

        return;
  }

  protected function log($message)
  {
      $log->logDebug('Phirehose: ' . $message);
  }

  function checkFilterPredicates()
  {
      $keywords = DataHelper::getKeywords(true);
      $keywords = array_keys($keywords);

      $log->logInfo('Tracking keywords...');
      $log->logInfo(count($keywords).' track : '.implode(', ', $keywords));
      
      if (!empty($keywords)) $this->setTrack($keywords);
  }

}

try
{
    set_time_limit(0);
    
    // Start streaming
    define('TWITTER_CONSUMER_KEY', OAUTH_CONSUMER_KEY);
    define('TWITTER_CONSUMER_SECRET', OAUTH_CONSUMER_SECRET);

    $sc = new TrackConsumer(OAUTH_SERVICE_TOKEN, OAUTH_SERVICE_SECRET, Phirehose::METHOD_FILTER, Phirehose::FORMAT_JSON);
    $sc->setTrack(array());
    $sc->setFollow(array());
    $sc->consume();
}
catch(Exception $e)
{
    EmailFailReport('Exception in exception block', formatExceptionMessage($e));
}