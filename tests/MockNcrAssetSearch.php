<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequest;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesResponse\SearchNodesResponse;

class MockNcrAssetSearch extends MockNcrSearch
{
    /** @var int */
    private $assetCount = 0;

    /**
     * @param SearchNodesRequest  $request
     * @param ParsedQuery         $parsedQuery
     * @param SearchNodesResponse $response
     * @param array               $qnames
     * @param array               $context
     */
    public function searchNodes(SearchNodesRequest $request,
                                ParsedQuery $parsedQuery,
                                SearchNodesResponse $response,
                                array $qnames = [],
                                array $context = []): void
    {
        $response->set('total', $this->assetCount);
    }

    /**
     * @param int $count
     */
    public function setAssetCount(int $count): void
    {
        $this->assetCount = $count;
    }
}
