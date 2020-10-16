<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\SchemaQName;
use Gdbots\QueryParser\ParsedQuery;

class MockNcrSearch implements NcrSearch
{
    private array $nodes = [];

    public function createStorage(SchemaQName $qname, array $context = []): void
    {
        var_dump('MockNcrSearch createStorage');
        die();
        // do nothing
    }

    public function describeStorage(SchemaQName $qname, array $context = []): string
    {
        var_dump('MockNcrSearch describeStorage');
        die();
        // do nothing
    }

    public function indexNodes(array $nodes, array $context = []): void
    {
        $this->deleteNodes(array_map(fn(Message $node) => $node->generateNodeRef(), $nodes));
        $this->nodes = array_merge($this->nodes, $nodes);
    }

    public function deleteNodes(array $nodeRefs, array $context = []): void
    {
        $this->nodes = array_filter($this->nodes, function (Message $node) use ($nodeRefs) {
            return !in_array($node->generateNodeRef(), $nodeRefs);
        });
    }

    public function searchNodes(Message $request, ParsedQuery $parsedQuery, Message $response, array $qnames = [], array $context = []): void
    {
        $response
            ->addToList('nodes', $this->nodes)
            ->set('total', count($this->nodes));
    }
}
