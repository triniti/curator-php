<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UpdateGalleryImageCountV1;
use Acme\Schemas\Curator\Node\GalleryV1;;

use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UpdateGalleryImageCountHandler;
use Triniti\Schemas\Curator\Event\GalleryImageCountUpdatedV1;

final class UpdateGalleryImageCountHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = GalleryV1::create()->set('image_count', 20);
        $nodeRef = $node->generateNodeRef();
        $this->ncr->putNode($node);
        $command = UpdateGalleryImageCountV1::create()->set('node_ref', $nodeRef);
        $handler = new UpdateGalleryImageCountHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        foreach ($this->eventStore->pipeAllEvents() as [$event, $streamId]) {
            $this->assertInstanceOf(GalleryImageCountUpdatedV1::class, $event);
            $this->assertSame($event->get('node_ref'), $nodeRef);
            $this->assertSame(0, $event->get('image_count'));
            $this->assertTrue(StreamId::fromString("acme:gallery:{$nodeRef->getId()}")->equals($streamId));
        }
    }

    public function testHandleCommandWithMatchingCount(): void
    {
        $node = GalleryV1::create()->set('image_count', 0);
        $nodeRef = $node->generateNodeRef();
        $this->ncr->putNode($node);
        $command = UpdateGalleryImageCountV1::create()->set('node_ref', $nodeRef);
        $handler = new UpdateGalleryImageCountHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $eventCount = 0;
        foreach ($this->eventStore->pipeAllEvents() as $yield) {
            $eventCount ++;
        }
        $this->assertSame(0, $eventCount);
    }
}
