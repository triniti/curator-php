<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Triniti\Schemas\Curator\Mixin\SearchPromotionsRequest\SearchPromotionsRequestV1Mixin;

class SearchPromotionsRequestHandler extends AbstractSearchNodesRequestHandler
{
    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            SearchPromotionsRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
