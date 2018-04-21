<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Triniti\Schemas\Curator\Mixin\SearchGalleriesRequest\SearchGalleriesRequestV1Mixin;

class SearchGalleriesRequestHandler extends AbstractSearchNodesRequestHandler
{
    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            SearchGalleriesRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
