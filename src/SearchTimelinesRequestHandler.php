<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Triniti\Schemas\Curator\Mixin\SearchTimelinesRequest\SearchTimelinesRequestV1Mixin;

class SearchTimelinesRequestHandler extends AbstractSearchNodesRequestHandler
{
    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            SearchTimelinesRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
