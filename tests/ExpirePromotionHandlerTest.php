<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\ExpirePromotionV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Gdbots\Schemas\Ncr\Mixin\NodeExpired\NodeExpired;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\ExpirePromotionHandler;

final class ExpirePromotionHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = ExpirePromotionV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new ExpirePromotionHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeExpired::class, $event);
            $this->assertSame(StreamId::fromString("promotion.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}