<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\CreateTimelineV1;
use Acme\Schemas\Curator\Event\TimelineCreatedV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\CreateTimelineHandler;

final class CreateTimelineHandlerTest extends AbstractPbjxTest
{

    public function testHandleCommand(): void
    {
        $title = 'test-timeline';

        $node = TimelineV1::create()
            ->set('title', $title);

        $command = CreateTimelineV1::create()
            ->set('node', $node);

        $expectedEvent = TimelineCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateTimelineHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("timeline.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }
}