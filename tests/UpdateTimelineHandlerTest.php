<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UpdateTimelineV1;
use Acme\Schemas\Curator\Event\TimelineUpdatedV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UpdateTimelineHandler;

final class UpdateTimelineHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $oldNode = TimelineV1::create()->set('slug', 'first-timeline');
        $this->ncr->putNode($oldNode);

        $newNode = TimelineV1::create()
            ->set('_id', $oldNode->get('_id'))
            ->set('slug', 'updated-first-timeline');

        $command = UpdateTimelineV1::create()
            ->set('node_ref', NodeRef::fromNode($oldNode))
            ->set('old_node', $oldNode)
            ->set('new_node', $newNode);

        $handler = new UpdateTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedEvent = TimelineUpdatedV1::create();
        $expectedId = $oldNode->get('_id');
        $expectedSlug = $oldNode->get('slug');

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedEvent, $expectedId, $expectedSlug) {
                $this->assertSame($event::schema(), $expectedEvent::schema());
                $this->assertTrue($event->has('old_node'));
                $this->assertTrue($event->has('new_node'));

                $newNodeFromEvent = $event->get('new_node');

                $this->assertSame(NodeStatus::DRAFT, (string)$newNodeFromEvent->get('status'));
                $this->assertEquals($expectedSlug, $newNodeFromEvent->get('slug'));
                $this->assertSame(StreamId::fromString("timeline.history:{$expectedId}")->toString(), $streamId->toString());
                $this->assertSame($event->generateMessageRef()->toString(), (string)$newNodeFromEvent->get('last_event_ref'));
            }
        );
    }
}