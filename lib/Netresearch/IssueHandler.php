<?php
namespace Netresearch;

class IssueHandler 
{

    public static $results = array();

    public static function getResults() 
    {
        return self::$results;
    }
    
    public static function registerCheck($extension, $checkName)
    {
        self::$results[$extension][$checkName]['issues'] = array();
    }
    
    public static function getPreparedResults()
    {
        $dbResults = array();
        
        foreach (self::$results as $extension => $entries) {
            foreach ($entries as $key => $value) {
                $dbResults[$extension][$key] = $value;
                foreach ($dbResults[$extension][$key]['issues'] as $id => $issue) {
                    $dbResults[$extension][$key]['issues'][$id] = $issue->getJsonData();
                }
            }
        }
        return $dbResults;
    }

    /**
     * Serializes an object of type issue and adds it to array.
     * @param Issue $issue 
     */
    public static function addIssue($issue)
    {
        self::$results[$issue->getExtension()][$issue->getCheckname()]['issues'][] = $issue;
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
    
    /**
     * Gets all issues by type.
     * 
     * @param String $check The name of the check.
     * @param String $type The type of the issue.
     * @return array 
     */
    public static function getIssuesByType($check, $type)
    {
        $result = array();
        
        foreach (self::$results[$check]['issues'] as $issue )
        {
            $issueObject = unserialize($issue);
            if ($issueObject->getType() == $type)
            {
                $result[] = $issueObject;
            }
        }
        
        return $result;
    }
    
    /**
     * Counts issues of check.
     * 
     * @param String $check
     * @return int 
     */
    public static function countIssuesByCheck($check)
    {
        return count(self::$results[$check]['issues']);
    }
    
    /**
     * Counts issues with specified type.
     * 
     * @param String $check
     * @param String $type
     * @return int 
     */
    public static function countIssuesByType($check, $type)
    {
        $result = 0;
        
        foreach (self::$results[$check]['issues'] as $issue )
        {
            $issueObject = unserialize($issue);
            if ($issueObject->getType() == $type)
            {
                $result++;
            }
        }
        
        return $result;
    }
}