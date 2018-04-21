<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractExpireNodeHandler;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\TimelinePbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Timeline\TimelineV1Mixin;

class ExpireTimelineHandler extends AbstractExpireNodeHandler
{
    use TimelinePbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        $curie = TimelineV1Mixin::findOne()->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:expire-timeline"),
        ];
    }
}