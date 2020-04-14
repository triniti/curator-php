<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\SyncTeaserV1;
use Acme\Schemas\Curator\Event\TeaserCreated;
use Acme\Schemas\Curator\Event\TeaserUpdated;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Ncr\Enricher\NodeEtagEnricher;
use Gdbots\Ncr\IndexQuery;
use Gdbots\Ncr\IndexQueryResult;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\Repository\InMemoryNcr;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Pbjx\EventStore\InMemoryEventStore;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\SyncTeaserHandler;
use Triniti\Curator\TeaserTransformer;
use Triniti\Schemas\Curator\TeaserId;
use Triniti\Schemas\News\ArticleId;
use Triniti\Sys\Flags;

final class SyncTeaserHandlerTest extends AbstractPbjxTest
{
    /**
     * Prepare the test.
     */
    public function setup(): void
    {
        parent::setup();
        $this->ncr = new class implements Ncr {
            public function __construct()
            {
                $this->next = new InMemoryNcr();
            }

            public function createStorage(SchemaQName $qname, array $context = []): void
            {
            }

            public function describeStorage(SchemaQName $qname, array $context = []): string
            {
            }

            public function hasNode(NodeRef $nodeRef, bool $consistent = false, array $context = []): bool
            {
                return $this->next->hasNode($nodeRef, $consistent, $context);
            }

            public function getNode(NodeRef $nodeRef, bool $consistent = false, array $context = []): Node
            {
                return $this->next->getNode($nodeRef, $consistent, $context);
            }

            public function getNodes(array $nodeRefs, bool $consistent = false, array $context = []): array
            {
                return $this->next->getNodes($nodeRefs, $consistent, $context);
            }

            public function putNode(Node $node, ?string $expectedEtag = null, array $context = []): void
            {
                $this->next->putNode($node, $expectedEtag, $context);
            }

            public function deleteNode(NodeRef $nodeRef, array $context = []): void
            {
                $this->next->deleteNode($nodeRef, $context);
            }

            public function findNodeRefs(IndexQuery $query, array $context = []): IndexQueryResult
            {
                $query = new IndexQuery(
                    $query->getQName(),
                    str_replace('target', 'target_ref', $query->getAlias()),
                    $query->getValue(),
                    $query->getCount(),
                    $query->getCursor(),
                    $query->sortAsc(),
                    $query->getFilters()
                );

                return $this->next->findNodeRefs($query, $context);
            }

            public function pipeNodes(SchemaQName $qname, callable $receiver, array $context = []): void
            {
                $this->next->pipeNodes($qname, $receiver, $context);
            }

            public function pipeNodeRefs(SchemaQName $qname, callable $receiver, array $context = []): void
            {
                $this->next->pipeNodeRefs($qname, $receiver, $context);
            }
        };

        $this->locator->setEventStore(new InMemoryEventStore($this->pbjx));
        $this->locator->getDispatcher()->addSubscriber(new NodeEtagEnricher);
    }

    public function testHandleSyncByTargetRefWithoutTeasers(): void
    {
        $article = ArticleV1::create()
            ->set('title', 'article title')
            ->set('status', NodeStatus::create('published'));
        $this->ncr->putNode($article);

        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        $events = [];
        $this->locator->getEventStore()->pipeAllEvents(function (Message $event) use (&$events) {
            $events[] = $event;
        });

        $this->assertTrue(
            $events[0] instanceof TeaserCreated && $events[0]->get('node')->get('target_ref')->equals(NodeRef::fromNode($article)),
            'A TeaserCreated event should be put when a teaserable with no teasers is synced by target_ref.'
        );
    }

    public function testHandleSyncByTargetRefWithTeasers(): void
    {
        $article = ArticleV1::create()
            ->set('title', 'article title')
            ->set('status', NodeStatus::PUBLISHED());
        $teaser1 = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true)
            ->set('title', 'teaser title 1')
            ->set('etag', 'before');
        $teaser2 = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true)
            ->set('title', 'teaser title 2')
            ->set('etag', 'before');

        $this->ncr->putNode($article);
        $this->ncr->putNode($teaser1);
        $this->ncr->putNode($teaser2);

        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        $events = [];
        $this->locator->getEventStore()->pipeAllEvents(function (Message $event) use (&$events) {
            $events[(string)$event->get('node_ref')] = $event;
        });

        foreach ([$teaser1, $teaser2] as $teaser) {
            $event = $events[NodeRef::fromNode($teaser)->toString()];
            $this->assertTrue(
                $event instanceof TeaserUpdated && $event->get('node_ref')->equals(NodeRef::fromNode($teaser)),
                'The TeaserUpdated event should be put.'
            );
        }
    }

    public function testHandleSyncByTargetRefWithTeasersMixedSync(): void
    {
        $article = ArticleV1::create()
            ->set('title', 'article title')
            ->set('status', NodeStatus::create('published'));
        $teaser1 = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true)
            ->set('title', 'teaser title 1');
        $teaser2 = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false)
            ->set('title', 'teaser title 2');
        $this->ncr->putNode($article);
        $this->ncr->putNode($teaser1);
        $this->ncr->putNode($teaser2);

        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        $events = [];
        $this->locator->getEventStore()->pipeAllEvents(function (Message $event) use (&$events) {
            $events[] = $event;
        });

        $this->assertTrue(
            count($events) === 1,
            'The event store should only have one event after sync by target_ref when only one of the two teasers is set to sync.'
        );

        $this->assertTrue(
            $events[0] instanceof TeaserUpdated && $events[0]->get('node_ref')->equals(NodeRef::fromNode($teaser1)),
            'The TeaserUpdated event should be put for the teaser that is set to sync.'
        );
    }

    public function testHandleSyncByTeaserRef(): void
    {
        $article = ArticleV1::create()
            ->set('title', 'article title')
            ->set('status', NodeStatus::create('deleted'));

        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false)
            ->set('title', 'teaser title 1');

        $this->ncr->putNode($article);
        $this->ncr->putNode($teaser);

        $command = SyncTeaserV1::create()->set('teaser_ref', NodeRef::fromNode($teaser));
        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        $events = [];
        $this->locator->getEventStore()->pipeAllEvents(function (Message $event) use (&$events) {
            $events[] = $event;
        });

        $this->assertTrue(
            $events[0] instanceof TeaserUpdated && $events[0]->get('node_ref')->equals(NodeRef::fromNode($teaser)),
            'When synced by teaser_ref, the TeaserUpdated event should be put even when the teaser is not set to sync_with_target.'
        );
    }

    public function testDontSyncIfEtagIdentical(): void
    {
        $article = ArticleV1::create()
            ->set('title', 'article title');

        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('title', 'article title');

        $this->ncr->putNode($article);
        $this->ncr->putNode($teaser);

        $command = SyncTeaserV1::create()->set('teaser_ref', NodeRef::fromNode($teaser));
        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        $this->assertTrue(
            count($this->locator->getEventStore()->getEvents([])) === 0,
            'The event store should still be empty before the sync command is handled, because teaser\'s etag would not have changed.'
        );
    }
}
