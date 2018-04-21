<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\PublishTimelineV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Schemas\Ncr\Mixin\NodePublished\NodePublished;
use Gdbots\Schemas\Ncr\Mixin\NodeScheduled\NodeScheduled;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\PublishTimelineHandler;

final class PublishTimelineHandlerTest extends AbstractPbjxTest
{
    public function testWithNoDate(): void
    {
        $node = TimelineV1::create()->set('slug', 'published-timeline-test');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = PublishTimelineV1::create()
            ->set('node_ref', $nodeRef);

        $handler = new PublishTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($command) {
            $this->assertInstanceOf(NodePublished::class, $event);
            $expectedId = $command->get('node_ref')->getId();
            $this->assertSame(
                StreamId::fromString("timeline.history:{$expectedId}")->toString(),
                $streamId->toString()
            );

            $this->assertSame(
                $command->get('node_ref')->toString(),
                $event->get('node_ref')->toString()
            );

            $this->assertSame(
                $command->get('occurred_at')->toDateTime()->format('U'),
                $event->get('published_at')->format('U')
            );

            $this->assertSame('published-timeline-test', $event->get('slug'));
        });
    }

    public function testWithinAnticipationThreshold(): void
    {
        $node = TimelineV1::create()->set('slug', 'published-timeline-test');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = PublishTimelineV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', new \DateTime('+15 seconds'));

        $handler = new PublishTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($command) {
            $this->assertInstanceOf(NodePublished::class, $event);
            $expectedId = $command->get('node_ref')->getId();
            $this->assertSame(
                StreamId::fromString("timeline.history:{$expectedId}")->toString(),
                $streamId->toString()
            );

            $this->assertSame(
                $command->get('node_ref')->toString(),
                $event->get('node_ref')->toString()
            );

            $this->assertSame($command->get('publish_at'), $event->get('published_at'));
            $this->assertSame('published-timeline-test', $event->get('slug'));
        });
    }

    public function testWithFutureDate(): void
    {
        $node = TimelineV1::create()->set('slug', 'scheduled-timeline-test');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = PublishTimelineV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', new \DateTime('+16 seconds'));

        $handler = new PublishTimelineHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($command) {
            $this->assertInstanceOf(NodeScheduled::class, $event);
            $expectedId = $command->get('node_ref')->getId();
            $this->assertSame(
                StreamId::fromString("timeline.history:{$expectedId}")->toString(),
                $streamId->toString()
            );

            $this->assertSame(
                $command->get('node_ref')->toString(),
                $event->get('node_ref')->toString()
            );

            $this->assertSame($command->get('publish_at'), $event->get('publish_at'));
            $this->assertSame('scheduled-timeline-test', $event->get('slug'));
        });
    }
}