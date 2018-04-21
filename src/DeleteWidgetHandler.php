<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractDeleteNodeHandler;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\WidgetPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Widget\WidgetV1Mixin;

class DeleteWidgetHandler extends AbstractDeleteNodeHandler
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
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:delete-widget"),
        ];
    }
}
