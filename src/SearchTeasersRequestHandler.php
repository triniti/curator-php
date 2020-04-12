<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\Schema;
use Gdbots\Pbjx\Pbjx;
use Gdbots\QueryParser\Enum\BoolOperator;
use Gdbots\QueryParser\Node\Field;
use Gdbots\QueryParser\Node\Word;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\QueryParser\QueryParser;
use Gdbots\Schemas\Common\Enum\Trinary;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequest;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesResponse\SearchNodesResponse;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Cache\CacheItemPoolInterface;
use Triniti\Schemas\Curator\Enum\SearchTeasersSort;
use Triniti\Schemas\Curator\Mixin\SearchTeasersRequest\SearchTeasersRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\Teaser\TeaserV1Mixin;

class SearchTeasersRequestHandler extends AbstractSearchNodesRequestHandler
{
    protected const SLOTTING_MAX = 15;
    protected const SLOTTING_TTL = 180;

    /** @var Ncr */
    protected $ncr;

    /** @var CacheItemPoolInterface */
    protected $cache;

    /**
     * @param NcrSearch              $ncrSearch
     * @param Ncr                    $ncr
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(NcrSearch $ncrSearch, Ncr $ncr, CacheItemPoolInterface $cache)
    {
        parent::__construct($ncrSearch);
        $this->ncr = $ncr;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function handle(SearchNodesRequest $request, Pbjx $pbjx): SearchNodesResponse
    {
        // todo: implement popularity sorting on teasers
        /*
        $sort = $request->get('sort');
        if (SearchTeasersSort::POPULARITY()->equals($sort)) {
            return $this->handleUsingStats($request, $pbjx);
        }
        */

        try {
            $slottedNodes = $this->getSlottedNodes($request, $pbjx);
        } catch (\Throwable $e) {
            $slottedNodes = [];
        }

        if (empty($slottedNodes)) {
            return parent::handle($request, $pbjx);
        }

        $request = clone $request;
        $count = $request->get('count');
        $request->set('count', $count + count($slottedNodes));
        $response = parent::handle($request, $pbjx);

        $slottedIds = [];
        foreach ($slottedNodes as $slottedNode) {
            $slottedIds[(string)$slottedNode->get('_id')] = true;
        }

        /** @var Node[] $unslottedNodes */
        $unslottedNodes = $response->get('nodes', []);
        $response->clear('nodes');

        /** @var Node[] $finalNodes */
        $finalNodes = [];

        $page = $request->get('page');
        $slot = (($page - 1) * $count) + 1;
        $end = $slot + $count;

        for (; $slot < $end; $slot++) {
            if (isset($slottedNodes[$slot])) {
                $finalNodes[] = $slottedNodes[$slot];
                continue;
            }

            do {
                $node = array_shift($unslottedNodes);
                if (!isset($slottedIds[(string)$node->get('_id')])) {
                    $finalNodes[] = $node;
                    break;
                }
            } while (null !== $node);
        }

        while (count($finalNodes) < $count) {
            $node = array_shift($unslottedNodes);
            if (null === $node) {
                break;
            }

            if (!isset($slottedIds[(string)$node->get('_id')])) {
                $finalNodes[] = $node;
            }
        }

        return $response->addToList('nodes', $finalNodes);
    }

    /**
     * Returns the slotted nodes for a given slotting_key.
     * The return array is keyed by the slot position it should occupy.
     *
     * @param SearchNodesRequest $request
     * @param Pbjx               $pbjx
     *
     * @return Node[]
     */
    protected function getSlottedNodes(SearchNodesRequest $request, Pbjx $pbjx): array
    {
        if (!$request->has('slotting_key')
            || !NodeStatus::PUBLISHED()->equals($request->get('status'))
        ) {
            return [];
        }

        $slottingKey = $request->get('slotting_key');
        $cacheKey = "curator.slotting.{$slottingKey}.php";

        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $cachedSlots = $cacheItem->get();
            if (is_array($cachedSlots)) {
                $nodeRefs = [];
                foreach ($cachedSlots as $nodeRefStr) {
                    $nodeRefs[] = NodeRef::fromString($nodeRefStr);
                }

                /** @var Node[] $slots */
                $slots = [];
                $nodes = $this->ncr->getNodes($nodeRefs, false, $this->createNcrContext($request));

                foreach ($nodes as $node) {
                    if (
                        !NodeStatus::PUBLISHED()->equals($node->get('status'))
                        || $node->get('is_unlisted', false)
                        || !$node->isInMap('slotting', $slottingKey)
                    ) {
                        continue;
                    }

                    $slot = (int)$node->getFromMap('slotting', $slottingKey);
                    if ($slot < 1 || $slot > self::SLOTTING_MAX || isset($slots[$slot])) {
                        continue;
                    }

                    $slots[$slot] = $node;
                }

                return $slots;
            }
        }

        $slottingMax = self::SLOTTING_MAX;
        $query = "+slotting.{$slottingKey}:[1..{$slottingMax}]";
        $parsedQuery = (new QueryParser())->parse($query);

        /** @var SearchNodesRequest $slotRequest */
        $slotRequest = $request::schema()->createMessage();
        $slotRequest
            ->set('q', $query)
            ->addToSet('fields_used', $parsedQuery->getFieldsUsed())
            ->set('parsed_query_json', json_encode($parsedQuery))
            ->set('sort', SearchTeasersSort::ORDER_DATE_DESC())
            ->set('status', NodeStatus::PUBLISHED())
            ->set('count', min($request->get('count'), self::SLOTTING_MAX))
            ->set('is_unlisted', Trinary::FALSE_VAL);

        $response = $this->createSearchNodesResponse($slotRequest, $pbjx);
        $this->beforeSearchNodes($slotRequest, $parsedQuery);
        $qnames = $this->createQNamesForSearchNodes($request, $parsedQuery);

        $this->ncrSearch->searchNodes(
            $slotRequest,
            $parsedQuery,
            $response,
            $qnames,
            $this->createNcrSearchContext($slotRequest)
        );

        $slots = [];
        $slotsToCache = [];

        /** @var Node $node */
        foreach ($response->get('nodes', []) as $node) {
            $slot = (int)$node->getFromMap('slotting', $slottingKey);
            if (isset($slots[$slot])) {
                continue;
            }

            $slots[$slot] = $node;
            $slotsToCache[$slot] = NodeRef::fromNode($node)->toString();
        }

        $this->cache->saveDeferred($cacheItem->set($slotsToCache)->expiresAfter(self::SLOTTING_TTL));

        return $slots;
    }

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

        if (Trinary::UNKNOWN !== $request->get('is_unlisted')) {
            $parsedQuery->addNode(
                new Field(
                    'is_unlisted',
                    new Word(Trinary::TRUE_VAL === $request->get('is_unlisted') ? 'true' : 'false', $required),
                    $required
                )
            );
        }

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

        if ($request->has('channel_ref')) {
            $parsedQuery->addNode(
                new Field(
                    'channel_ref',
                    new Word((string)$request->get('channel_ref'), $required),
                    $required
                )
            );
        }

        /** @var NodeRef $nodeRef */
        foreach ($request->get('category_refs', []) as $nodeRef) {
            $parsedQuery->addNode(
                new Field(
                    'category_refs',
                    new Word($nodeRef->toString(), $required),
                    $required
                )
            );
        }

        foreach ($request->get('person_refs', []) as $nodeRef) {
            $parsedQuery->addNode(
                new Field(
                    'person_refs',
                    new Word($nodeRef->toString(), $required),
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
