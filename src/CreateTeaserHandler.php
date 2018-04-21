<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractCreateNodeHandler;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Triniti\Curator\Util\TeaserPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Teaser\TeaserV1Mixin;

class CreateTeaserHandler extends AbstractCreateNodeHandler
{
    use TeaserPbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        /** @var Schema $schema */
        $schema = TeaserV1Mixin::findAll()[0];
        $curie = $schema->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:create-teaser"),
        ];
    }
}
