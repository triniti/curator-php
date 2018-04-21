<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UnpublishTimelineV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\NodeUnpublished\NodeUnpublished;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UnpublishTimelineHandler;

final class UnpublishTimelineHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $node = TimelineV1::create();
        $slug = 'great-awesome-timeline';
        $node->set('slug', $slug)->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($node);

        $this->ncr->putNode($node);
        $command = UnpublishTimelineV1::create()->set('node_ref', $nodeRef);

        $handler = new UnpublishTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedId = $nodeRef->getId();

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedId, $slug) {
                $this->assertInstanceOf(NodeUnpublished::class, $event);
                $this->assertSame(StreamId::fromString("timeline.history:{$expectedId}")->toString(), $streamId->toString());
                $this->assertSame($slug, $event->get('slug'));
            }
        );
    }
}