<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\MarkTimelineAsPendingV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsPending\NodeMarkedAsPendingV1;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\MarkTimelineAsPendingHandler;

final class MarkTimelineAsPendingHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($node);
        $slug = 'great-awesome-timeline';
        $node->set('slug', $slug);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = MarkTimelineAsPendingV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new MarkTimelineAsPendingHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId, $slug) {
            $this->assertInstanceOf(NodeMarkedAsPendingV1::class, $event);
            $this->assertSame(StreamId::fromString("timeline.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($slug, $event->get('slug'));
        });
    }
}