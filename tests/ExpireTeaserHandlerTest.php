<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\ExpireTeaserV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Schemas\Ncr\Mixin\NodeExpired\NodeExpired;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\ExpireTeaserHandler;

final class ExpireTeaserHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = ArticleTeaserV1::create()
            ->set('title', 'test-title')
            ->set('target_ref', NodeRef::fromString('acme:article:test'));

        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = ExpireTeaserV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new ExpireTeaserHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeExpired::class, $event);
            $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}