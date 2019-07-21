<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\GalleryCreatedV1;
use Acme\Schemas\Curator\Event\GalleryDeletedV1;
use Acme\Schemas\Curator\Event\GalleryExpiredV1;
use Acme\Schemas\Curator\Event\GalleryImageCountUpdatedV1;
use Acme\Schemas\Curator\Event\GalleryMarkedAsDraftV1;
use Acme\Schemas\Curator\Event\GalleryMarkedAsPendingV1;
use Acme\Schemas\Curator\Event\GalleryPublishedV1;
use Acme\Schemas\Curator\Event\GalleryRenamedV1;
use Acme\Schemas\Curator\Event\GalleryScheduledV1;
use Acme\Schemas\Curator\Event\GalleryUnpublishedV1;
use Acme\Schemas\Curator\Event\GalleryUpdatedV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Acme\Schemas\Dam\Event\AssetCreatedV1;
use Acme\Schemas\Dam\Event\GalleryAssetReorderedV1;
use Acme\Schemas\Dam\Node\ImageAssetV1;
use Acme\Schemas\Dam\Node\VideoAssetV1;
use Acme\Schemas\Dam\Request\SearchAssetsResponseV1;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;
use Triniti\Curator\NcrGalleryProjector;
use Triniti\Schemas\Dam\AssetId;

class SearchAssetsRequestHandler implements RequestHandler
{
    /** @var NcrSearch */
    protected $ncrSearch;

    /**
     * @param NcrSearch $ncrSearch
     */
    public function __construct(NcrSearch $ncrSearch)
    {
        $this->ncrSearch = $ncrSearch;
    }

    public function handleRequest(Request $request, Pbjx $pbjx): Response
    {
        $response = SearchAssetsResponseV1::create();
        $this->ncrSearch->searchNodes($request, new ParsedQuery(), $response);
        return $response;
    }

    public static function handlesCuries(): array
    {
    }
}

final class NcrGalleryProjectorTest extends AbstractPbjxTest
{
    /** @var NcrGalleryProjector */
    protected $projector;

    /** @var NcrSearch|\PHPUnit_Framework_MockObject_MockObject */
    protected $ncrSearch;

    /** @var Pbjx|\PHPUnit_Framework_MockObject_MockObject */
    protected $pbjx;

    public function setup()
    {
        parent::setup();
        $this->ncrSearch = $this->getMockBuilder(MockNcrSearch::class)->getMock();
        $this->projector = new NcrGalleryProjector($this->ncr, $this->ncrSearch);
        $this->pbjx = $this->getMockBuilder(MockPbjx::class)->getMock();
    }

    public function testOnImageAssetCreated(): void
    {
        $gallery = GalleryV1::create();
        $galleryRef = NodeRef::fromNode($gallery);
        $this->ncr->putNode($gallery);

        $image = ImageAssetV1::fromArray([
            '_id'         => AssetId::create('image', 'jpg'),
            'mime_type'   => 'image/jpeg',
            'gallery_ref' => $galleryRef,
            'status'      => NodeStatus::PUBLISHED(),
        ]);

        $event = AssetCreatedV1::create()->set('node', $image);
        $this->pbjx->expects($this->once())->method('sendAt');
        $this->projector->onAssetCreated($event, $this->pbjx);
    }

    public function testOnImageAssetCreatedIsReplay(): void
    {
        $item = ImageAssetV1::create()
            ->set('_id', AssetId::create('image', 'jpg'))
            ->set('mime_type', 'image/jpeg');
        $event = AssetCreatedV1::create()->set('node', $item);
        $event->isReplay(true);

        $this->pbjx->expects($this->never())->method('sendAt');
        $this->projector->onAssetCreated($event, $this->pbjx);
    }

    public function testOnNonImageAssetCreated()
    {
        $image = VideoAssetV1::fromArray([
            '_id'       => AssetId::create('video', 'mp4'),
            'mime_type' => 'video/mp4',
            'status'    => NodeStatus::PUBLISHED(),
        ]);

        $event = AssetCreatedV1::create()->set('node', $image);
        $this->pbjx->expects($this->never())->method('sendAt');
        $this->projector->onAssetCreated($event, $this->pbjx);
    }

    public function testOnAssetCreatedWithoutGalleryRef(): void
    {
        $image = ImageAssetV1::fromArray([
            '_id'       => AssetId::create('image', 'jpg'),
            'mime_type' => 'image/jpeg',
            'status'    => NodeStatus::PUBLISHED(),
        ]);

        $event = AssetCreatedV1::create()->set('node', $image);
        $this->pbjx->expects($this->never())->method('sendAt');
        $this->projector->onAssetCreated($event, $this->pbjx);
    }

    public function testOnGalleryAssetReordered(): void
    {
        $gallery = GalleryV1::create();
        $galleryRef = NodeRef::fromNode($gallery);

        $image = ImageAssetV1::fromArray([
            '_id'         => AssetId::create('image', 'jpg'),
            'mime_type'   => 'image/jpeg',
            'gallery_ref' => $galleryRef,
            'status'      => NodeStatus::PUBLISHED(),
        ]);

        $this->ncr->putNode($image);
        $this->ncr->putNode($gallery);

        $event = GalleryAssetReorderedV1::create()
            ->set('node_ref', NodeRef::fromNode($image))
            ->set('gallery_ref', $galleryRef);

        $this->pbjx->expects($this->once())->method('sendAt');
        $this->projector->onGalleryAssetReordered($event, $this->pbjx);
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
        $gallery = GalleryV1::create();
        $nodeRef = NodeRef::fromNode($gallery);
        $event = GalleryDeletedV1::create()->set('node_ref', $nodeRef);
        $this->projector->onGalleryDeleted($event, $this->pbjx);
        $this->assertFalse($this->ncr->hasNode($nodeRef));
    }

    public function testOnGalleryImageCountUpdated(): void
    {
        $gallery = GalleryV1::create();
        $gallery->set('image_count', 20);
        $this->ncr->putNode($gallery);
        $nodeRef = NodeRef::fromNode($gallery);
        $event = GalleryImageCountUpdatedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('image_count', 5);
        $this->projector->onGalleryImageCountUpdated($event, $this->pbjx);
        $updatedGallery = $this->ncr->getNode($nodeRef);
        $this->assertEquals(5, $updatedGallery->get('image_count'));
    }
}
