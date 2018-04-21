<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\MarkTeaserAsDraftV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsDraft\NodeMarkedAsDraft;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\MarkTeaserAsDraftHandler;

final class MarkTeaserAsDraftHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = ArticleTeaserV1::create()
            ->set('title', 'test-title')
            ->set('target_ref', NodeRef::fromString('acme:article:test'));

        $nodeRef = NodeRef::fromNode($node);
        $node->set('status', NodeStatus::PENDING());
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = MarkTeaserAsDraftV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new MarkTeaserAsDraftHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeMarkedAsDraft::class, $event);
            $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}