<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\PromotionCreatedV1;
use Acme\Schemas\Curator\Event\PromotionDeletedV1;
use Acme\Schemas\Curator\Event\PromotionExpiredV1;
use Acme\Schemas\Curator\Event\PromotionMarkedAsDraftV1;
use Acme\Schemas\Curator\Event\PromotionMarkedAsPendingV1;
use Acme\Schemas\Curator\Event\PromotionPublishedV1;
use Acme\Schemas\Curator\Event\PromotionScheduledV1;
use Acme\Schemas\Curator\Event\PromotionUnpublishedV1;
use Acme\Schemas\Curator\Event\PromotionUpdatedV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\NcrPromotionProjector;

final class NcrPromotionProjectorTest extends AbstractPbjxTest
{
    /** @var NcrPromotionProjector */
    protected $projector;

    /** @var NcrSearch|\PHPUnit\Framework\MockObject\MockObject */
    protected $ncrSearch;

    public function setup(): void
    {
        parent::setup();
        $this->ncrSearch = $this->getMockBuilder(MockNcrSearch::class)->getMock();
        $this->projector = new NcrPromotionProjector($this->ncr, $this->ncrSearch);
    }

    public function testOnPromotionCreated(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = PromotionCreatedV1::create()->set('node', $item);
        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onPromotionCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnPromotionCreatedIsReplay(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = PromotionCreatedV1::create()->set('node', $item);
        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onPromotionCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnPromotionUpdated(): void
    {
        $oldItem = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = PromotionV1::create()->set('_id', $oldItem->get('_id'));
        $newItem->set('title', 'New item');

        $event = PromotionUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onPromotionUpdated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($newItem->equals($actualArticle));
    }

    public function testOnPromotionUpdatedIsReplay(): void
    {
        $oldItem = PromotionV1::create();
        $oldItem->set('title', 'Old item');
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = PromotionV1::create();

        $newItem->set('title', 'New item')
            ->set('etag', $newItem->generateEtag(['etag', 'updated_at']));

        $event = PromotionUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onPromotionUpdated($event, $this->pbjx);
        $actualItem = $this->ncr->getNode($nodeRef);
        $this->assertTrue($actualItem->equals($oldItem));
    }

    public function testOnPromotionDeleted(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = PromotionDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onPromotionDeleted($event, $this->pbjx);
        $deletedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DELETED(), $deletedItem->get('status'));
    }

    public function testOnPromotionExpired(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = PromotionExpiredV1::create()->set('node_ref', $nodeRef);
        $this->projector->onPromotionExpired($event, $this->pbjx);
        $expiredItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::EXPIRED(), $expiredItem->get('status'));
    }

    public function testOnPromotionMarkedAsDraft(): void
    {
        $item = PromotionV1::create()->set('status', NodeStatus::PENDING());
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = PromotionMarkedAsDraftV1::create()->set('node_ref', $nodeRef);
        $this->projector->onPromotionMarkedAsDraft($event, $this->pbjx);
        $draftArticle = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $draftArticle->get('status'));
    }

    public function testOnPromotionMarkedAsPending(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = PromotionMarkedAsPendingV1::create()->set('node_ref', $nodeRef);
        $this->projector->onPromotionMarkedAsPending($event, $this->pbjx);
        $pendingItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PENDING(), $pendingItem->get('status'));
    }

    public function testOnPromotionPublished(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $publishedAt = new \DateTime();
        $this->ncr->putNode($item);

        $event = PromotionPublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);

        $this->projector->onPromotionPublished($event, $this->pbjx);
        $publishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PUBLISHED(), $publishedItem->get('status'));
        $this->assertSame($publishedAt->getTimestamp(), $publishedItem->get('published_at')->getTimestamp());
    }

    public function testOnPromotionScheduled(): void
    {
        $item = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $publishAt = new \DateTime('+16 seconds');
        $this->ncr->putNode($item);

        $event = PromotionScheduledV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', $publishAt);

        $this->projector->onPromotionScheduled($event, $this->pbjx);
        $scheduledItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::SCHEDULED(), $scheduledItem->get('status'));
        $this->assertSame($publishAt->getTimestamp(), $scheduledItem->get('published_at')->getTimestamp());
    }

    public function testOnPromotionUnpublished(): void
    {
        $article = PromotionV1::create()->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($article);
        $this->ncr->putNode($article);
        $event = PromotionUnpublishedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onPromotionUnpublished($event, $this->pbjx);
        $unpublishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $unpublishedItem->get('status'));
    }

    public function testOnPromotionDeletedNodeRefNotExists(): void
    {
        $article = PromotionV1::create();
        $nodeRef = NodeRef::fromNode($article);
        $event = PromotionDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onPromotionDeleted($event, $this->pbjx);
        $this->assertFalse($this->ncr->hasNode($nodeRef));
    }
}
