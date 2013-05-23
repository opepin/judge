<?php
namespace Netresearch;

use Symfony\Component\Console\Output\OutputInterface;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use \Exception as Exception;

/**
 * Logger for database access. 
 * Prints results in console or send data to database.
 *
 * @author Stefanie Drost<stefanie.drost@netresearch.de>
 */
class Logger extends BaseLogger
{
    protected static $loggerOutput = 'console';
    
    protected static $user;
    protected static $password;
    protected static $host;
    protected static $token;
    
    protected static $issueHandler;
    
    public static function setLoggerOutput($loggerOutput)
    {
        self::$loggerOutput = $loggerOutput;
    }
    
    public static function setUser($user)
    {
        self::$user = $user;
    }
    
    public static function setHost($host)
    {
        self::$host = $host;
    }
    
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    public static function setToken($token)
    {
        self::$token = $token;
    }
    
    public static function registerCheck($extension, $checkName)
    {
        IssueHandler::registerCheck($extension, $checkName);
    }
    
    /**
     * Prints results or sending them to database (based on config file).
     * 
     * @param String $extension path to extension
     */
    public static function printResults($extension)
    {        
        //switch between db and simple logger
        if (self::$loggerOutput === 'webservice') {
            self::sendToWebservice();
        } else if(self::$loggerOutput === 'console') {
            self::printOnOutput($extension);
        }
    }
    
    private static function printOnOutput($extension)
    {
        self::$output->writeln("Vendor: " . self::getExtVendor());
        self::$output->writeln("Extension: " . self::getExtName());
        self::$output->writeln("Version: " . self::getExtVersion());
        
        $results = IssueHandler::getResults($extension);
        foreach($results as $check => $entries) {
            self::$output->writeln('Extensions ' . $extension . ' were evaluated by check ' . $check);
            if(count($entries['issues']) > 0) {
                foreach($entries['issues'] as $issue) {
                    self::$output->writeln('* ' . $issue->getType() . ': ' . $issue->getComment());
                }
            }
        }
    }
    
    private static function sendToWebservice()
    {    
        $data = 'user=' . self::$user . '&pw=' . self::$password .
                '&version=' . urlencode(self::$extVersion) .
                '&name=' . urlencode(self::$extName) .
                '&vendor=' . urlencode(self::$extVendor) .
                '&identifier=' . urlencode(self::$extIdentifier) .
                '&results=' . urlencode(json_encode(IssueHandler::getPreparedResults()));
        if (self::$token) {
            $data .= '&token=' . self::$token;
        }

        $x = self::postToHost(self::$host, "/judgedb/", self::$host . "/judgedb/", $data);
    }
    
    /**
     * Sends data to host.
     * 
     * @param String $host
     * @param String $path
     * @param String $referer
     * @param String $data_to_send
     * @return type 
     */
    public static function postToHost($host, $path, $referer, $data_to_send) {
          $res = null;
          $fp = fsockopen($host, 80);
          //printf("Open!\n");
          fputs($fp, "POST $path HTTP/1.1\r\n");
          fputs($fp, "Host: $host\r\n");
          fputs($fp, "Referer: $referer\r\n");
          fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
          fputs($fp, "Content-length: ". strlen($data_to_send) ."\r\n");
          fputs($fp, "Connection: close\r\n\r\n");
          fputs($fp, $data_to_send);
          
          while(!feof($fp)) {
              $res .= fgets($fp, 128);
          }
          
          fclose($fp);

          return $res;
    }
    
    public static function getPassedChecksOfIssueHandler($extension)
    {
        $passedChecks = array();
        $results = IssueHandler::getResults($extension);
        foreach($results as $check => $entries) {
            if(count($entries['issues']) == 0) {
                $passedChecks[$check] = $entries;
            }
        }
        return $passedChecks;
    }
    
    public static function getFailedChecksOfIssueHandler($extension)
    {
        $failedChecks = array();
        $results = IssueHandler::getResults($extension);
        foreach($results as $check => $entries) {
            if(count($entries['issues']) > 0) {
                foreach($entries['issues'] as $issue) {
                    $tmp = array();
                    $tmp['type'] = $issue->getType();
                    $tmp['comment'] = $issue->getComment();
                    $failedChecks[$check]['comment'][] = $tmp;
                }
            }
        }
        return $failedChecks;
    }
}