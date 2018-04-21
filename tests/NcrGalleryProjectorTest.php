<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\GalleryCreatedV1;
use Acme\Schemas\Curator\Event\GalleryDeletedV1;
use Acme\Schemas\Curator\Event\GalleryExpiredV1;
use Acme\Schemas\Curator\Event\GalleryMarkedAsDraftV1;
use Acme\Schemas\Curator\Event\GalleryMarkedAsPendingV1;
use Acme\Schemas\Curator\Event\GalleryPublishedV1;
use Acme\Schemas\Curator\Event\GalleryRenamedV1;
use Acme\Schemas\Curator\Event\GalleryScheduledV1;
use Acme\Schemas\Curator\Event\GalleryUnpublishedV1;
use Acme\Schemas\Curator\Event\GalleryUpdatedV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\NcrGalleryProjector;

final class NcrGalleryProjectorTest extends AbstractPbjxTest
{
    /** @var NcrGalleryProjector */
    protected $projector;

    /** @var NcrSearch|\PHPUnit_Framework_MockObject_MockObject */
    protected $ncrSearch;

    public function setup()
    {
        parent::setup();
        $this->ncrSearch = $this->getMockBuilder(MockNcrSearch::class)->getMock();
        $this->projector = new NcrGalleryProjector($this->ncr, $this->ncrSearch);
    }

    public function testOnGalleryCreated(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = GalleryCreatedV1::create()->set('node', $item);
        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onGalleryCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnGalleryCreatedIsReplay(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = GalleryCreatedV1::create()->set('node', $item);
        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onGalleryCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnGalleryUpdated(): void
    {
        $oldItem = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = GalleryV1::create()->set('_id', $oldItem->get('_id'));
        $newItem->set('title', 'New item')
            ->set('slug', $newItem->generateEtag(['etag', 'updated_at']));

        $event = GalleryUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onGalleryUpdated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($newItem->equals($actualArticle));
    }

    public function testOnGalleryUpdatedIsReplay(): void
    {
        $oldItem = GalleryV1::create();
        $oldItem->set('title', 'Old item');
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = GalleryV1::create();

        $newItem->set('title', 'New item')
            ->set('etag', $newItem->generateEtag(['etag', 'updated_at']));

        $event = GalleryUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onGalleryUpdated($event, $this->pbjx);
        $actualItem = $this->ncr->getNode($nodeRef);
        $this->assertTrue($actualItem->equals($oldItem));
    }

    public function testOnGalleryDeleted(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = GalleryDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryDeleted($event, $this->pbjx);
        $deletedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DELETED(), $deletedItem->get('status'));
    }

    public function testOnGalleryExpired(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = GalleryExpiredV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryExpired($event, $this->pbjx);
        $expiredItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::EXPIRED(), $expiredItem->get('status'));
    }

    public function testOnGalleryMarkedAsDraft(): void
    {
        $item = GalleryV1::create()->set('status', NodeStatus::PENDING());
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = GalleryMarkedAsDraftV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryMarkedAsDraft($event, $this->pbjx);
        $draftArticle = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $draftArticle->get('status'));
    }

    public function testOnGalleryMarkedAsPending(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = GalleryMarkedAsPendingV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryMarkedAsPending($event, $this->pbjx);
        $pendingItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PENDING(), $pendingItem->get('status'));
    }

    public function testOnGalleryPublished(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $publishedAt = new \DateTime();
        $this->ncr->putNode($item);

        $event = GalleryPublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);

        $this->projector->onGalleryPublished($event, $this->pbjx);
        $publishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::PUBLISHED(), $publishedItem->get('status'));
        $this->assertSame($publishedAt->getTimestamp(), $publishedItem->get('published_at')->getTimestamp());
    }

    public function testOnGalleryScheduled(): void
    {
        $item = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $publishAt = new \DateTime('+16 seconds');
        $this->ncr->putNode($item);

        $event = GalleryScheduledV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', $publishAt);

        $this->projector->onGalleryScheduled($event, $this->pbjx);
        $scheduledItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::SCHEDULED(), $scheduledItem->get('status'));
        $this->assertSame($publishAt->getTimestamp(), $scheduledItem->get('published_at')->getTimestamp());
    }

    public function testOnGalleryUnpublished(): void
    {
        $article = GalleryV1::create()->set('status', NodeStatus::PUBLISHED());
        $nodeRef = NodeRef::fromNode($article);
        $this->ncr->putNode($article);
        $event = GalleryUnpublishedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryUnpublished($event, $this->pbjx);
        $unpublishedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DRAFT(), $unpublishedItem->get('status'));
    }

    public function testOnGalleryRenamed(): void
    {
        $item = GalleryV1::create()->set('slug', 'item-to-rename');
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = GalleryRenamedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('new_slug', 'new-item-name');

        $this->projector->onGalleryRenamed($event, $this->pbjx);
        $renamedArticle = $this->ncr->getNode($nodeRef);
        $this->assertEquals('new-item-name', $renamedArticle->get('slug'));
    }

    public function testOnGalleryDeletedNodeRefNotExists(): void
    {
        $article = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($article);
        $event = GalleryDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryDeleted($event, $this->pbjx);
        $this->assertFalse($this->ncr->hasNode($nodeRef));
    }
}