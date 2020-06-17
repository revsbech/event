<?php
namespace Ag\Event\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 */
class EventHandlerCommandController extends CommandController {

	/**
	 * @Flow\InjectConfiguration(path="eventHandlers")
	 */
	protected $eventHandlersConfiguration = array();

	/**
	 * Lists all configured event handlers
	 */
	public function listCommand() {
		$tableData = array();

		foreach ($this->eventHandlersConfiguration as $syncType => $configuration) {
			foreach ($configuration as $implementationClassName => $enabledStatus) {
				$tableData[] = array(
					$implementationClassName . PHP_EOL . '  Key: <b>' . str_replace('\\', '_', $implementationClassName) . '</b>',
					$syncType,
					$enabledStatus ? 'TRUE' : 'FALSE'
				);
			}
		}

		$this->output->outputTable($tableData, array('Implementation class / key', 'Type', 'Enabled?'));
	}

}
