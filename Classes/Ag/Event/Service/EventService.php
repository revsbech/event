<?php
namespace Ag\Event\Service;

use Ag\Event\Domain\Model\DomainEvent;
use Ag\Event\Domain\Model\StoredEvent;
use Ag\Event\Domain\Repository\StoredEventRepository;
use Ag\Event\EventHandler\EventHandler;
use Ag\Event\Exception\EventHandlingException;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Psr\Log\LoggerInterface;
use Neos\Utility\TypeHandling;

/**
 * @Flow\Scope("singleton")
 */
class EventService {

	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var StoredEventRepository
	 */
	protected $storedEventRepository;

	/**
	 * @Flow\Inject
	 * @var LoggerInterface
	 */
	protected $systemLogger;

	/**
	 * @Flow\Inject
	 * @var ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @Flow\Inject
	 * @var ReflectionService
	 */
	protected $reflectionService;

	/**
	 * Note: this dependency injection is backed by Objects.yaml!
	 *
	 * @Flow\Inject
	 * @var Pheanstalk
	 */
	protected $pheanstalk;

	/**
	 * @Flow\InjectConfiguration(path="eventHandlers")
	 * @var array
	 */
	protected $eventHandlersConfiguration;

	/**
	 * @var array
	 */
	protected $events = array();

	/**
	 * @var boolean
	 */
	protected $logging = TRUE;

	/**
	 * @param DomainEvent $event
	 */
	public function publish(DomainEvent $event) {

		if($this->logging) {
			//$this->systemLogger->log('Publish event ' . $this->reflectionService->getClassNameByObject($event), LOG_DEBUG);
            //$this->systemLogger->log(LOG_DEBUG, 'Publish event ' . TypeHandling::getTypeForValue($event));
		}

		$event = new StoredEvent($event);
		$this->persistenceManager->whitelistObject($event);
		$this->storedEventRepository->add($event);

		$this->events[] = $event;

	}

	/**
	 * @param PostFlushEventArgs $eventArgs
	 * @return void
	 */
	public function postFlush(PostFlushEventArgs $eventArgs) {
		$events = $this->events;
		$this->events = array();
		foreach ($events as $event) {
			foreach ($this->eventHandlersConfiguration['async'] as $eventHandlerClassName => $enabled) {
				if ($enabled !== FALSE) {
					$this->_asyncPublish($event, $eventHandlerClassName);
				}
			}

			foreach ($this->eventHandlersConfiguration['sync'] as $eventHandlerClassName => $enabled) {
				if ($enabled !== FALSE) {
					$this->_syncPublish($event, $eventHandlerClassName);
				}
			}
		}
	}

	/**
	 * @param DomainEvent $event
	 * @param $eventHandlerClassName
	 */
	public function handleDomainEventByEventHandler(DomainEvent $event, $eventHandlerClassName) {
		$eventHandlerInstance = $this->objectManager->get($eventHandlerClassName);

		if (!$eventHandlerInstance instanceof EventHandler) {
			$this->systemLogger->log(sprintf('Event handler "%s" does not implement the event handler interface.', $eventHandlerClassName), LOG_CRIT, array(
				'event' => serialize($event)
			));
			return;
		}

		try {
			$eventHandlerInstance->handle($event);
		} catch (\Exception $caughtException) {
			$wrappedException = new EventHandlingException('Event could not be handled.', 1403853171, $caughtException);
			/*
			$this->systemLogger->error($wrappedException, array(
				'event' => serialize($event)
			));
			/**/
		}
	}

	/**
	 * @param StoredEvent $event
	 * @param string $eventHandlerClassName
	 */
	protected function _syncPublish(StoredEvent $event, $eventHandlerClassName) {
		if($this->logging) {
			$this->systemLogger->debug(sprintf('Synchronously publishing event #%s to "%s"', $event->getEventId(), $eventHandlerClassName));
		}

		$this->handleDomainEventByEventHandler($event->getEvent(), $eventHandlerClassName);
	}

	/**
	 * @param StoredEvent $storedEvent
	 * @param string $key
	 */
	protected function _asyncPublish(StoredEvent $storedEvent, $key) {
		$key = str_replace('\\', '_', $key);

		$this->pheanstalk->useTube($key)->put(serialize($storedEvent), PheanstalkInterface::DEFAULT_PRIORITY, 1);

		if($this->logging) {
			$this->systemLogger->info(sprintf('Asynchronously published event #%s to tube "%s"', $storedEvent->getEventId(), $key));
		}
	}
}
