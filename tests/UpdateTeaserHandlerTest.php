<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UpdateTeaserV1;
use Acme\Schemas\Curator\Event\TeaserUpdatedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UpdateTeaserHandler;

final class UpdateTeaserHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $oldNode = ArticleTeaserV1::create()
            ->set('title', 'old-test-title')
            ->set('target_ref', NodeRef::fromString('acme:article-teaser:test'));

        $this->ncr->putNode($oldNode);

        $newNode = ArticleTeaserV1::create()
            ->set('_id', $oldNode->get('_id'))
            ->set('title', 'new-test-title')
            ->set('target_ref', NodeRef::fromString('acme:article-teaser:test'));

        $command = UpdateTeaserV1::create()
            ->set('node_ref', NodeRef::fromNode($oldNode))
            ->set('old_node', $oldNode)
            ->set('new_node', $newNode);

        $handler = new UpdateTeaserHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedEvent = TeaserUpdatedV1::create();
        $expectedId = $oldNode->get('_id');

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedEvent, $expectedId) {
                $this->assertSame($event::schema(), $expectedEvent::schema());
                $this->assertTrue($event->has('old_node'));
                $this->assertTrue($event->has('new_node'));

                $newNodeFromEvent = $event->get('new_node');

                $this->assertSame(NodeStatus::DRAFT, (string)$newNodeFromEvent->get('status'));
                $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
                $this->assertSame($event->generateMessageRef()->toString(), (string)$newNodeFromEvent->get('last_event_ref'));
            }
        );
    }
}