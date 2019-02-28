<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractUpdateNodeHandler;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\NodeUpdated\NodeUpdated;
use Gdbots\Schemas\Ncr\Mixin\UpdateNode\UpdateNode;
use Triniti\Curator\Util\WidgetPbjxHelperTrait;
use Triniti\Schemas\Curator\Mixin\Widget\Widget;
use Triniti\Schemas\Curator\Mixin\Widget\WidgetV1Mixin;

class UpdateWidgetHandler extends AbstractUpdateNodeHandler
{
    use WidgetPbjxHelperTrait;

    /**
     * {@inheritdoc}
     */
    protected function beforePutEvents(NodeUpdated $event, UpdateNode $command, Pbjx $pbjx): void
    {
        parent::beforePutEvents($event, $command, $pbjx);

        /** @var Widget $newNode */
        $newNode = $event->get('new_node');

        // widgets are only published or deleted, enforce it.
        // if we're updating the widget, force it to be published.
        $newNode->set('status', NodeStatus::PUBLISHED());
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
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:update-widget"),
        ];
    }
}
