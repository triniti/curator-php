<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\MarkTeaserAsPendingV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsPending\NodeMarkedAsPendingV1;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\MarkTeaserAsPendingHandler;

final class MarkTeaserAsPendingHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = ArticleTeaserV1::create()
            ->set('title', 'test_title')
            ->set('target_ref', NodeRef::fromString('acme:article:test'));

        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = MarkTeaserAsPendingV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new MarkTeaserAsPendingHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeMarkedAsPendingV1::class, $event);
            $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}