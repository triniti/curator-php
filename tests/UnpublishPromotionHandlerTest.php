<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UnpublishPromotionV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\NodeUnpublished\NodeUnpublished;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UnpublishPromotionHandler;

final class UnpublishPromotionHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $node = PromotionV1::create();
        $node->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = UnpublishPromotionV1::create()->set('node_ref', $nodeRef);

        $handler = new UnpublishPromotionHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedId = $nodeRef->getId();

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedId) {
                $this->assertInstanceOf(NodeUnpublished::class, $event);
                $this->assertSame(StreamId::fromString("promotion.history:{$expectedId}")->toString(), $streamId->toString());
            }
        );
    }
}