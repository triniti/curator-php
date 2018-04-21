<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\DeleteWidgetV1;
use Acme\Schemas\Curator\Node\CarouselWidgetV1;
use Gdbots\Schemas\Ncr\Mixin\NodeDeleted\NodeDeleted;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\DeleteWidgetHandler;

final class DeleteWidgetHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = CarouselWidgetV1::create()
            ->set('title', 'test-widget');

        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = DeleteWidgetV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new DeleteWidgetHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeDeleted::class, $event);
            $this->assertSame(StreamId::fromString("carousel-widget.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}