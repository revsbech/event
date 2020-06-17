<?php
namespace Ag\Event\Domain\Repository;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class StoredEventRepository extends \Neos\Flow\Persistence\Repository {

	/**
	 * @param string $eventId
	 * @return \Ag\Event\Domain\Model\StoredEvent
	 */
	public function findByIdentifier($eventId) {
		return parent::findByIdentifier($eventId);
	}

	/**
	 * @return \Ag\Event\Domain\Model\StoredEvent
	 */
	public function getLatestEvent() {
		$query = $this->createQuery();
		return $query
			->setOrderings(array('eventId'=>\Neos\Flow\Persistence\QueryInterface::ORDER_DESCENDING))
			->setLimit(1)
			->execute()
			->getFirst();
	}

	/**
	 * @param \Ag\Event\Domain\Model\StoredEvent $event
	 * @return \Ag\Event\Domain\Model\StoredEvent
	 */
	public function findNext($event) {
		$eventId = $event !== NULL ? $event->getEventId() : '0';

		$query = $this->createQuery();
		return $query
			->matching($query->greaterThan('eventId',$eventId))
			->setOrderings(array('eventId'=>\Neos\Flow\Persistence\QueryInterface::ORDER_ASCENDING))
			->setLimit(1)
			->execute()
			->getFirst();
	}

}
?>
