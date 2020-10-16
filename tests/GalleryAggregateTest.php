<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\UpdateGalleryImageCountV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Acme\Schemas\Dam\Node\ImageAssetV1;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\GalleryAggregate;

final class GalleryAggregateTest extends AbstractPbjxTest
{
    public function testOnGalleryImageCountUpdated(): void
    {
        $node = GalleryV1::create()->set('image_count', 20);
        $aggregate = GalleryAggregate::fromNode($node, $this->pbjx);
        $ncrSearch = new MockNcrSearch();
        $imageAsset = ImageAssetV1::create();
        $ncrSearch->indexNodes([$imageAsset]);
        $this->locator->registerRequestHandler(
            SchemaCurie::fromString('triniti:dam:request:search-assets-request'),
            new MockSearchNodesRequestHandler($ncrSearch),
        );
        $aggregate->updateGalleryImageCount(UpdateGalleryImageCountV1::create()->set('node_ref', $node->generateNodeRef()), $this->pbjx);
        $this->assertSame(1, $aggregate->getUncommittedEvents()[0]->get('image_count'));
    }
}
