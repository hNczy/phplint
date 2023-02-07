<?php

declare(strict_types=1);

namespace Overtrue\PHPLint\Extension;

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Configuration\FileOptionsResolver;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Event\AfterCheckingEvent;
use Overtrue\PHPLint\Event\AfterCheckingInterface;
use Overtrue\PHPLint\Output\ChainOutput;
use Overtrue\PHPLint\Output\ConsoleOutput;
use Overtrue\PHPLint\Output\JsonOutput;
use Overtrue\PHPLint\Output\JunitOutput;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Laurent Laville
 */
class OutputFormat implements EventSubscriberInterface, AfterCheckingInterface
{
    private array $outputOptions;
    private array $handlers = [];

    public function __construct(array $outputOptions = [])
    {
        $this->outputOptions = $outputOptions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'initFormat',
        ];
    }

    public function initFormat(ConsoleCommandEvent $event): void
    {
        $command= $event->getCommand();
        if (!$command instanceof LintCommand) {
            // this extension must be only available for lint command
            return;
        }

        $input = $event->getInput();

        if (true === $input->hasParameterOption(['--no-configuration'], true)) {
            $configResolver = new ConsoleOptionsResolver($input, $command->getDefinition());
        } else {
            $configResolver = new FileOptionsResolver($input, $command->getDefinition());
        }

        foreach ($this->outputOptions as $name) {
            if ($filename = $configResolver->getOption($name)) {
                if (OptionDefinition::OPTION_JSON_FILE == $name) {
                    $this->handlers[] = new JsonOutput(fopen($filename, 'w'));
                } elseif (OptionDefinition::OPTION_JUNIT_FILE == $name) {
                    $this->handlers[] = new JunitOutput(fopen($filename, 'w'));
                }
            }
        }

        /** @var ConsoleOutput $consoleOutput */
        $consoleOutput = $event->getOutput();
        $consoleOutput->setApplicationVersion($event->getCommand()->getApplication()->getLongVersion());
        $consoleOutput->setConfigResolver($configResolver);

        $this->handlers[] = $consoleOutput;
    }

    public function afterChecking(AfterCheckingEvent $event): void
    {
        $outputHandler = new ChainOutput($this->handlers);
        $outputHandler->format($event->getArgument('results'));
    }
}
