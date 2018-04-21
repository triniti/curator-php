<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\TeaserCreatedV1;
use Acme\Schemas\Curator\Event\TeaserDeletedV1;
use Acme\Schemas\Curator\Event\TeaserExpiredV1;
use Acme\Schemas\Curator\Event\TeaserMarkedAsDraftV1;
use Acme\Schemas\Curator\Event\TeaserMarkedAsPendingV1;
use Acme\Schemas\Curator\Event\TeaserPublishedV1;
use Acme\Schemas\Curator\Event\TeaserUnpublishedV1;
use Acme\Schemas\Curator\Event\TeaserUpdatedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\NcrTeaserProjector;

final class NcrTeaserProjectorTest extends AbstractPbjxTest
{
    /** @var NcrTeaserProjector */
    protected $projector;

    /** @var NcrSearch|\PHPUnit_Framework_MockObject_MockObject */
    protected $ncrSearch;

    public function setup()
    {
        parent::setup();
        $this->ncrSearch = $this->getMockBuilder(MockNcrSearch::class)->getMock();
        $this->projector = new NcrTeaserProjector($this->ncr, $this->ncrSearch);
    }

    public function testOnTeaserCreated(): void
    {
        $item = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($item);
        $event = TeaserCreatedV1::create()->set('node', $item);
        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onTeaserCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnTeaserCreatedIsReplay(): void
    {
        $item = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($item);
        $event = TeaserCreatedV1::create()->set('node', $item);
        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onTeaserCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnTeaserUpdated(): void
    {
        $oldItem = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = ArticleTeaserV1::create()
            ->set('_id', $oldItem->get('_id'))
            ->set('target_ref', NodeRef::fromString('acme:article:test'));

        $newItem->set('title', 'New item');

        $event = TeaserUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onTeaserUpdated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($newItem->equals($actualArticle));
    }

    public function testOnTeaserUpdatedIsReplay(): void
    {
        $oldItem = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $oldItem->set('title', 'Old item');
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));

        $newItem->set('title', 'New item')
            ->set('etag', $newItem->generateEtag(['etag', 'updated_at']));

        $event = TeaserUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onTeaserUpdated($event, $this->pbjx);
        $actualItem = $this->ncr->getNode($nodeRef);
        $this->assertTrue($actualItem->equals($oldItem));
    }

    public function testOnTeaserDeleted(): void
    {
        $item = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TeaserDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTeaserDeleted($event, $this->pbjx);
        $deletedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DELETED(), $deletedItem->get('status'));
    }

    public function testOnTeaserExpired(): void
    {
        $item = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TeaserExpiredV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTeaserExpired($event, $this->pbjx);
        $expiredItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::EXPIRED(), $expiredItem->get('status'));
    }

    public function testOnTeaserMarkedAsDraft(): void
    {
        $item = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromString('acme:article:test'))
            ->set('status', NodeStatus::PENDING());
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TeaserMarkedAsDraftV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTeaserMarkedAsDraft($event, $this->pbjx);
        $draftArticle = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $draftArticle->get('status'));
    }

    public function testOnTeaserMarkedAsPending(): void
    {
        $item = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = TeaserMarkedAsPendingV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTeaserMarkedAsPending($event, $this->pbjx);
        $pendingItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PENDING(), $pendingItem->get('status'));
    }

    public function testOnTeaserPublished(): void
    {
        $item = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($item);
        $publishedAt = new \DateTime();
        $this->ncr->putNode($item);

        $event = TeaserPublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);

        $this->projector->onTeaserPublished($event, $this->pbjx);
        $publishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PUBLISHED(), $publishedItem->get('status'));
        $this->assertSame($publishedAt->getTimestamp(), $publishedItem->get('published_at')->getTimestamp());
    }

    public function testOnTeaserUnpublished(): void
    {
        $article = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromString('acme:article:test'))
            ->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($article);
        $this->ncr->putNode($article);
        $event = TeaserUnpublishedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTeaserUnpublished($event, $this->pbjx);
        $unpublishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $unpublishedItem->get('status'));
    }

    public function testOnTeaserDeletedNodeRefNotExists(): void
    {
        $article = ArticleTeaserV1::create()->set('target_ref', NodeRef::fromString('acme:article:test'));
        $nodeRef = NodeRef::fromNode($article);
        $event = TeaserDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onTeaserDeleted($event, $this->pbjx);
        $this->assertFalse($this->ncr->hasNode($nodeRef));
    }
}