<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractMarkNodeAsPendingHandler;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\GalleryPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Gallery\GalleryV1Mixin;

class MarkGalleryAsPendingHandler extends AbstractMarkNodeAsPendingHandler
{
    use GalleryPbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        $curie = GalleryV1Mixin::findOne()->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:mark-gallery-as-pending"),
        ];
    }
}
