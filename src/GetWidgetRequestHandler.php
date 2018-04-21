<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractGetNodeRequestHandler;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\WidgetPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Widget\WidgetV1Mixin;

class GetWidgetRequestHandler extends AbstractGetNodeRequestHandler
{
    use WidgetPbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        /** @var Schema $schema */
        $schema = WidgetV1Mixin::findAll()[0];
        $curie = $schema->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:request:get-widget-request"),
        ];
    }
}
