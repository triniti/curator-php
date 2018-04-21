<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\CreateGalleryV1;
use Acme\Schemas\Curator\Event\GalleryCreatedV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\CreateGalleryHandler;

final class CreateGalleryHandlerTest extends AbstractPbjxTest
{

    public function testHandleCommand(): void
    {
        $testGalleryTitle = 'test-gallery';

        $node = GalleryV1::create()
            ->set('title', $testGalleryTitle);

        $command = CreateGalleryV1::create()
            ->set('node', $node);

        $expectedEvent = GalleryCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateGalleryHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $testGalleryTitle) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($testGalleryTitle, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("gallery.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }
}