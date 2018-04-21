<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractGetNodeHistoryRequestHandler;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\GetEventsRequest\GetEventsRequest;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Schemas\Curator\Mixin\Widget\WidgetV1Mixin;

class GetWidgetHistoryRequestHandler extends AbstractGetNodeHistoryRequestHandler
{
    /**
     * {@inheritdoc}
     */
    protected function canReadStream(GetEventsRequest $request, Pbjx $pbjx): bool
    {
        /** @var StreamId $streamId */
        $streamId = $request->get('stream_id');
        $validTopics = [];

        /** @var Schema $schema */
        foreach (WidgetV1Mixin::findAll() as $schema) {
            $qname = $schema->getQName();
            // e.g. "carousel-widget.history", "gyro-widget.history"
            $validTopics[$qname->getMessage() . '.history'] = true;
        }

        return isset($validTopics[$streamId->getTopic()]);
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
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:request:get-widget-history-request"),
        ];
    }
}
