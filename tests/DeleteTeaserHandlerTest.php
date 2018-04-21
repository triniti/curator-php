<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\DeleteTeaserV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Schemas\Ncr\Mixin\NodeDeleted\NodeDeleted;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\DeleteTeaserHandler;

final class DeleteTeaserHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $node = ArticleTeaserV1::create()
            ->set('title', 'test-teaser')
            ->set('target_ref', NodeRef::fromString('acme:article:test'));

        $nodeRef = NodeRef::fromNode($node);
        $this->ncr->putNode($node);

        $expectedId = $nodeRef->getId();

        $command = DeleteTeaserV1::create();
        $command->set('node_ref', $nodeRef);

        $handler = new DeleteTeaserHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId) use ($expectedId) {
            $this->assertInstanceOf(NodeDeleted::class, $event);
            $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
        });
    }
}