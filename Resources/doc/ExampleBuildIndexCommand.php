<?php
namespace RedEyeApps\MyBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Guzzle\Http\Client;

use RedEyeApps\MyBundle\Entity\Document;

class ExampleBuildIndexCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this
		->setName('mybundle:rebuildDocumentIndex')
		->setDescription('Builds documents search index.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln('<comment>Indexing All Drawings</comment>');
		$em = $this->getContainer()->get('doctrine')->getManager();

		//Get all drawings to index.
		$documents = $em->getRepository('MyBundle:Document')->findAll();

		$cloudsearchindexer = $this->getContainer()->get('cloudsearchindexer');

        $documents_arr = array();
        $batchcount = 0;
        $indexcount = 0;

		foreach ($documents as $document) {

			$indexcount++;
			$documents_arr[] = $document->getSearchFields();

			//Cloud Search batches restricted to 5 meg so check size
			$batchsize = (strlen(json_encode($documents_arr)) / 1024); //returns kb

			if($batchsize > 4900){
				//Send this batch and start new batch.
				$result = $cloudsearchindexer->indexDocument($documents_arr, 'myindexname', 'add');
				$output->writeln($result);
				$documents_arr = array();
				$batchcount++;
			}
		}

		if(count($documents_arr) > 0){
			$result = $cloudsearchindexer->indexDocument($documents_arr, 'myindexname', 'add');
			$output->writeln($result);
			$batchcount++;
		}
		
		$output->writeln('<comment>Total Index Batches: </comment>' . $batchcount);
		$output->writeln('<comment>Total Documents Indexed: </comment>' . $indexcount);
	}
}