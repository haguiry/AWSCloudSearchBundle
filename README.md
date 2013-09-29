AWSCloudSearchBundle
====================

This bundle is designed to make it easier to integrate Amazon Cloud Search with Symfony2 projects to index entities regardless of database implementation.

Full AWS Cloud Search API Doco available at:
http://docs.aws.amazon.com/cloudsearch/latest/developerguide/SvcIntro.html

1) Installation
---------------
Install PHP Curl module:

        yum install php-curl

Add to composer.json:

        "redeyeapps/awscloudsearchbundle" : "dev-master"

2) Configuration
---------------
In your Symfony2 projects config.yml you needs to configure you AWS indexes. 
doc_endpoint and search_endpoint can be copied directly for AWS Cloud Search console, remember to change protocol to https for ssl encryption.

Example Configuration:

	aws_cloud_search: 
    indexes: 
        index1 :
            doc_endpoint: https://doc-index1.us-west-1.cloudsearch.amazonaws.com
            search_endpoint: https://search-index1.cloudsearch.amazonaws.com
            lang: en
        index2 :
            doc_endpoint: https://doc-index2.us-west-1.cloudsearch.amazonaws.com
            search_endpoint: https://search-index2-test.cloudsearch.amazonaws.com
            lang: en

Also remember to setup your AWS Cloud Search access rules to allow indexing and searching from appropriate ip's. 

3) Indexer Usage
--------------
To index documents you need to create a JSON array of documents that match the AWC Cloud Search fields format you configured in the AWS console and post it to Cloud Search.

To index changes to entities (adds/updates/removes) it is recommended you us an event subscriber to doctrine persist events and index changes to entities on the fly.

To create a subscriber see:
http://symfony.com/doc/current/cookbook/doctrine/event_listeners_subscribers.html

For full example subscriber see Resources/doc/ExampleSubscriber.php
        
To do initial indexing of entities it is recommend you use a Symfony2 command.
For full example subscriber see Resources/doc/ExampleIndexCommand.php

## Notes on Converting Entity to Json
There are a couple of approaches, one is to use this bundle:
http://jmsyst.com/bundles/JMSSerializerBundle/master/installation

This bundle is pretty complex to setup and adds a few extra depenacies. In our case we have created a simple function on the entities we need to index called getSearchFields() which manually converts the entity to an object that that matches the fields configured for our indexes. This is then json encoded and passed to the indexer service.

Example:

        public function getSearchFields() {
            $obj = new \StdClass;
            $obj->id = $this->getId();
            $number = $this->getNumber();

            //Be careful with null fields
            if($code == null) {
                $number = '';
            }
            $obj->number = $number;

            $title = $this->getTitle();
            if($title == null) {
                $title = '';
            }
            $obj->title = $title;

            //Groups Array
            $groups = array();
            foreach($this->getGroups() as $group) {
                $groups[] = $group->getId();
            }

            if(count($groups) > 0){
                $obj->groups = $groups;
            }

            return $obj;
        }

This matches an index with the fields:

        id : uint
        number : uint
        title : text
        groups : uint

4) Search Usage
--------------
Searching using the CloudSearchClient service is pretty straight forward (the only required setting is setIndex):

        // Create search client
        $cloudsearcher = $this->get('cloudsearchclient');

        //Specify index to search. Must match indexname in config.yml
        $cloudsearcher->setIndex('redeyevms');

        //Set text fields to search with search term
        $cloudsearcher->addSearchField('title');
        $cloudsearcher->addSearchField('desc');

        //Set fields to recieve in results
        $cloudsearcher->addReturnField('title');

        //Set a sort field
        $cloudsearcher->addSort('title', 'ASC');

        //Set offsets and result limit, useful for paging.
        $cloudsearcher->setOffset($offset);
        $cloudsearcher->setLimit($resultlength);

        // Match mode, one of: normal, exact, startswith, endswith, any. Defaults to normal.
        $cloudsearcher->setMatchMode('startswith');

        //Add a filter to search
        $cloudsearcher->addFilter('genre', 'or', array('horror', 'sci-fi')); //Genre can be either horror or sci-fi.

        //Do search with string as search term
        $results = $cloudsearcher->search('star wars');      


Format of results is documented here:
http://docs.aws.amazon.com/cloudsearch/latest/developerguide/Search.Response.html



