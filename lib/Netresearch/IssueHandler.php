<?php
namespace Netresearch;

class IssueHandler 
{

    public static $results = array();

    public static function getResults() {
        return self::$results;
    }

    /**
     * Serializes an object of type issue and adds it to array.
     * @param Issue $issue 
     */
    public static function addIssue($issue)
    {
        self::$results[$issue->getCheckname()]['issues'][] = serialize($issue);
    }
    
    /**
     * Gets all issues associated to check name.
     * @param String $checkName The name of the check.
     * @return array 
     */
    public static function getIssuesByCheck($checkName)
    {
        return self::$results[$checkName]['issues'];
    }
    
    public static function getIssuesByType($check, $type)
    {
        
    }
    
    public static function countIssuesByCheck($check)
    {
        
    }
    
    public static function countIssuesByType($check, $type)
    {
        
    }
}