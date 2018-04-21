<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\WidgetDeletedV1;
use Acme\Schemas\Curator\Event\WidgetUpdatedV1;
use Acme\Schemas\Curator\Node\CarouselWidgetV1;
use Acme\Schemas\Curator\Request\GetWidgetHistoryRequestV1;
use Acme\Schemas\Curator\Request\GetWidgetHistoryResponseV1;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetWidgetHistoryRequestHandler;

final class GetWidgetHistoryRequestHandlerTest extends AbstractPbjxTest
{
    public function testHandleRequest(): void
    {
        $streamId = StreamId::fromString('carousel-widget.history:1234');
        $nodeRef = NodeRef::fromString('acme:carousel-widget:1234');
        $expectedEvents = [
            WidgetDeletedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetWidgetHistoryRequestV1::create()->set('stream_id', $streamId);

        /** @var GetWidgetHistoryResponseV1 $response */
        $response = (new GetWidgetHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events', []);

        $this->assertFalse($response->get('has_more'));
        $this->assertEquals(1, count($actualEvents));

        /** @var Message $actualEvent */
        foreach (array_reverse($actualEvents) as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }

    public function testHandleRequestWithCount(): void
    {
        $oldNode = CarouselWidgetV1::create()
            ->set('title', 'old-test-title');

        $newNode = CarouselWidgetV1::create()
            ->set('_id', $oldNode->get('_id'))
            ->set('title', 'new-test-title');

        $streamId = StreamId::fromString('carousel-widget.history:1234');
        $nodeRef = NodeRef::fromString('acme:carousel-widget:1234');
        $expectedEvents = [
            WidgetUpdatedV1::create()->set('node_ref', $nodeRef)
                ->set('new_node', $newNode)
                ->set('node_ref', $nodeRef),
            WidgetUpdatedV1::create()->set('node_ref', $nodeRef)
                ->set('new_node', $newNode)
                ->set('node_ref', $nodeRef),
            WidgetDeletedV1::create()->set('node_ref', $nodeRef),
            WidgetDeletedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetWidgetHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('count', 3)
            ->set('forward', true);

        /** @var GetWidgetHistoryResponseV1 $response */

        $response = (new GetWidgetHistoryRequestHandler())->handleRequest($request, $this->pbjx);
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
        $oldNode = CarouselWidgetV1::create()
            ->set('title', 'old-test-title');

        $newNode = CarouselWidgetV1::create()
            ->set('_id', $oldNode->get('_id'))
            ->set('title', 'new-test-title');

        $streamId = StreamId::fromString('carousel-widget.history:1234');
        $nodeRef = NodeRef::fromString('acme:carousel-widget:1234');

        $expectedEvents = [
            WidgetUpdatedV1::create()->set('old_node', $oldNode)
                ->set('new_node', $newNode)
                ->set('node_ref', $nodeRef),
            WidgetDeletedV1::create()->set('node_ref', $nodeRef),
        ];

        $this->pbjx->getEventStore()->putEvents($streamId, $expectedEvents);
        $request = GetWidgetHistoryRequestV1::create()
            ->set('stream_id', $streamId)
            ->set('forward', true);

        /** @var GetWidgetHistoryResponseV1 $response */
        $response = (new GetWidgetHistoryRequestHandler())->handleRequest($request, $this->pbjx);
        $actualEvents = $response->get('events');

        /** @var Message $actualEvent */
        foreach ($actualEvents as $key => $actualEvent) {
            $this->assertTrue($actualEvent->equals($expectedEvents[$key]));
        }
    }
}