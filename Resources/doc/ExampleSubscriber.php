<?php
namespace RedEyeApps\MyBundle\Listener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use RedEyeApps\MyBundle\Entity\Documents;

class SearchIndexerSubscriber implements EventSubscriber
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function getSubscribedEvents()
    {
        return array(
            'postPersist',
            'postUpdate',
            'postRemove'
        );
    }

    /* Event Handlers */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->index($args, 'add');
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->index($args, 'add');
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $this->index($args, 'remove');
    }

    public function index(LifecycleEventArgs $args, $action = '')
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        //Check if entity is of type we need to index
        if ($entity instanceof Document) {

                // Index this entity 
                $cloudsearchindexer = $this->container->get('cloudsearchindexer');
               
                if($action != 'remove') {
                    // Add or Update index.
                    $result = $cloudsearchindexer->indexDocuments(array($entity->getSearchFields()), 'myindexname', $action);
                    
                } else if($action == 'remove') {
                    //Remove from index.
                    $result = $cloudsearchindexer->indexDocuments(array($entity->getSearchFields()), 'myindexname', 'delete');
                }
        }
    }
}