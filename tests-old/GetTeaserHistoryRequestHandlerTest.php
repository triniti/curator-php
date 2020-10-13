<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\TeaserExpiredV1;
use Acme\Schemas\Curator\Event\TeaserPublishedV1;
use Acme\Schemas\Curator\Event\TeaserScheduledV1;
use Acme\Schemas\Curator\Event\TeaserUnpublishedV1;
use Acme\Schemas\Curator\Request\GetTeaserHistoryRequestV1;
use Acme\Schemas\Curator\Request\GetTeaserHistoryResponseV1;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetTeaserHistoryRequestHandler;

final class GetTeaserHistoryRequestHandlerTest extends AbstractPbjxTest
{
    public function testHandleRequest(): void
    {
        $streamId = StreamId::fromString('category-teaser.history:1234');
        $nodeRef = NodeRef::fromString('acme:category-teaser:1234');

        $expectedEvents = [
            TeaserPublishedV1::create()->set('node_ref', $nodeRef),
            TeaserUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetTeaserHistoryRequestV1::create()->set('stream_id', $streamId);

        /** @var GetTeaserHistoryResponseV1 $response */
        $response = (new GetTeaserHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $streamId = StreamId::fromString('gallery-teaser.history:1234');
        $nodeRef = NodeRef::fromString('acme:gallery-teaser:1234');

        $expectedEvents = [
            TeaserScheduledV1::create()->set('node_ref', $nodeRef),
            TeaserPublishedV1::create()->set('node_ref', $nodeRef),
            TeaserUnpublishedV1::create()->set('node_ref', $nodeRef),
            TeaserExpiredV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetTeaserHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('count', 3)
            ->set('forward', true);

        /** @var GetTeaserHistoryResponseV1 $response */
        $response = (new GetTeaserHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $streamId = StreamId::fromString('channel-teaser.history:1234');
        $nodeRef = NodeRef::fromString('acme:channel-teaser:1234');

        $expectedEvents = [
            TeaserPublishedV1::create()->set('node_ref', $nodeRef),
            TeaserUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetTeaserHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('forward', true);

        /** @var GetTeaserHistoryResponseV1 $response */
        $response = (new GetTeaserHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');

        /** @var Message $actualEvent */
        foreach ($actualEvents as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }
}