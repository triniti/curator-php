<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\DeleteTimelineV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Ncr\Mixin\NodeDeleted\NodeDeleted;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\DeleteTimelineHandler;

final class DeleteTimelineHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = TimelineV1::create()->set('slug', 'great-awesome-timeline');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = DeleteTimelineV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new DeleteTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeDeleted::class, $event);
            $this->assertSame(StreamId::fromString("timeline.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame('great-awesome-timeline', $event->get('slug'));
        });
    }
}