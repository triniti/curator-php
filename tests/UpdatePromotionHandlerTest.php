<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UpdatePromotionV1;
use Acme\Schemas\Curator\Event\PromotionUpdatedV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\UpdatePromotionHandler;

final class UpdatePromotionHandlerTest extends AbstractPbjxTest
{
    public function testHandle(): void
    {
        $oldNode = PromotionV1::create();
        $this->ncr->putNode($oldNode);

        $newNode = PromotionV1::create()->set('_id', $oldNode->get('_id'));

        $command = UpdatePromotionV1::create()
            ->set('node_ref', NodeRef::fromNode($oldNode))
            ->set('old_node', $oldNode)
            ->set('new_node', $newNode);

        $handler = new UpdatePromotionHandler($this->ncr);
        $handler->handleCommand($command, $this->pbjx);

        $expectedEvent = PromotionUpdatedV1::create();
        $expectedId = $oldNode->get('_id');

        $this->eventStore->pipeAllEvents(
            function (Event $event, StreamId $streamId) use ($expectedEvent, $expectedId) {
                $this->assertSame($event::schema(), $expectedEvent::schema());
                $this->assertTrue($event->has('old_node'));
                $this->assertTrue($event->has('new_node'));

                $newNodeFromEvent = $event->get('new_node');

                $this->assertSame(NodeStatus::DRAFT, (string)$newNodeFromEvent->get('status'));
                $this->assertSame(StreamId::fromString("promotion.history:{$expectedId}")->toString(), $streamId->toString());
                $this->assertSame($event->generateMessageRef()->toString(), (string)$newNodeFromEvent->get('last_event_ref'));
            }
        );
    }
}