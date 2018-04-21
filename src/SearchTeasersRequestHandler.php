<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Gdbots\Pbj\Schema;
use Gdbots\QueryParser\Enum\BoolOperator;
use Gdbots\QueryParser\Node\Field;
use Gdbots\QueryParser\Node\Word;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequest;
use Triniti\Schemas\Curator\Mixin\SearchTeasersRequest\SearchTeasersRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\Teaser\TeaserV1Mixin;

class SearchTeasersRequestHandler extends AbstractSearchNodesRequestHandler
{
    /**
     * {@inheritdoc}
     */
    protected function createQNamesForSearchNodes(SearchNodesRequest $request, ParsedQuery $parsedQuery): array
    {
        $validQNames = [];

        /** @var Schema $schema */
        foreach (TeaserV1Mixin::findAll() as $schema) {
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
    protected function beforeSearchNodes(SearchNodesRequest $request, ParsedQuery $parsedQuery): void
    {
        parent::beforeSearchNodes($request, $parsedQuery);
        $required = BoolOperator::REQUIRED();

        if ($request->has('gallery_ref')) {
            $parsedQuery->addNode(
                new Field(
                    'gallery_ref',
                    new Word((string)$request->get('gallery_ref'), $required),
                    $required
                )
            );
        }

        if ($request->has('timeline_ref')) {
            $parsedQuery->addNode(
                new Field(
                    'timeline_ref',
                    new Word((string)$request->get('timeline_ref'), $required),
                    $required
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            SearchTeasersRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
