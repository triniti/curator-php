<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Boost\Event\SponsorPublishedV1;
use Acme\Schemas\Boost\Node\SponsorV1;
use Acme\Schemas\Curator\Command\DeleteTeaserV1;
use Acme\Schemas\Curator\Command\ExpireTeaserV1;
use Acme\Schemas\Curator\Command\PublishTeaserV1;
use Acme\Schemas\Curator\Command\UnpublishTeaserV1;
use Acme\Schemas\Curator\Event\WidgetCreatedV1;
use Acme\Schemas\Curator\Event\WidgetDeletedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\Curator\Node\BlogrollWidgetV1;
use Acme\Schemas\News\Event\ArticleCreatedV1;
use Acme\Schemas\News\Event\ArticleDeletedV1;
use Acme\Schemas\News\Event\ArticleExpiredV1;
use Acme\Schemas\News\Event\ArticlePublishedV1;
use Acme\Schemas\News\Event\ArticleScheduledV1;
use Acme\Schemas\News\Event\ArticleUnpublishedV1;
use Acme\Schemas\News\Event\ArticleUpdatedV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\IndexQuery;
use Gdbots\Ncr\IndexQueryResult;
use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Triniti\Curator\TeaserableWatcher;
use Triniti\Schemas\Curator\Command\SyncTeaserV1;

final class TeaserableWatcherTest extends AbstractPbjxTest
{
    public function setup(): void
    {
        parent::setup();
        $this->pbjx = new MockPbjx($this->locator);
        $this->ncr = new class implements Ncr {
            private array $nodes = [];

            public function createStorage(SchemaQName $qname, array $context = []): void
            {
                // TODO: Implement createStorage() method.
            }

            public function deleteNode(NodeRef $nodeRef, array $context = []): void
            {
                // TODO: Implement deleteNode() method.
            }

            public function describeStorage(SchemaQName $qname, array $context = []): string
            {
                // TODO: Implement describeStorage() method.
            }

            public function findNodeRefs(IndexQuery $query, array $context = []): IndexQueryResult
            {
                return new IndexQueryResult(new IndexQuery(SchemaQName::fromString('a:b'), 'alias', 'value'), array_keys($this->nodes));
            }

            public function getNode(NodeRef $nodeRef, bool $consistent = false, array $context = []): Message
            {
                if (!$this->hasNode($nodeRef)) {
                    throw NodeNotFound::forNodeRef($nodeRef);
                }

                $node = $this->nodes[$nodeRef->toString()];
                if ($node->isFrozen()) {
                    $node = $this->nodes[$nodeRef->toString()] = clone $node;
                }

                return $node;
            }

            public function getNodes(array $nodeRefs, bool $consistent = false, array $context = []): array
            {
                return array_filter($this->nodes, function (Message $node) {
                    return $this->hasNode($node->generateNodeRef());
                });
            }

            public function hasNode(NodeRef $nodeRef, bool $consistent = false, array $context = []): bool
            {
                return isset($this->nodes[$nodeRef->toString()]);
            }

            public function pipeNodeRefs(SchemaQName $qname, array $context = []): \Generator
            {
                // TODO: Implement pipeNodeRefs() method.
            }

            public function pipeNodes(SchemaQName $qname, array $context = []): \Generator
            {
                // TODO: Implement pipeNodes() method.
            }

            public function putNode(Message $node, ?string $expectedEtag = null, array $context = []): void
            {
                $this->nodes[$node->generateNodeRef()->toString()] = $node;
            }
        };
    }

    public function testOnNodeCreated(): void
    {
        $node = ArticleV1::create();
        $watcher = new TeaserableWatcher($this->ncr);
        $watcher->onNodeCreated(ArticleCreatedV1::create()->set('node', $node), $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(SyncTeaserV1::class, $sentCommand);
        $this->assertTrue($node->generateNodeRef()->equals($sentCommand->get('target_ref')));
    }

    public function testOnNodeCreatedIsReplay(): void
    {
        $node = ArticleV1::create();
        $watcher = new TeaserableWatcher($this->ncr);
        $event = ArticleCreatedV1::create()->set('node', $node);
        $event->isReplay(true);
        $watcher->onNodeCreated($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
    }

    public function testOnNodeCreatedIsUnsupported(): void
    {
        $node = BlogrollWidgetV1::create();
        $watcher = new TeaserableWatcher($this->ncr);
        $event = WidgetCreatedV1::create()->set('node', $node);
        $watcher->onNodeCreated($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
    }

    public function testOnNodeDeleted(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('status', NodeStatus::PUBLISHED());
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $watcher->onNodeDeleted(ArticleDeletedV1::create()->set('node_ref', $nodeRef), $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(DeleteTeaserV1::class, $sentCommand);
        $this->assertTrue($teaser->generateNodeRef()->equals($sentCommand->get('node_ref')));
    }

    public function testOnNodeDeletedIsReplay(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('status', NodeStatus::PUBLISHED());
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $event = ArticleDeletedV1::create()->set('node_ref', $nodeRef);
        $event->isReplay(true);
        $watcher->onNodeDeleted($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
    }

    public function testOnNodeDeletedIsUnsupported(): void
    {
        $node = BlogrollWidgetV1::create();
        $watcher = new TeaserableWatcher($this->ncr);
        $event = WidgetDeletedV1::create()->set('node_ref', $node->generateNodeRef());
        $watcher->onNodeDeleted($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
    }

    public function testOnNodeExpired(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('status', NodeStatus::PUBLISHED());
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $watcher->onNodeExpired(ArticleExpiredV1::create()->set('node_ref', $nodeRef), $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(ExpireTeaserV1::class, $sentCommand);
        $this->assertTrue($teaser->generateNodeRef()->equals($sentCommand->get('node_ref')));
    }

    public function testOnNodePublished(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $publishedAt = new \DateTime('2099-01-01');
        $event = ArticlePublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);
        $watcher->onNodePublished($event, $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(PublishTeaserV1::class, $sentCommand);
        $this->assertTrue($teaser->generateNodeRef()->equals($sentCommand->get('node_ref')));
        $this->assertSame($publishedAt->format('Y-m-d'), $sentCommand->get('publish_at')->format('Y-m-d'));
    }

    public function testOnNodePublishedIsReplay(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $publishedAt = new \DateTime('2099-01-01');
        $event = ArticlePublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);
        $event->isReplay(true);
        $watcher->onNodePublished($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
    }

    public function testOnNodePublishedIsUnsupported(): void
    {
        $node = SponsorV1::create();
        $watcher = new TeaserableWatcher($this->ncr);
        $event = SponsorPublishedV1::create()->set('node_ref', $node->generateNodeRef());
        $watcher->onNodePublished($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
    }

    public function testOnNodeScheduled(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('sync_with_target', true);
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $publishAt = new \DateTime('2099-01-01');
        $event = ArticleScheduledV1::create()
            ->set('node_ref', $nodeRef)
            ->set('publish_at', $publishAt);
        $watcher->onNodeScheduled($event, $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(PublishTeaserV1::class, $sentCommand);
        $this->assertTrue($teaser->generateNodeRef()->equals($sentCommand->get('node_ref')));
        $this->assertSame($publishAt->format('Y-m-d'), $sentCommand->get('publish_at')->format('Y-m-d'));
    }

    public function testOnNodeUnpublished(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->set('sync_with_target', true)
            ->set('status', NodeStatus::PUBLISHED());
        $this->ncr->putNode($teaser);
        $watcher = new TeaserableWatcher($this->ncr);
        $event = ArticleUnpublishedV1::create()
            ->set('node_ref', $nodeRef);
        $watcher->onNodeUnpublished($event, $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(UnpublishTeaserV1::class, $sentCommand);
        $this->assertTrue($teaser->generateNodeRef()->equals($sentCommand->get('node_ref')));
    }

    public function testOnNodeUpdated(): void
    {
        $node = ArticleV1::create();
        $nodeRef = $node->generateNodeRef();
        $watcher = new TeaserableWatcher($this->ncr);
        $event = ArticleUpdatedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('new_node', (clone $node)->set('title', 'new-title'));
        $watcher->onNodeCreated($event, $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(SyncTeaserV1::class, $sentCommand);
        $this->assertTrue($nodeRef->equals($sentCommand->get('target_ref')));
    }
}