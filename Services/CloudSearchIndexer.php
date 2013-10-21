<?php
namespace  RedEyeApps\AWSCloudSearchBundle\Services;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
* AWS CloudSearch Indexer 
* Integrates with Amazon Cloud Search API to add JSON documents which 
* represent entities to Cloud Search indexes.
*/
class CloudSearchIndexer {

	protected $container;

	// AWS Cloud Search API Version
    protected $apiversion = "2011-02-01";
    protected $indexes;

    /**
    *	Constructor gets configured indexes settings.
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
	* @param documentsjson - json string of document objects.
	* @param indexname - string that matches configured index.
	* @param action - string Cloud Search API action to be carried out on these documents.
	*/
	public function indexDocuments($documentsjson, $indexname, $action){
		$documentobjs = json_decode($documentsjson);

		//Get configuration for specified index.
		if(isset($this->indexes[$indexname])){
			$indexconfig = $this->indexes[$indexname];

			//Create batch for Cloud Search
			$documents = array();
			foreach($documentobjs as $documentobj) {

				//Create document and add to batch
				$documents[] = $this->createDocument($documentobj, $action, $indexconfig);

			}

			$documents = json_encode($documents);

			//Post batch to Cloud Search	
			$result = $this->post($indexconfig['doc_endpoint'].'/'.$this->apiversion.'/documents/batch', $documents);

			if(is_object($result)){
				$result = json_decode($result);
		
				if($result->status == 'success') {
					return 'Success, total adds: '. $result->adds. ' & total deletes: ' . $result->deletes;

				} else {
					return $result->status;
				}
			} else {
				//HTTP type error such as 404, 503
				return $result;	
			}

		} else {
			return 'Index is not configured.';
		}
	}

	/**
	* Creates document object that matches AWS Cloud Search API
	* @param documentobj - Object that represents fields for entity in Cloud Search index.
	* @param action - String Cloud Search action to be applied to this docuemnt (add or delete)
	* @param indexconfig - Array index configuration
	*/
	private function createDocument($documentobj, $action, $indexconfig) {

		//Create Cloud Search Json Document
		$document = new \StdClass;
		$document->type = $action;
		$id = $documentobj->id;
		if(isset($indexconfig['id_prefix'])){
			$id = $indexconfig['id_prefix'].$id;
		}
		$document->id = $id;

		//Auto incerment version using unix timestamp.
		$version = time();
	
		$document->version = $version;
		$document->lang = $indexconfig['lang'];

		//Set fields for this document.
		$document->fields = $documentobj;

		return $document;
	}

   /**
   * Uses PHP Curl to POST requests to AWS Cloud Search API
   * @param url - String URL of Cloud Search docuement endpoint to POST to. 
   * @param batch - String JSON of AWS Cloud Search API batch. 
   */
    private function post($url, $batch) {
        
        $curl2 = curl_init();

		$contentlength = strlen($batch);

	    curl_setopt($curl2, CURLOPT_POST, true);
	    curl_setopt($curl2, CURLOPT_POSTFIELDS, $batch);
	    curl_setopt($curl2, CURLOPT_HTTPHEADER, array(                                                                          
	        'Content-Type: application/json',                                                                                
	        'Content-Length: ' . $contentlength)                                                                   
	    );   
        curl_setopt($curl2, CURLOPT_URL, $url);
        curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
  
        $result = curl_exec($curl2);
        
        $HttpCode = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
        
        $this->http_code = (int)$HttpCode;

        return $result;
    }  
}
