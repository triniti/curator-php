<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\MarkPromotionAsDraftV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsDraft\NodeMarkedAsDraft;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\MarkPromotionAsDraftHandler;

final class MarkPromotionAsDraftHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($node);
        $node->set('status', NodeStatus::PENDING());
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = MarkPromotionAsDraftV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new MarkPromotionAsDraftHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeMarkedAsDraft::class, $event);
            $this->assertSame(StreamId::fromString("promotion.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}