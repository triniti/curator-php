<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UpdateWidgetV1;
use Acme\Schemas\Curator\Event\WidgetUpdatedV1;
use Acme\Schemas\Curator\Node\CarouselWidgetV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UpdateWidgetHandler;

final class UpdateWidgetHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $oldNode = CarouselWidgetV1::create()
            ->set('title', 'old-test-title');

        $this->ncr->putNode($oldNode);

        $newNode = CarouselWidgetV1::create()
            ->set('_id', $oldNode->get('_id'))
            ->set('title', 'new-test-title');

        $command = UpdateWidgetV1::create()
            ->set('node_ref', NodeRef::fromNode($oldNode))
            ->set('old_node', $oldNode)
            ->set('new_node', $newNode);

        $handler = new UpdateWidgetHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedEvent = WidgetUpdatedV1::create();
        $expectedId = $oldNode->get('_id');

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedEvent, $expectedId) {
                $this->assertSame($event::schema(), $expectedEvent::schema());
                $this->assertTrue($event->has('old_node'));
                $this->assertTrue($event->has('new_node'));

                $newNodeFromEvent = $event->get('new_node');

                $this->assertSame(NodeStatus::PUBLISHED, (string)$newNodeFromEvent->get('status'));
                $this->assertSame(StreamId::fromString("carousel-widget.history:{$expectedId}")->toString(), $streamId->toString());
                $this->assertSame($event->generateMessageRef()->toString(), (string)$newNodeFromEvent->get('last_event_ref'));
            }
        );
    }
}
