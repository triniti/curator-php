<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\PromotionExpiredV1;
use Acme\Schemas\Curator\Event\PromotionPublishedV1;
use Acme\Schemas\Curator\Event\PromotionScheduledV1;
use Acme\Schemas\Curator\Event\PromotionUnpublishedV1;
use Acme\Schemas\Curator\Request\GetPromotionHistoryRequestV1;
use Acme\Schemas\Curator\Request\GetPromotionHistoryResponseV1;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetPromotionHistoryRequestHandler;

final class GetPromotionHistoryRequestHandlerTest extends AbstractPbjxTest
{
    public function testHandleRequest(): void
    {
        $streamId = StreamId::fromString('promotion.history:1234');
        $nodeRef = NodeRef::fromString('acme:promotion:1234');

        $expectedEvents = [
            PromotionPublishedV1::create()->set('node_ref', $nodeRef),
            PromotionUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetPromotionHistoryRequestV1::create()->set('stream_id', $streamId);

        /** @var GetPromotionHistoryResponseV1 $response */
        $response = (new GetPromotionHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $streamId = StreamId::fromString('promotion.history:1234');
        $nodeRef = NodeRef::fromString('acme:promotion:1234');

        $expectedEvents = [
            PromotionScheduledV1::create()->set('node_ref', $nodeRef),
            PromotionPublishedV1::create()->set('node_ref', $nodeRef),
            PromotionUnpublishedV1::create()->set('node_ref', $nodeRef),
            PromotionExpiredV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetPromotionHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('count', 3)
            ->set('forward', true);

        /** @var GetPromotionHistoryResponseV1 $response */
        $response = (new GetPromotionHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $streamId = StreamId::fromString('promotion.history:1234');
        $nodeRef = NodeRef::fromString('acme:promotion:1234');

        $expectedEvents = [
            PromotionPublishedV1::create()->set('node_ref', $nodeRef),
            PromotionUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetPromotionHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('forward', true);

        /** @var GetPromotionHistoryResponseV1 $response */
        $response = (new GetPromotionHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');

        /** @var Message $actualEvent */
        foreach ($actualEvents as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }
}