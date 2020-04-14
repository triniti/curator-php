<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\TimelineCreatedV1;
use Acme\Schemas\Curator\Event\TimelineDeletedV1;
use Acme\Schemas\Curator\Event\TimelineExpiredV1;
use Acme\Schemas\Curator\Event\TimelineMarkedAsDraftV1;
use Acme\Schemas\Curator\Event\TimelineMarkedAsPendingV1;
use Acme\Schemas\Curator\Event\TimelinePublishedV1;
use Acme\Schemas\Curator\Event\TimelineRenamedV1;
use Acme\Schemas\Curator\Event\TimelineScheduledV1;
use Acme\Schemas\Curator\Event\TimelineUnpublishedV1;
use Acme\Schemas\Curator\Event\TimelineUpdatedV1;
use Acme\Schemas\Curator\Node\TimelineV1;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\NcrTimelineProjector;

final class NcrTimelineProjectorTest extends AbstractPbjxTest
{
    /** @var NcrTimelineProjector */
    protected $projector;

    /** @var NcrSearch|\PHPUnit\Framework\MockObject\MockObject */
    protected $ncrSearch;

    public function setup(): void
    {
        parent::setup();
        $this->ncrSearch = $this->getMockBuilder(MockNcrSearch::class)->getMock();
        $this->projector = new NcrTimelineProjector($this->ncr, $this->ncrSearch);
    }

    public function testOnTimelineCreated(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = TimelineCreatedV1::create()->set('node', $item);
        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onTimelineCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnTimelineCreatedIsReplay(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = TimelineCreatedV1::create()->set('node', $item);
        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onTimelineCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnTimelineUpdated(): void
    {
        $oldItem = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = TimelineV1::create()->set('_id', $oldItem->get('_id'));
        $newItem->set('title', 'New item')
            ->set('slug', $newItem->generateEtag(['etag', 'updated_at']));

        $event = TimelineUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onTimelineUpdated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($newItem->equals($actualArticle));
    }

    public function testOnTimelineUpdatedIsReplay(): void
    {
        $oldItem = TimelineV1::create();
        $oldItem->set('title', 'Old item');
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = TimelineV1::create();

        $newItem->set('title', 'New item')
            ->set('etag', $newItem->generateEtag(['etag', 'updated_at']));

        $event = TimelineUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onTimelineUpdated($event, $this->pbjx);
        $actualItem = $this->ncr->getNode($nodeRef);
        $this->assertTrue($actualItem->equals($oldItem));
    }

    public function testOnTimelineDeleted(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TimelineDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTimelineDeleted($event, $this->pbjx);
        $deletedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DELETED(), $deletedItem->get('status'));
    }

    public function testOnTimelineExpired(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TimelineExpiredV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTimelineExpired($event, $this->pbjx);
        $expiredItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::EXPIRED(), $expiredItem->get('status'));
    }

    public function testOnTimelineMarkedAsDraft(): void
    {
        $item = TimelineV1::create()->set('status', NodeStatus::PENDING());
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TimelineMarkedAsDraftV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTimelineMarkedAsDraft($event, $this->pbjx);
        $draftArticle = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $draftArticle->get('status'));
    }

    public function testOnTimelineMarkedAsPending(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TimelineMarkedAsPendingV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTimelineMarkedAsPending($event, $this->pbjx);
        $pendingItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PENDING(), $pendingItem->get('status'));
    }

    public function testOnTimelinePublished(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $publishedAt = new \DateTime();
        $this->ncr->putNode($item);

        $event = TimelinePublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);

        $this->projector->onTimelinePublished($event, $this->pbjx);
        $publishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PUBLISHED(), $publishedItem->get('status'));
        $this->assertSame($publishedAt->getTimestamp(), $publishedItem->get('published_at')->getTimestamp());
    }

    public function testOnTimelineScheduled(): void
    {
        $item = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $publishAt = new \DateTime('+16 seconds');
        $this->ncr->putNode($item);

        $event = TimelineScheduledV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', $publishAt);

        $this->projector->onTimelineScheduled($event, $this->pbjx);
        $scheduledItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::SCHEDULED(), $scheduledItem->get('status'));
        $this->assertSame($publishAt->getTimestamp(), $scheduledItem->get('published_at')->getTimestamp());
    }

    public function testOnTimelineUnpublished(): void
    {
        $article = TimelineV1::create()->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($article);
        $this->ncr->putNode($article);
        $event = TimelineUnpublishedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTimelineUnpublished($event, $this->pbjx);
        $unpublishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $unpublishedItem->get('status'));
    }

    public function testOnTimelineRenamed(): void
    {
        $item = TimelineV1::create()->set('slug', 'item-to-rename');
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TimelineRenamedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('new_slug', 'new-item-name');

        $this->projector->onTimelineRenamed($event, $this->pbjx);
        $renamedArticle = $this->ncr->getNode($nodeRef);
        $this->assertEquals('new-item-name', $renamedArticle->get('slug'));
    }

    public function testOnTimelineDeletedNodeRefNotExists(): void
    {
        $article = TimelineV1::create();
        $nodeRef = NodeRef::fromNode($article);
        $event = TimelineDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTimelineDeleted($event, $this->pbjx);
        $this->assertFalse($this->ncr->hasNode($nodeRef));
    }
}
