<?php
namespace  RedEyeApps\AWSCloudSearchBundle\Services;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
* AWS CloudSearch Searcher 
* Integrates with Amazon Cloud Search API to search indexes.
*/
class CloudSearcher {

	protected $container;

	/* Left hardcoded for now as if API version changes all code 
	would need testing anyway. */
    protected $apiversion = "2011-02-01";
    protected $indexes;

    /**
    *	Constructor gets configured index settings for config.yml.
    */
	public function __construct($container)
	{
		$this->container = $container;
		$this->indexes = $this->container->getParameter('aws_cloud_search.indexes');
	}

	/**
	* Index and Array of documents object. Objects must match AWS Cloud Search fields format and
	* have unique integer property called id.
	*
	* @param indexname - string that matches configured index.
	* @param searchterm - string search term to use.
	* @param string - Match mode, one of: normal, exact, startswith, endswith, any
	* @param integer - Number of results to return.
	* @param integer - Results offset.
	* @param array - Array of string field names to search with given search term.
	* @param filterfields - Array of fields objects to filter search query by example:
	*					  array( Object('fieldname' => 'groups', 'type' => 'and', 'values' => array(60, 1, 2, 3)))
	* @param sortfields - Array of field objects to sort search results by in order of importance. Example:
	*					  array(
	*								Object('fieldname' => 'title', 'order' => 'DESC'), 
	*								Object('fieldname' => 'author', 'order' => 'ASC')
	*					  );
	*/
	public function search($indexname, $searchterm = '', $matchmode = 'normal', $length = 25, $offset = 0, 
						   $searchfields = array(), $filterfields = array(), $sortfields = array()) {

		$indexname = 'redeye_test';

		//Get configuration for specified index.
		if(isset($this->indexes[$indexname])){

			$indexconfig = $this->indexes[$indexname];

			//Construct query string for fields to search with given term.
			$searchfieldsstr = $this->searchFieldsQuery($searchfields, $searchterm, $matchmode);

			//Construct query string for filters.
			$filtersstr = $this->filterFieldsQuery($filterfields);	
		
			//Construct sorting/ranking query string.
			//$sortstr = $this->sortQuery($sortfields);	

			//Construct overall query string.
			$searchstr = '';
			if(strlen($searchfieldsstr) > 0 && strlen($filtersstr) > 0){
				//Create query using search fields and filter fields
				$searchstr .= "(and (or " . $searchfieldsstr.") ";
				$searchstr .= "(" . $filtersstr . "))";

			} elseif(strlen($searchfieldsstr) > 0 && strlen($filtersstr) == 0) {
				//Only use search fields
				$searchstr .= "(or " . $searchfieldsstr.")";

			} 

			$searchurl = $indexconfig['search_endpoint']."/".$this->apiversion."/"."search?bq=".urlencode($searchstr);

			//Set number results to return, and offset.
			$searchurl .= "&size=".$length;
			$searchstr .= "&start=".$offset;
			
			//Do the search
			$results = $this->get($searchurl);

			return $results;
		} else {
			return 'Specified index not configured.';
		}
	}

	/**
	* Creates search query string to search files.
	* @param array - Array of string field names.
	* @param string - Search term.
	* @param string - Match mode, one of: normal, exact, startswith, endswith, any
	*/
	private function searchFieldsQuery($searchfields, $searchterm, $matchmode) {

		//Set Defaults
		$searchfieldsstr = '';
		$searchfieldcount = 0;
		$startswith = '';
		$endswith = '';
		$quote = "'";

		//Change it based on match mode
		switch ($matchmode) {
		    case 'exact':
		        $quote = '"';	
		        break;
		    case 'startswith':
		        $startswith = '*';
		        break;
		    case 'endswith':
		        $endswith = '*';
		        break;
		    case 'any':
		        $endswith = '*';
		       	$startswith = '*';
		    default:	
		    	//Normal search with no match mode set
		}

		//Create search query for each field specified.
		foreach ($searchfields as $searchfield) {
				$searchfieldsstr .= $searchfield . ':' . $quote . $endswith . $searchterm . $startswith .$quote . " ";
				$searchfieldcount++;
		}

		return $searchfieldsstr;
	}

	/**
	* Creates boolean query to filter results with.
	* @param filterfields - Array of fields objects to filter search query by example:
	*					    array( Object('fieldname' => 'groups', 'type' => 'and', 'values' => array(60, 1, 2, 3))
	* NOTE: Order matters for example if filtering by two fields, one as and, and the other as or query result
	* will be different based on order in array. 
	*/
	private function filterFieldsQuery($filterfields) {
		
		$filterstring = '';
		$currentfiltertype = '';

		//Set filters for each field
		$currentfiltertype = '';
		foreach ($filterfields as $field) {
			//Get field settings
			$fieldstr = '';
			$fieldvalues = 0;
			$fieldfiltertype = $field->type;

			//Add each value for field to filter.
			foreach($field->values as $value) {

				if($fieldfiltertype != $currentfiltertype){
					//Change filter type for this field
					$currentfiltertype = $field->type;
					$fieldstr .= $currentfiltertype . ' ';
				} 

				//Add field to field query string
				$fieldstr .= $field->name . ":" . $value . " ";
				$fieldvalues++;
			}

			if($fieldvalues > 0) {
				$filterstring .= $fieldstr;	
			}
		}

		return $filterstring;
	}

   /**
   * Generates query string to match Cloud Search API for sorting.
   * @param sortfields - Array of field objects with fieldname and order (ASC or DESC).
   * NOTE: Multiple sorts are applied in sortfields array order. 
   */
   private function sortQuery($sortfields) {
   		$sortstring = '';
   		$sortfieldcount = 0;

   		foreach($sortfields as $sortfieldobj) {
   			$sortfield = '';

   			if(isset($sortfieldobj->name)){
   				$sortfield = $sortfieldobj->name;

   				//Set Order
   				if(isset($sortfieldobj->order) && $sortfieldobj->order == 'ASC') {
   					$sortfield = '-'.$sortfield;
   				}
   			
   				if($sortfieldcount >= 1) {
   					$sortstring .= ',';
   				}

   				$sortstring .= $sortfield;
   				$sortfieldcount++;
   			}
   		}

   		if($sortfieldcount > 0) {
   			$sortstring = '&rank='.$sortstring;
   		}

   		return $sortstring;
   }

   /**
   * Uses PHP Curl to make GET requests to AWS Cloud Search API
   * @param url - String URL of Cloud Search search url with query paramters. 
   */
    private function get($url) {
        
        $curl2 = curl_init();

        curl_setopt($curl2, CURLOPT_URL, $url);
        curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
  
        $result = curl_exec($curl2);
        
        $HttpCode = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
        
        $this->http_code = (int)$HttpCode;

        return $result;
    }  
}
