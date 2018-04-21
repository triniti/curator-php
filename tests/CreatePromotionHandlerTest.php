<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\CreatePromotionV1;
use Acme\Schemas\Curator\Event\PromotionCreatedV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\CreatePromotionHandler;

final class CreatePromotionHandlerTest extends AbstractPbjxTest
{

    public function testHandleCommand(): void
    {
        $testGalleryTitle = 'test-gallery';

        $node = PromotionV1::create()
            ->set('title', $testGalleryTitle);

        $command = CreatePromotionV1::create()
            ->set('node', $node);

        $expectedEvent = PromotionCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreatePromotionHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $testGalleryTitle) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($testGalleryTitle, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("promotion.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }
}