<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractCreateNodeHandler;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\CreateNode\CreateNode;
use Gdbots\Schemas\Ncr\Mixin\NodeCreated\NodeCreated;
use Triniti\Curator\Util\WidgetPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Widget\WidgetV1Mixin;

class CreateWidgetHandler extends AbstractCreateNodeHandler
{
    use WidgetPbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    protected function beforePutEvents(NodeCreated $event, CreateNode $command, Pbjx $pbjx): void
    {
        parent::beforePutEvents($event, $command, $pbjx);
        $node = $event->get('node');
        $node->set('status', NodeStatus::PUBLISHED());
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        /** @var Schema $schema */
        $schema = WidgetV1Mixin::findAll()[0];
        $curie = $schema->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:create-widget"),
        ];
    }
}
