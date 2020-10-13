<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

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

    public function send(Command $command): void
    {
    }

    public function sendAt(Command $command, int $timestamp, ?string $jobId = null): string
    {
    }

    public function cancelJobs(array $jobIds): void
    {
    }

    public function publish(Event $event): void
    {
    }

    public function request(Request $request): Response
    {
    }

    public function getEventStore(): EventStore
    {
    }

    public function getEventSearch(): EventSearch
    {
    }
}
