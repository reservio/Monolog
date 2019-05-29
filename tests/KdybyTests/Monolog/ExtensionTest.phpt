<?php

/**
 * Test: Kdyby\Monolog\Extension.
 *
 * @testCase
 */

namespace KdybyTests\Monolog;

use Kdyby\Monolog\DI\MonologExtension;
use Kdyby\Monolog\Logger as MonologLogger;
use Kdyby\Monolog\Processor\PriorityProcessor;
use Kdyby\Monolog\Processor\TracyExceptionProcessor;
use Kdyby\Monolog\Processor\TracyUrlProcessor;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\NewRelicHandler;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use Nette\Configurator;
use Tester\Assert;
use Tracy\Debugger;

require_once __DIR__ . '/../bootstrap.php';

class ExtensionTest extends \Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer($configName = NULL)
	{
		$config = new Configurator();
		$config->setTempDirectory(TEMP_DIR);
		MonologExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');

		if ($configName !== NULL) {
			$config->addConfig(__DIR__ . '/config/' . $configName . '.neon');
		}

		return $config->createContainer();
	}

	public function testServices()
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('monolog.logger') instanceof MonologLogger);
	}

	public function testFunctional()
	{
		foreach (array_merge(glob(TEMP_DIR . '/*.log'), glob(TEMP_DIR . '/*.html')) as $logFile) {
			unlink($logFile);
		}

		Debugger::$logDirectory = TEMP_DIR;

		$dic = $this->createContainer();
		/** @var \Kdyby\Monolog\Logger $logger */
		$logger = $dic->getByType(MonologLogger::class);

		Debugger::log('tracy message 1');
		Debugger::log('tracy message 2', 'error');

		Debugger::log(new \Exception('tracy exception message 1'), 'error');
		Debugger::log(new \Exception('tracy exception message 2'));

		$logger->addInfo('logger message 1');
		$logger->addInfo('logger message 2', ['channel' => 'custom']);

		$logger->addError('logger message 3');
		$logger->addError('logger message 4', ['channel' => 'custom']);

		$logger->addWarning('exception message 1', ['exception' => new \Exception('exception message 1')]);

		$logger->addDebug('logger message 5');
		$logger->addDebug('logger message 6', ['channel' => 'custom']);

		$logger->addNotice('logger message 7');
		$logger->addNotice('logger message 8', ['channel' => 'custom']);

		$logger->addCritical('logger message 9');
		$logger->addCritical('logger message 10', ['channel' => 'custom']);

		$logger->addAlert('logger message 11');
		$logger->addAlert('logger message 12', ['channel' => 'custom']);

		$logger->addEmergency('logger message 13');
		$logger->addEmergency('logger message 14', ['channel' => 'custom']);

		$logger->warn('exception message 2', ['exception' => new \Exception('exception message 2')]);
		$logger->warn('logger message 16', ['channel' => 'custom']);

		$logger->err('logger message 17');
		$logger->err('logger message 18', ['channel' => 'custom']);

		$logger->crit('logger message 19');
		$logger->crit('logger message 20', ['channel' => 'custom']);

		$logger->emerg('logger message 21');
		$logger->emerg('logger message 22', ['channel' => 'custom']);

		Assert::match(
			'[%a%] tracy message 1 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 2 in %a%:%d% {"at":"%a%","exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []' . "\n" .
			'[%a%] logger message 1 [] []',
			file_get_contents(TEMP_DIR . '/info.log')
		);

		Assert::match(
			'[%a%] exception message 1 {"exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []' . "\n" .
			'[%a%] exception message 2 {"exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []',
			file_get_contents(TEMP_DIR . '/warning.log')
		);

		Assert::match(
			'[%a%] tracy message 2 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 1 in %a%:%d% {"at":"%a%","exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} []' . "\n" .
			'[%a%] logger message 3 [] []' . "\n" .
			'[%a%] logger message 17 [] []',
			file_get_contents(TEMP_DIR . '/error.log')
		);

		Assert::match(
			'[%a%] INFO: logger message 2 [] []' . "\n" .
			'[%a%] ERROR: logger message 4 [] []' . "\n" .
			'[%a%] DEBUG: logger message 6 [] []' . "\n" .
			'[%a%] NOTICE: logger message 8 [] []' . "\n" .
			'[%a%] CRITICAL: logger message 10 [] []' . "\n" .
			'[%a%] ALERT: logger message 12 [] []' . "\n" .
			'[%a%] EMERGENCY: logger message 14 [] []' . "\n" .
			'[%a%] WARNING: logger message 16 [] []' . "\n" .
			'[%a%] ERROR: logger message 18 [] []' . "\n" .
			'[%a%] CRITICAL: logger message 20 [] []' . "\n" .
			'[%a%] EMERGENCY: logger message 22 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/custom.log')
		);

		Assert::match(
			'[%a%] logger message 5 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/debug.log')
		);

		Assert::match(
			'[%a%] logger message 7 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/notice.log')
		);

		Assert::match(
			'[%a%] logger message 9 [] []' . "\n" .
			'[%a%] logger message 19 [] []',
			file_get_contents(TEMP_DIR . '/critical.log')
		);

		Assert::match(
			'[%a%] logger message 11 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/alert.log')
		);

		Assert::match(
			'[%a%] logger message 13 [] []' . "\n" .
			'[%a%] logger message 21 [] []' . "\n",
			file_get_contents(TEMP_DIR . '/emergency.log')
		);

		Assert::count(4, glob(TEMP_DIR . '/exception-*.html'));

		$channel = $logger->channel('test');
		Assert::type('Kdyby\Monolog\CustomChannel', $channel);
		Assert::match('test', $channel->getName());
	}

	public function testHandlersSorting()
	{
		$dic = $this->createContainer('handlers');
		$logger = $dic->getByType(MonologLogger::class);
		$handlers = $logger->getHandlers();
		Assert::count(3, $handlers);
		Assert::type(NewRelicHandler::class, array_shift($handlers));
		Assert::type(ChromePHPHandler::class, array_shift($handlers));
		Assert::type(BrowserConsoleHandler::class, array_shift($handlers));
	}

	public function testProcessorsSorting()
	{
		$dic = $this->createContainer('processors');
		$logger = $dic->getByType(MonologLogger::class);
		$processors = $logger->getProcessors();
		Assert::count(6, $processors);
		Assert::type(TracyExceptionProcessor::class, array_shift($processors));
		Assert::type(PriorityProcessor::class, array_shift($processors));
		Assert::type(TracyUrlProcessor::class, array_shift($processors));
		Assert::type(WebProcessor::class, array_shift($processors));
		Assert::type(ProcessIdProcessor::class, array_shift($processors));
		Assert::type(GitProcessor::class, array_shift($processors));
	}

}

(new ExtensionTest())->run();
