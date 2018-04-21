<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\RenameTimelineV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Ncr\Mixin\NodeRenamed\NodeRenamed;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\RenameTimelineHandler;

final class RenameTimelineHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = TimelineV1::create()->set('slug', 'great-awesome-timeline');
        $this->ncr->putNode($node);
        $nodeRef = NodeRef::fromNode($node);

        $expectedId = $nodeRef->getId();

        $command = RenameTimelineV1::create();
        $command->set('node_ref', $nodeRef);
        $command->set('new_slug', 'great-renamed-timeline');
        $command->set('old_slug', $node->get('slug'));

        $handler = new RenameTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeRenamed::class, $event);
            $this->assertSame(StreamId::fromString("timeline.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame('great-renamed-timeline', $event->get('new_slug'));
            $this->assertSame('great-awesome-timeline', $event->get('old_slug'));
        });
    }

    public function testSlugNotChanged(): void
    {
        $node = TimelineV1::create()->set('slug', 'great-awesome-timeline');
        $this->ncr->putNode($node);

        $command = RenameTimelineV1::create();
        $command->set('node_ref', NodeRef::fromNode($node));
        $command->set('new_slug', 'great-awesome-timeline');
        $command->set('old_slug', $node->get('slug'));

        $handler = new RenameTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $callbackIsCalled = false;

        $this->eventStore->pipeAllEvents(function () use (&$callbackIsCalled) {
            $callbackIsCalled = true;
        });

        $this->assertFalse($callbackIsCalled, 'Failed asserting that no event was created if old and new slugs are the same.');
    }
}