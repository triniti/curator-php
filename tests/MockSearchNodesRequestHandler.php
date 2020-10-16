<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Dam\Request\SearchAssetsResponseV1;
use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\QueryParser\ParsedQuery;

final class MockSearchNodesRequestHandler extends AbstractSearchNodesRequestHandler
{
    protected function createSearchNodesResponse(Message $request, Pbjx $pbjx): Message
    {
        $response = SearchAssetsResponseV1::create();
        $this->ncrSearch->searchNodes($request, new ParsedQuery(), $response);
        return $response;
    }

    public static function handlesCuries(): array
    {
        return [];
    }
}
