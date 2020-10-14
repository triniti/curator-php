<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Pbjx;

class MockPbjx implements Pbjx
{
    public function trigger(Message $message, string $suffix, ?PbjxEvent $event = null, bool $recursive = true): Pbjx
    {
    }

    public function triggerLifecycle(Message $message, bool $recursive = true): Pbjx
    {
    }

    public function copyContext(Message $from, Message $to): Pbjx
    {
    }

    public function send(Message $command): void
    {
    }

    public function sendAt(Message $command, int $timestamp, ?string $jobId = null, array $context = []): string
    {
    }

    public function cancelJobs(array $jobIds, array $context = []): void
    {
    }

    public function publish(Message $event): void
    {
    }

    public function request(Message $request): Message
    {
    }

    public function getEventStore(): EventStore
    {
    }

    public function getEventSearch(): EventSearch
    {
    }
}
