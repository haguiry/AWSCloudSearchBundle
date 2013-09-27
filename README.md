AWSCloudSearchBundle
====================

This bundle is designed to make it easier to integrate Amazon Cloud Search with Symfony2 projects to index entities regardless of database implementation.


1) Installation
---------------

Install PHP Curl module:
    yum install php-curl

Add to composer.json:


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
To index documents you need to create a JSON array of documents match the AWC Cloud Search fields format you configured in the AWS console.

To index changes it is recommended you us an event subscriber to doctrine persist events and index changes to entities on the fly.
Example to come.

To do your initial indexing of entities it is recommend you use a Symfony2 command.
Example to come.

4) Search Usage
--------------



