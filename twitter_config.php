<?php

//echo 'Current Directory is: '.getcwd().chr(13).chr(10);
//echo 'Changing to: '.preg_replace('/\\/[^\\/]+$/',"",$_SERVER['PHP_SELF']).chr(13).chr(10);
@chdir(preg_replace('/\\/[^\\/]+$/',"",$_SERVER['PHP_SELF']));
//echo 'Current Directory is: '.getcwd().chr(13).chr(10);

//PHP5 incompatiblity with DBSimple https://github.com/DmitryKoterov/DbSimple/issues/1
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT); //@todo prod
//echo 'Include Path: '.get_include_path().chr(13).chr(10);
set_include_path(get_include_path() . PATH_SEPARATOR . './lib/');

include_once('config.php');
require_once('DbSimple/Generic.php');	//DB http://en.dklab.ru/lib/DbSimple/
require_once("Arc90/Service/Twitter.php");
require_once('KLogger.php');

@header( 'Content-Type: text/html; charset=UTF-8' );
@mb_internal_encoding( 'UTF-8' );
@ini_set('mbstring.substitute_character', "none");
ini_set('memory_limit', '256M');

define('TWITTER_USER', TWITTERSCREENNAME);	
define('TWITTER_ID',	TWITTERID);


define('OAUTH_CONSUMER_KEY',	TWITTERKEY);
define('OAUTH_CONSUMER_SECRET', TWITTERSECRET);

define('OAUTH_SERVICE_TOKEN',	TWITTERTOKEN);
define('OAUTH_SERVICE_SECRET',	TWITTERTOKENSECRET);


define('DB_DATABASE',	DB_NAME);
define('DB_SERVER',	HOST);
define('DB_USER',	USER);
define('DB_PSWD',	PASSWORD);

define('LOG_PATH', './log');
define('LOG_LEVEL', KLogger::DEBUG);

function db_connect()
{
    global $DB;
    //create a new db connection and cache it
    $DB = DbSimple_Generic::connect("mysql://".DB_USER.":".DB_PSWD."@".DB_SERVER."/".DB_DATABASE);

    //see UTF instructions at http://webcollab.sourceforge.net/unicode.html
    $DB->query("SET NAMES 'utf8' COLLATE 'utf8_general_ci'"); //@todo check if this causes any side effects
    //$DB->query("SET time_zone=?", DEFAULT_TIMEZONE);	//already set to CET
    $DB->query("SET group_concat_max_len = 1000000");
    //http://www.adviesenzo.nl/examples/php_mysql_charset_fix/
    //$DB->query("SET CHARACTER SET utf8");
    $DB->setErrorHandler('databaseErrorHandler');
}

global $DB;

//Make a DB connection
if ( !isset($DB) )
{
    db_connect();
}


global $log;
if ( !isset($log) )
    $log = new KLogger(LOG_PATH, LOG_LEVEL); # Specify the log directory

function setSetting($key, $value)
{
    global $log;
    $log->logDebug("Setting $key = ".substr($value, 0, 500));
    global $DB;
    $rows = $DB->query('INSERT IGNORE INTO Settings(`key`, `value`) VALUES(?, ?, ?)
                        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            $key, $value); //without ` it throws an error

    if (empty($rows)) return null;
    return $rows[0]['value'];
}

function getSetting($key)
{
    global $DB;
    $rows = $DB->query('SELECT * FROM Settings WHERE `key` = ?', $key); //without ` it throws an error

    if (empty($rows)) return null;
    return $rows[0]['value'];
}


?>

