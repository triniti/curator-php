<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\GalleryImageCountUpdatedV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UpdateGalleryImageCountHandler;
use Triniti\Schemas\Curator\Mixin\UpdateGalleryImageCount\UpdateGalleryImageCountV1Mixin;
use Triniti\Schemas\Dam\Mixin\SearchAssetsRequest\SearchAssetsRequestV1Mixin;

final class UpdateGalleryImageCountHandlerTest extends AbstractPbjxTest
{
    public function setup()
    {
        parent::setup();

        // prepare request handlers that this test case requires
        PbjxEvent::setPbjx($this->pbjx);
        $mockAssetSearch = new MockNcrAssetSearch();
        $mockAssetSearch->setAssetCount(2);
        $this->locator->registerRequestHandler(
            SearchAssetsRequestV1Mixin::findOne()->getCurie(),
            new SearchAssetsRequestHandler($mockAssetSearch)
        );
    }

    public function testHandle(): void
    {
        $gallery = GalleryV1::create()
            ->set('slug', 'test-gallery')
            ->set('image_count', 10);
        $this->ncr->putNode($gallery);

        $nodeRef = $gallery->get('_id')->toNodeRef();
        $command = UpdateGalleryImageCountV1Mixin::findOne()->createMessage()
            ->set('node_ref', $nodeRef);

        $handler = new UpdateGalleryImageCountHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedEvent = GalleryImageCountUpdatedV1::create();
        $expectedId = $gallery->get('_id');
        $expectedCount = 2;

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedEvent, $expectedId, $expectedCount) {
                $this->assertSame($event::schema(), $expectedEvent::schema());
                $this->assertTrue($event->has('node_ref'));
                $this->assertTrue($event->has('image_count'));

                $this->assertEquals($expectedId, $event->get('node_ref')->getId());
                $this->assertEquals($expectedCount, $event->get('image_count'));
                $this->assertSame(StreamId::fromString("gallery.history:{$expectedId}")->toString(), $streamId->toString());
            }
        );
    }

    public function testHandleWithMatchingCount(): void
    {
        $gallery = GalleryV1::create()
            ->set('slug', 'test-gallery')
            ->set('image_count', 2);
        $this->ncr->putNode($gallery);

        $nodeRef = $gallery->get('_id')->toNodeRef();
        $command = UpdateGalleryImageCountV1Mixin::findOne()->createMessage()
            ->set('node_ref', $nodeRef);

        $handler = new UpdateGalleryImageCountHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedEvent = GalleryImageCountUpdatedV1::create();
        $expectedId = $gallery->get('_id');
        $expectedCount = 2;

        $count = 0;
        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedEvent, $expectedId, $expectedCount, &$count) {
                $count++;
                $this->assertSame($event::schema(), $expectedEvent::schema());
                $this->assertTrue($event->has('node_ref'));
                $this->assertTrue($event->has('image_count'));

                $this->assertEquals($expectedId, $event->get('node_ref')->getId());
                $this->assertEquals($expectedCount, $event->get('image_count'));
                $this->assertSame(StreamId::fromString("gallery.history:{$expectedId}")->toString(), $streamId->toString());
            }
        );
        $this->assertEquals(0, $count, 'No event should be created if the gallery image_count matches the number of assets.');
    }
}
