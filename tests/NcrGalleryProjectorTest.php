<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Node\GalleryV1;
use Acme\Schemas\Dam\Event\AssetCreatedV1;
use Acme\Schemas\Dam\Node\ImageAssetV1;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Triniti\Curator\NcrGalleryProjector;
use Triniti\Schemas\Dam\AssetId;

final class NcrGalleryProjectorTest extends AbstractPbjxTest
{
    protected NcrGalleryProjector $projector;
    protected MockNcrSearch $ncrSearch;

    public function setup(): void
    {
        parent::setup();
        $this->ncrSearch = new MockNcrSearch();
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
}
