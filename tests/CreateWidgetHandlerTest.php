<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\CreateWidgetV1;
use Acme\Schemas\Curator\Event\WidgetCreatedV1;
use Acme\Schemas\Curator\Node\CarouselWidgetV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\CreateWidgetHandler;

final class CreateWidgetHandlerTest extends AbstractPbjxTest
{

    public function testHandleCommand(): void
    {
        $title = 'test-gallery';

        $node = CarouselWidgetV1::create()
            ->set('title', $title);

        $command = CreateWidgetV1::create()
            ->set('node', $node);

        $expectedEvent = WidgetCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateWidgetHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(NodeStatus::PUBLISHED, (string)$actualNode->get('status'));
            $this->assertSame(StreamId::fromString("carousel-widget.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }
}
