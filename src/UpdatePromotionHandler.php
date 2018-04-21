<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractUpdateNodeHandler;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\PromotionPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Promotion\PromotionV1Mixin;

class UpdatePromotionHandler extends AbstractUpdateNodeHandler
{
    use PromotionPbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        $curie = PromotionV1Mixin::findOne()->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:update-promotion"),
        ];
    }
}