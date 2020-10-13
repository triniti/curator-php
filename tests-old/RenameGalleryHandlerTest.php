<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\RenameGalleryV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Schemas\Ncr\Mixin\NodeRenamed\NodeRenamed;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\RenameGalleryHandler;

final class RenameGalleryHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = GalleryV1::create()->set('slug', 'great-awesome-gallery');
        $this->ncr->putNode($node);
        $nodeRef = NodeRef::fromNode($node);

        $expectedId = $nodeRef->getId();

        $command = RenameGalleryV1::create();
        $command->set('node_ref', $nodeRef);
        $command->set('new_slug', 'renamed-gallery-name');
        $command->set('old_slug', $node->get('slug'));

        $handler = new RenameGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeRenamed::class, $event);
            $this->assertSame(StreamId::fromString("gallery.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame('renamed-gallery-name', $event->get('new_slug'));
            $this->assertSame('great-awesome-gallery', $event->get('old_slug'));
        });
    }

    public function testSlugNotChanged(): void
    {
        $node = GalleryV1::create()->set('slug', 'great-awesome-gallery');
        $this->ncr->putNode($node);

        $command = RenameGalleryV1::create();
        $command->set('node_ref', NodeRef::fromNode($node));
        $command->set('new_slug', 'great-awesome-gallery');
        $command->set('old_slug', $node->get('slug'));

        $handler = new RenameGalleryHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $callbackIsCalled = false;

        $this->eventStore->pipeAllEvents(function () use (&$callbackIsCalled) {
            $callbackIsCalled = true;
        });

        $this->assertFalse($callbackIsCalled, 'Failed asserting that no event was created if old and new slugs are the same.');
    }
}