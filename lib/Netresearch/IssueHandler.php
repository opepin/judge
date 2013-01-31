<?php
namespace Netresearch;

class IssueHandler 
{

    protected static $results = array();
//    protected static $files;
//    protected static $currentIssue;
//    protected static $details;
//    protected static $checkName;
    
    function __construct() 
    {
        
    }
    
    public static function getResults() 
    {
        return self::$results;
    }
    
    /**
     * Adds an object of type issue to array.
     * @param Issue $issue 
     */
    public static function addIssue($issue)
    {
        self::$results[$issue->getCheckName()][] = $issue;
    }
    
//    public function addIssue($check, $type, $comment) {
//              
//        if (false == array_key_exists($check, self::$results)) {
//            self::$checkName = $check;
//            self::$results[$check] = array();
//            self::$currentIssue = array();
//            self::$details = array();
//            $this->setFailed($check, true);
//        }
//        
//        if (!is_null(self::$files)) {
//            foreach(self::$files as $file) {
//                $tmp = array();
//                $tmp['type'] = $type;
//                $tmp['comment'] = $comment;
//                $tmp['file'] = $file;
//                foreach(self::$details as $detail => $value) {
//                   $tmp[$detail] = $value; 
//                }
//                self::$currentIssue[] = $tmp;
//            }
//            self::resetFiles();
//        }
//        else {
//            $tmp = array();
//            $tmp['type'] = $type;
//            $tmp['comment'] = $comment;
//            foreach(self::$details as $detail => $value) {
//                   $tmp[$detail] = $value; 
//                }
//            self::$currentIssue[] = $tmp;
//        }
//    }
    
//    public function setFailed($check, $failed) {
//        self::$results[$check]['failed'] = $failed;
//    }
//    
//    public function addDetail($name, $value) {
//        self::$details[$name] = $value;
//    }
//    
//    public function addFilesForIssue($files) {
//        self::$files = $files;
//    }
//    
//    public function resetFiles() {
//        self::$files = null;
//    }
//    
//    public function save() {
//        foreach(self::$currentIssue as $issue) {
//            self::$results[self::$checkName]['issues'][] = $issue;
//        }
//        self::$currentIssue = array();
//    }

}