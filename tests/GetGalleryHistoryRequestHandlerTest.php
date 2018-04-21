<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\GalleryExpiredV1;
use Acme\Schemas\Curator\Event\GalleryPublishedV1;
use Acme\Schemas\Curator\Event\GalleryScheduledV1;
use Acme\Schemas\Curator\Event\GalleryUnpublishedV1;
use Acme\Schemas\Curator\Request\GetGalleryHistoryRequestV1;
use Acme\Schemas\Curator\Request\GetGalleryHistoryResponseV1;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetGalleryHistoryRequestHandler;

final class GetGalleryHistoryRequestHandlerTest extends AbstractPbjxTest
{
    public function testHandleRequest(): void
    {
        $streamId = StreamId::fromString('gallery.history:1234');
        $nodeRef = NodeRef::fromString('acme:gallery:1234');

        $expectedEvents = [
            GalleryPublishedV1::create()->set('node_ref', $nodeRef),
            GalleryUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetGalleryHistoryRequestV1::create()->set('stream_id', $streamId);

        /** @var GetGalleryHistoryResponseV1 $response */
        $response = (new GetGalleryHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');
        $this->assertFalse($response->get('has_more'));
        $this->assertEquals(count($expectedEvents), count($actualEvents));

        /** @var Message $actualEvent */
        foreach (array_reverse($actualEvents) as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }

    public function testHandleRequestWithCount(): void
    {
        $streamId = StreamId::fromString('gallery.history:1234');
        $nodeRef = NodeRef::fromString('acme:gallery:1234');

        $expectedEvents = [
            GalleryScheduledV1::create()->set('node_ref', $nodeRef),
            GalleryPublishedV1::create()->set('node_ref', $nodeRef),
            GalleryUnpublishedV1::create()->set('node_ref', $nodeRef),
            GalleryExpiredV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetGalleryHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('count', 3)
            ->set('forward', true);

        /** @var GetGalleryHistoryResponseV1 $response */
        $response = (new GetGalleryHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');
        $this->assertTrue($response->get('has_more'));
        $this->assertEquals(3, count($actualEvents));

        /** @var Message $actualEvent */
        foreach ($actualEvents as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }

    public function testHandleRequestWithForward(): void
    {
        $streamId = StreamId::fromString('gallery.history:1234');
        $nodeRef = NodeRef::fromString('acme:gallery:1234');

        $expectedEvents = [
            GalleryPublishedV1::create()->set('node_ref', $nodeRef),
            GalleryUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetGalleryHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('forward', true);

        /** @var GetGalleryHistoryResponseV1 $response */
        $response = (new GetGalleryHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');

        /** @var Message $actualEvent */
        foreach ($actualEvents as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }
}