<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Monolog;

use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ContaoTableHandler extends AbstractProcessingHandler implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private string $dbalServiceName = 'doctrine.dbal.default_connection';

    public function getDbalServiceName(): string
    {
        return $this->dbalServiceName;
    }

    public function setDbalServiceName(string $name): void
    {
        $this->dbalServiceName = $name;
    }

    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);
        $record['formatted'] = $this->getFormatter()->format($record);

        if (!isset($record['extra']['contao']) || !$record['extra']['contao'] instanceof ContaoContext) {
            return false;
        }

        try {
            $this->write($record);
        } catch (\Exception $e) {
            return false;
        }

        $this->executeHook($record['message'], $record['extra']['contao']);

        return false === $this->bubble;
    }

    protected function write(array $record): void
    {
        /** @var \DateTime $date */
        $date = $record['datetime'];

        /** @var ContaoContext $context */
        $context = $record['extra']['contao'];

        $this->getConnection()->insert('tl_log', [
            'tstamp' => $date->format('U'),
            'text' => StringUtil::specialchars((string) $record['formatted']),
            'source' => (string) $context->getSource(),
            'action' => (string) $context->getAction(),
            'username' => (string) $context->getUsername(),
            'func' => $context->getFunc(),
            'browser' => StringUtil::specialchars((string) $context->getBrowser()),
            'uri' => StringUtil::specialchars($context->getUri() ?? ''),
            'page' => $context->getPageId() ?? 0,
        ]);
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('%message%');
    }

    private function getConnection(): Connection
    {
        if (null === $this->container || !$this->container->has($this->dbalServiceName)) {
            throw new \RuntimeException('The container has not been injected or the database service is missing');
        }

        return $this->container->get($this->dbalServiceName);
    }

    /**
     * Executes the legacy hook if the Contao framework is booted.
     */
    private function executeHook(string $message, ContaoContext $context): void
    {
        if (null === $this->container || !$this->container->has('contao.framework')) {
            return;
        }

        $framework = $this->container->get('contao.framework');

        if (!$this->hasAddLogEntryHook() || !$framework->isInitialized()) {
            return;
        }

        trigger_deprecation('contao/core-bundle', '4.0', 'Using the "addLogEntry" hook has been deprecated and will no longer work in Contao 5.0.');

        $system = $framework->getAdapter(System::class);

        // Must create variables to allow modification-by-reference in hook
        $func = $context->getFunc();
        $action = $context->getAction();

        foreach ($GLOBALS['TL_HOOKS']['addLogEntry'] as $callback) {
            $system->importStatic($callback[0])->{$callback[1]}($message, $func, $action);
        }
    }

    private function hasAddLogEntryHook(): bool
    {
        return !empty($GLOBALS['TL_HOOKS']['addLogEntry']) && \is_array($GLOBALS['TL_HOOKS']['addLogEntry']);
    }
}
