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
    
    private function printOnOutput($extension)
    {
        foreach (self::getFailedChecks($extension) as $failedCheck) {
            self::error('<comment>"%s" failed check "%s"</comment>', array($extension, $failedCheck), false);

            if (array_key_exists('issues', IssueHandler::$results[$extension][$failedCheck])) {
                foreach (IssueHandler::$results[$extension][$failedCheck]['issues'] as $issue) {
                    self::$output->writeln('* ' . $issue->getType() . ': ' . $issue->getComment());
                }
            }
        }
        foreach (self::getPassedChecks($extension) as $passedCheck) {
            self::log('"%s" passed check "%s"', array($extension, $passedCheck));
            
            if (array_key_exists($passedCheck, IssueHandler::$results[$extension]) &&
                    array_key_exists('issues', IssueHandler::$results[$extension][$passedCheck])) {
                foreach (IssueHandler::$results[$extension][$passedCheck]['issues'] as $issue) {
                    self::$output->writeln('* ' . $issue->getType() . ': ' . $issue->getComment());
                }
            }
        }
        $score = self::getScore($extension);
        if (0 < $score) {
            $message = sprintf('<info>Extension "%s" succeeded in evaluation: %d</info>', $extension, $score);
        } elseif (0 == $score) {
            $message = sprintf('<comment>Result of "%s" evaluation: %d</comment>', $extension, $score);
        } else {
            $message = sprintf('<error>Extension "%s" failed evaluation: %d</error>', $extension, $score);
        }
        self::$output->writeln($message);
        
    }
    
    private static function sendToWebservice()
    {
        $data = 'user=' . self::$user . '&pw=' . self::$password .
                '&results=' . json_encode(IssueHandler::getPreparedResults());
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
    public function postToHost($host, $path, $referer, $data_to_send) {
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
}