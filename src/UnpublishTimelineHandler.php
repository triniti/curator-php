<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractUnpublishNodeHandler;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\TimelinePbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Timeline\TimelineV1Mixin;

class UnpublishTimelineHandler extends AbstractUnpublishNodeHandler
{
    use TimelinePbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        $curie = TimelineV1Mixin::findOne()->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:unpublish-timeline"),
        ];
    }
}