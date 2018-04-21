<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UnpublishTeaserV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\NodeUnpublished\NodeUnpublished;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UnpublishTeaserHandler;

final class UnpublishTeaserHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $node = ArticleTeaserV1::create()
            ->set('title', 'test-title')
            ->set('target_ref', NodeRef::fromString('acme:article:test'));

        $node->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $command = UnpublishTeaserV1::create()->set('node_ref', $nodeRef);

        $handler = new UnpublishTeaserHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedId = $nodeRef->getId();

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedId) {
                $this->assertInstanceOf(NodeUnpublished::class, $event);
                $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
            }
        );
    }
}