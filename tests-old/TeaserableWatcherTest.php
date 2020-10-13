<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\DeleteTeaser;
use Acme\Schemas\Curator\Command\ExpireTeaser;
use Acme\Schemas\Curator\Command\PublishTeaser;
use Acme\Schemas\Curator\Command\SyncTeaserV1;
use Acme\Schemas\Curator\Command\UnpublishTeaser;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\News\Event\ArticleCreatedV1;
use Acme\Schemas\News\Event\ArticleDeletedV1;
use Acme\Schemas\News\Event\ArticleExpiredV1;
use Acme\Schemas\News\Event\ArticlePublishedV1;
use Acme\Schemas\News\Event\ArticleScheduledV1;
use Acme\Schemas\News\Event\ArticleUnpublishedV1;
use Acme\Schemas\News\Event\ArticleUpdatedV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Ncr\IndexQuery;
use Gdbots\Ncr\IndexQueryResult;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Triniti\Curator\TeaserableWatcher;
use Triniti\Schemas\Curator\Mixin\SyncTeaser\SyncTeaser;

final class TeaserableWatcherTest extends AbstractPbjxTest
{
    /** @var NcrSearch|\PHPUnit\Framework\MockObject\MockObject */
    protected $ncrSearch;
    protected $logger;

    /**
     * Prepare the test.
     */
    public function setup(): void
    {
        parent::setup();

        $this->ncr = new class implements Ncr {
            private $storage = [];

            public function createStorage(SchemaQName $qname, array $context = []): void
            {
            }

            public function deleteNode(NodeRef $nodeRef, array $context = []): void
            {
            }

            public function describeStorage(SchemaQName $qname, array $context = []): string
            {
            }

            public function findNodeRefs(IndexQuery $query, array $context = []): IndexQueryResult
            {
                $nodeRefs = array_map(function (Node $node) {
                    return NodeRef::fromNode($node);
                }, $this->storage);
                return new IndexQueryResult(new IndexQuery(SchemaQName::fromString('a:b'), 'alias', 'value'), $nodeRefs);
            }

            public function getNode(NodeRef $nodeRef, bool $consistent = false, array $context = []): Node
            {
                return $this->storage[$nodeRef->toString()];
            }

            public function getNodes(array $nodeRefs, bool $consistent = false, array $context = []): array
            {
                $keys = array_map('strval', $nodeRefs);
                $nodes = array_intersect_key($this->storage, array_flip($keys));

                /** @var Node[] $nodes */
                foreach ($nodes as $nodeRef => $node) {
                    if ($node->isFrozen()) {
                        $nodes[$nodeRef] = $this->storage[$nodeRef] = clone $node;
                    }
                }

                return $nodes;
            }

            public function hasNode(NodeRef $nodeRef, bool $consistent = false, array $context = []): bool
            {
            }

            public function pipeNodeRefs(SchemaQName $qname, callable $receiver, array $context = []): void
            {
            }

            public function pipeNodes(SchemaQName $qname, callable $receiver, array $context = []): void
            {
            }

            public function putNode(Node $node, ?string $expectedEtag = null, array $context = []): void
            {
                $this->storage[NodeRef::fromNode($node)->toString()] = $node;
            }
        };

        $this->locator->setScheduler(new class implements Scheduler {
            private $scheduled = [];

            function createStorage(): void
            {
            }

            function describeStorage(): string
            {
            }

            function getScheduled(): array
            {
                return $this->scheduled;
            }

            function sendAt(Command $command, int $timestamp, ?string $jobId = null): string
            {
                $this->scheduled[] = $command;
                return '';
            }

            function cancelJobs(array $jobIds): void
            {
            }
        });
    }

    public function testHandleCreate(): void
    {
        $article = ArticleV1::create();
        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleCreatedV1::create()->set('node', $article);
        $teaserableWatcher->onNodeCreated($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof SyncTeaserV1 && $scheduled->get('target_ref')->equals(NodeRef::fromNode($article))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for sync teaser (by target_ref) after onNodeCreated called.'
        );
    }

    public function testHandleDeleteNotSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleDeletedV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeDeleted($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof DeleteTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for DeleteTeaser after onNodeDeleted called, even if the teaser is not set to sync.'
        );
    }

    public function testHandleDeleteSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleDeletedV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeDeleted($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof DeleteTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for DeleteTeaser after onNodeDeleted called.'
        );
    }

    public function testHandleExpireNotSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleExpiredV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeExpired($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof ExpireTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for ExpireTeaser after onNodeExpired called, even if the teaser is not set to sync.',
        );
    }

    public function testHandleExpireSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleExpiredV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeExpired($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof ExpireTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for ExpireTeaser after onNodeExpired called.'
        );
    }

    public function testHandlePublishNotSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticlePublishedV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodePublished($event, $this->pbjx);

        $this->assertTrue(
            count($this->locator->getScheduler()->getScheduled()) === 0,
            'sendAt should not have been done for anything after onNodePublished called, because teaser is not set to sync with target.'
        );
    }

    public function testHandlePublishSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticlePublishedV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodePublished($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof PublishTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for PublishTeaser after onNodePublished called.'
        );
    }

    public function testHandleScheduleNotSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleScheduledV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeScheduled($event, $this->pbjx);

        $this->assertTrue(
            count($this->locator->getScheduler()->getScheduled()) === 0,
            'sendAt should not have been done for anything after onNodeScheduled called, because teaser is not set to sync with target.'
        );
    }

    public function testHandleScheduleSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $dateTime = new \DateTime('3011-01-01T15:03:01.012345Z');
        $event = ArticleScheduledV1::create()
            ->set('node_ref', NodeRef::fromNode($article))
            ->set('publish_at', $dateTime);
        $teaserableWatcher->onNodeScheduled($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if (
                $scheduled instanceof PublishTeaser
                && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))
                && $scheduled->get('publish_at')->format(DateUtils::ISO8601_ZULU) === $dateTime->format(DateUtils::ISO8601_ZULU)
            ) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for PublishTeaser after onNodeScheduled called.'
        );
    }

    public function testHandleUnpublishNotSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', false);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleUnpublishedV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeUnpublished($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof UnpublishTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for UnpublishTeaser after onNodeUnpublished called, even if the teaser is not set to sync.',
        );
    }

    public function testHandleUnpublishSync(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleUnpublishedV1::create()->set('node_ref', NodeRef::fromNode($article));
        $teaserableWatcher->onNodeUnpublished($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof UnpublishTeaser && $scheduled->get('node_ref')->equals(NodeRef::fromNode($teaser))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for UnpublishTeaser after onNodeUnpublished called.'
        );
    }

    public function testHandleUpdate(): void
    {
        $article = ArticleV1::create();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);

        $teaserableWatcher = new TeaserableWatcher($this->ncr);
        $event = ArticleUpdatedV1::create()
            ->set('node_ref', NodeRef::fromNode($article))
            ->set('new_node', $article);
        $teaserableWatcher->onNodeUpdated($event, $this->pbjx);

        $commandWasDone = false;
        foreach ($this->locator->getScheduler()->getScheduled() as $scheduled) {
            /** @var Event $scheduled */
            if ($scheduled instanceof SyncTeaser && $scheduled->get('target_ref')->equals(NodeRef::fromNode($article))) {
                $commandWasDone = true;
                break;
            }
        }

        $this->assertTrue(
            $commandWasDone,
            'sendAt should have been done for sync teaser (by target_ref) after onNodeUpdated called.'
        );
    }
}
