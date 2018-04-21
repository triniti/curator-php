<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\DeleteGalleryV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Schemas\Ncr\Mixin\NodeDeleted\NodeDeleted;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\DeleteGalleryHandler;

final class DeleteGalleryHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = GalleryV1::create()->set('slug', 'great-awesome-gallery');
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = DeleteGalleryV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new DeleteGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeDeleted::class, $event);
            $this->assertSame(StreamId::fromString("gallery.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame('great-awesome-gallery', $event->get('slug'));
        });
    }
}