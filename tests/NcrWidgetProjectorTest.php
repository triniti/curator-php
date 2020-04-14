<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\WidgetCreatedV1;
use Acme\Schemas\Curator\Event\WidgetDeletedV1;
use Acme\Schemas\Curator\Event\WidgetUpdatedV1;
use Acme\Schemas\Curator\Node\CarouselWidgetV1;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\NcrWidgetProjector;

final class NcrWidgetProjectorTest extends AbstractPbjxTest
{
    /** @var NcrWidgetProjector */
    protected $projector;

    /** @var NcrSearch|\PHPUnit\Framework\MockObject\MockObject */
    protected $ncrSearch;

    public function setup(): void
    {
        parent::setup();
        $this->ncrSearch = $this->getMockBuilder(MockNcrSearch::class)->getMock();
        $this->projector = new NcrWidgetProjector($this->ncr, $this->ncrSearch);
    }

    public function testOnWidgetCreated(): void
    {
        $item = CarouselWidgetV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = WidgetCreatedV1::create()->set('node', $item);
        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onWidgetCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnWidgetCreatedIsReplay(): void
    {
        $item = CarouselWidgetV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $event = WidgetCreatedV1::create()->set('node', $item);
        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onWidgetCreated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($item->equals($actualArticle));
    }

    public function testOnWidgetUpdated(): void
    {
        $oldItem = CarouselWidgetV1::create();
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = CarouselWidgetV1::create()
            ->set('_id', $oldItem->get('_id'));

        $newItem->set('title', 'New item');

        $event = WidgetUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $this->ncrSearch->expects($this->once())->method('indexNodes');
        $this->projector->onWidgetUpdated($event, $this->pbjx);
        $actualArticle = $this->ncr->getNode($nodeRef);
        $this->assertTrue($newItem->equals($actualArticle));
    }

    public function testOnWidgetUpdatedIsReplay(): void
    {
        $oldItem = CarouselWidgetV1::create();
        $oldItem->set('title', 'Old item');
        $nodeRef = NodeRef::fromNode($oldItem);
        $this->ncr->putNode($oldItem);
        $newItem = CarouselWidgetV1::create();

        $newItem->set('title', 'New item')
            ->set('etag', $newItem->generateEtag(['etag', 'updated_at']));

        $event = WidgetUpdatedV1::create()
            ->set('old_node', $oldItem)
            ->set('new_node', $newItem)
            ->set('old_etag', $oldItem->get('etag'))
            ->set('new_etag', $newItem->get('etag'))
            ->set('node_ref', $nodeRef);

        $event->isReplay(true);
        $this->ncrSearch->expects($this->never())->method('indexNodes');
        $this->projector->onWidgetUpdated($event, $this->pbjx);
        $actualItem = $this->ncr->getNode($nodeRef);
        $this->assertTrue($actualItem->equals($oldItem));
    }

    public function testOnWidgetDeleted(): void
    {
        $item = CarouselWidgetV1::create();
        $nodeRef = NodeRef::fromNode($item);
        $this->ncr->putNode($item);
        $event = WidgetDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onWidgetDeleted($event, $this->pbjx);
        $deletedItem = $this->ncr->getNode($nodeRef);
        $this->assertEquals(NodeStatus::DELETED(), $deletedItem->get('status'));
    }

    public function testOnWidgetDeletedNodeRefNotExists(): void
    {
        $article = CarouselWidgetV1::create();
        $nodeRef = NodeRef::fromNode($article);
        $event = WidgetDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onWidgetDeleted($event, $this->pbjx);
        $this->assertFalse($this->ncr->hasNode($nodeRef));
    }
}
