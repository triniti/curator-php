<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\ExpireGalleryV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Schemas\Ncr\Mixin\NodeExpired\NodeExpired;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\ExpireGalleryHandler;

final class ExpireGalleryHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($node);
        $slug = 'great-awesome-gallery';
        $node->set('slug', $slug);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = ExpireGalleryV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new ExpireGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId, $slug) {
            $this->assertInstanceOf(NodeExpired::class, $event);
            $this->assertSame(StreamId::fromString("gallery.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($slug, $event->get('slug'));
        });
    }
}