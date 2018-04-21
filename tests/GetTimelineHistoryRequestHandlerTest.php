<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\TimelineExpiredV1;
use Acme\Schemas\Curator\Event\TimelinePublishedV1;
use Acme\Schemas\Curator\Event\TimelineScheduledV1;
use Acme\Schemas\Curator\Event\TimelineUnpublishedV1;
use Acme\Schemas\Curator\Request\GetTimelineHistoryRequestV1;
use Acme\Schemas\Curator\Request\GetTimelineHistoryResponseV1;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetTimelineHistoryRequestHandler;

final class GetTimelineHistoryRequestHandlerTest extends AbstractPbjxTest
{
    public function testHandleRequest(): void
    {
        $streamId = StreamId::fromString('timeline.history:1234');
        $nodeRef = NodeRef::fromString('acme:timeline:1234');

        $expectedEvents = [
            TimelinePublishedV1::create()->set('node_ref', $nodeRef),
            TimelineUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetTimelineHistoryRequestV1::create()->set('stream_id', $streamId);

        /** @var GetTimelineHistoryResponseV1 $response */
        $response = (new GetTimelineHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $streamId = StreamId::fromString('timeline.history:1234');
        $nodeRef = NodeRef::fromString('acme:timeline:1234');

        $expectedEvents = [
            TimelineScheduledV1::create()->set('node_ref', $nodeRef),
            TimelinePublishedV1::create()->set('node_ref', $nodeRef),
            TimelineUnpublishedV1::create()->set('node_ref', $nodeRef),
            TimelineExpiredV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetTimelineHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('count', 3)
            ->set('forward', true);

        /** @var GetTimelineHistoryResponseV1 $response */
        $response = (new GetTimelineHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $streamId = StreamId::fromString('timeline.history:1234');
        $nodeRef = NodeRef::fromString('acme:timeline:1234');

        $expectedEvents = [
            TimelinePublishedV1::create()->set('node_ref', $nodeRef),
            TimelineUnpublishedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetTimelineHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('forward', true);

        /** @var GetTimelineHistoryResponseV1 $response */
        $response = (new GetTimelineHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');

        /** @var Message $actualEvent */
        foreach ($actualEvents as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }
}