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
class CloudSearchClient {

	protected $container;

	/* Left hardcoded for now as if API version changes all code 
	would need testing anyway. */
    protected $apiversion = "2011-02-01";
    protected $indexes;

    private $indexname = '';
    private $filters = array();
    private $searchfields = array();
    private $returnfields = array();
    private $sorts = array();
    private $offset = 0;
    private $limit = 25;
    private $matchmode = 'normal';

    /**
    *	Constructor gets configured index settings for config.yml.
    */
	public function __construct($container)
	{
		$this->container = $container;
		$this->indexes = $this->container->getParameter('aws_cloud_search.indexes');

	}

	/**
	* Set index to be searched. Must match index name used in config.
	* @param $indexname string - Name of index in config.
	*/
	public function setIndex($indexname) {
		return $this->indexname = $indexname;
	}

	/**
	* Add a field to boolean query to filter.
	* @param $fieldname string - Name of field.
	* @param $filter string - Type of filter as per AWS Cloud Search doco possible values: and, or
	* @param $arrayvalues - Array of value to filter results with.
	*/
	public function addFilter($fieldname, $filtertype, $arrayvalues) {
		$filter = new \StdClass;
		$filter->name = $fieldname;
		$filter->type = $filtertype;
		$filter->values = $arrayvalues;
		return $this->filters[] = $filter;
	}

	/**
	* Add a field to search.
	* @param $fieldname string - Name of field.
	*/
	public function addSearchField($fieldname) {
		return $this->searchfields[] = $fieldname;
	}

	/**
	* Add a field to list of fields to return.
	* @param $fieldname string - Name of field.
	*/
	public function addReturnField($fieldname) {
		return $this->returnfields[] = $fieldname;
	}

	/**
	* Add a field to sort order of results.
	* @param $name string - Name of sort field or expression predefined in AWS console.
	* @param $order string - Order to sort this field etiher ASC or DESC
	* Note: If using field to sort it must be enabled as a result field in AWS console.
	*/
	public function addSort($name, $order = 'ASC') {
		$sort = new \StdClass;
		$sort->name = $name;
		$sort->order = $order;
		return $this->sorts[] = $sort;
	}

	/**
	* Set search results offset
	* @param $offset integer
	*/
	public function setOffset($offset) {
		return $this->offset = $offset;
	}

	/**
	* Set search results length
	* @param $length integer
	*/
	public function setLimit($limit) {
		return $this->limit = $limit;
	}

	/**
	* Set search matchmode 
	* @param $matchmode - Match mode, one of: normal, exact, startswith, endswith, any
	*/
	public function setMatchMode($matchmode) {
		return $this->matchmode = $matchmode;
	}

	/**
	* Index and Array of documents object. Objects must match AWS Cloud Search fields format and
	* have unique integer property called id.
	*
	* @param searchterm - string search term to use.
	*/
	public function search($searchterm = '') {

		//Get configuration for specified index.
		if(isset($this->indexes[$this->indexname])){

			$indexconfig = $this->indexes[$this->indexname];

			//Construct query string for fields to search with given term.
			$searchfieldsstr = $this->searchFieldsQuery($this->searchfields, $searchterm, $this->matchmode);

			//Construct query string for filters.
			$filtersstr = $this->filterFieldsQuery($this->filters);	

			//Construct sorting/ranking query string.
			$sortstr = $this->sortQuery($this->sorts);	

			//Construct overall query string.
			$searchstr = '';
			if(strlen($searchfieldsstr) > 0 && strlen($filtersstr) > 0){
				//Create query using search fields and filter fields
				$searchstr .= "(and (or " . $searchfieldsstr.") ";
				$searchstr .= " " . $filtersstr . " )";

			} elseif(strlen($searchfieldsstr) > 0 && strlen($filtersstr) == 0) {
				//Only use search fields
				$searchstr .= "(or " . $searchfieldsstr.")";

			} 

			$searchurl = $indexconfig['search_endpoint']."/".$this->apiversion."/"."search?bq=".urlencode($searchstr);

			//Set return fields
			$returnfieldsstr = $this->returnFieldsQuery($this->returnfields);
			$searchurl .= $returnfieldsstr;

			//Set number results to return, and offset.
	
			$searchurl .= "&size=".$this->limit;
			$searchurl .= "&start=".$this->offset;
			$searchurl .= $sortstr;

			//Do the search
			$results = json_decode($this->get($searchurl));

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
	* @param filterfields - Array of fields defined using addFilter
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
			$fieldstr = ' (' . $field->type . ' ';

			//Add each value for field to filter.
			foreach($field->values as $value) {
				//Add field to field query string
				$fieldstr .= $field->name . ":" . $value . " ";
				$fieldvalues++;
			}

			if($fieldvalues >= 1) {
				$filterstring .= $fieldstr . ')';	
			}
		}

		return $filterstring;
	}

   /**
   * Generates query string to match Cloud Search API for sorting.
   * @param sortfields - Array of sort fields/expressions specified with addSortField.
   * NOTE: Multiple sorts are applied in sortfields array order. 
   */
   private function sortQuery($sorts) {
   		$sortstring = '';
   		$sortcount = 0;

   		foreach($sorts as $sort) {
   			$sortstr = '';

   			if(isset($sort->name)){
   				$sortstr .= $sort->name;

   				//Set Order (default is ASC)
   				if(isset($sort->order) && $sort->order == 'DESC') {
   					$sortstr = '-'.$sortstr;
   				}

   				//Comma seperate		
   				if($sortcount >= 1) {
   					$sortstring .= ',';
   				}

   				$sortstring .= $sortstr;
   				$sortcount++;
   			}
   		}

   		if($sortcount > 0) {
   			$sortstring = '&rank='.$sortstring;
   		}

   		return $sortstring;
   }

   /**
   * Generates query string to match Cloud Search API for fields to return from index.
   * @param searchfields - Array of field fieldnames to return in results.
   */
   private function returnFieldsQuery($returnfields) {

   		$returnfieldstring = '';
   		$fieldcount = 0;
   		foreach($returnfields as $fieldname) {
   				if($fieldcount >= 1) {
   					$returnfieldstring .= ',';
   				}
   				$returnfieldstring .= $fieldname;
   				$fieldcount++;
   		}

   		if($fieldcount >= 1) {
   			$returnfields = '&return-fields='.$returnfieldstring;
   		}

   		return $returnfields;
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
