<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Gdbots\Pbj\Schema;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequest;
use Triniti\Schemas\Curator\Mixin\SearchWidgetsRequest\SearchWidgetsRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\Widget\WidgetV1Mixin;

class SearchWidgetsRequestHandler extends AbstractSearchNodesRequestHandler
{
    /**
     * {@inheritdoc}
     */
    protected function createQNamesForSearchNodes(SearchNodesRequest $request, ParsedQuery $parsedQuery): array
    {
        $validQNames = [];

        /** @var Schema $schema */
        foreach (WidgetV1Mixin::findAll() as $schema) {
            $qname = $schema->getQName();
            $validQNames[$qname->getMessage()] = $qname;
        }

        $qnames = [];
        foreach ($request->get('types', []) as $type) {
            if (isset($validQNames[$type])) {
                $qnames[] = $validQNames[$type];
            }
        }

        if (empty($qnames)) {
            $qnames = array_values($validQNames);
        }

        return $qnames;
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            SearchWidgetsRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
