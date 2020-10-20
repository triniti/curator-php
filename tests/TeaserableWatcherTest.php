<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\DeleteTeaserV1;
use Acme\Schemas\Curator\Command\ExpireTeaserV1;
use Acme\Schemas\Curator\Command\PublishTeaserV1;
use Acme\Schemas\Curator\Command\UnpublishTeaserV1;
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
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Triniti\Curator\TeaserableWatcher;
use Triniti\Schemas\Curator\Command\SyncTeaserV1;

final class TeaserableWatcherTest extends AbstractPbjxTest
{
    public function setup(): void
    {
        parent::setup();
        $this->pbjx = new MockPbjx($this->locator);
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
        $event = ArticleCreatedV1::create()->set('node', $node);
        $event->isReplay(true);
        $watcher->onNodeCreated($event, $this->pbjx);
        $this->assertEmpty($this->pbjx->getSent());
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
}
