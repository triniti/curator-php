<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\PublishGalleryV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Schemas\Ncr\Mixin\NodePublished\NodePublished;
use Gdbots\Schemas\Ncr\Mixin\NodeScheduled\NodeScheduled;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\PublishGalleryHandler;

final class PublishGalleryHandlerTest extends AbstractPbjxTest
{
    public function testWithNoDate(): void
    {
        $node = GalleryV1::create()->set('slug', 'published-gallery-test');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = PublishGalleryV1::create()
            ->set('node_ref', $nodeRef);

        $handler = new PublishGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($command) {
            $this->assertInstanceOf(NodePublished::class, $event);
            $expectedId = $command->get('node_ref')->getId();
            $this->assertSame(
                StreamId::fromString("gallery.history:{$expectedId}")->toString(),
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

            $this->assertSame('published-gallery-test', $event->get('slug'));
        });
    }

    public function testWithinAnticipationThreshold(): void
    {
        $node = GalleryV1::create()->set('slug', 'published-gallery-test');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = PublishGalleryV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', new \DateTime('+15 seconds'));

        $handler = new PublishGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($command) {
            $this->assertInstanceOf(NodePublished::class, $event);
            $expectedId = $command->get('node_ref')->getId();
            $this->assertSame(
                StreamId::fromString("gallery.history:{$expectedId}")->toString(),
                $streamId->toString()
            );

            $this->assertSame(
                $command->get('node_ref')->toString(),
                $event->get('node_ref')->toString()
            );

            $this->assertSame($command->get('publish_at'), $event->get('published_at'));
            $this->assertSame('published-gallery-test', $event->get('slug'));
        });
    }

    public function testWithFutureDate(): void
    {
        $node = GalleryV1::create()->set('slug', 'scheduled-gallery-test');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = PublishGalleryV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', new \DateTime('+16 seconds'));

        $handler = new PublishGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($command) {
            $this->assertInstanceOf(NodeScheduled::class, $event);
            $expectedId = $command->get('node_ref')->getId();
            $this->assertSame(
                StreamId::fromString("gallery.history:{$expectedId}")->toString(),
                $streamId->toString()
            );

            $this->assertSame(
                $command->get('node_ref')->toString(),
                $event->get('node_ref')->toString()
            );

            $this->assertSame($command->get('publish_at'), $event->get('publish_at'));
            $this->assertSame('scheduled-gallery-test', $event->get('slug'));
        });
    }
}