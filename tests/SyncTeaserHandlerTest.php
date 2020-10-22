<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\SyncTeaserV1;
use Acme\Schemas\Curator\Event\TeaserCreatedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Ncr\Repository\InMemoryNcr;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Triniti\Curator\SyncTeaserHandler;
use Triniti\Curator\TeaserTransformer;
use Triniti\Sys\Flags;

final class SyncTeaserHandlerTest extends AbstractPbjxTest
{
    private InMemoryNcr $ncr;

    public function testHandleSyncByTargetRefWithoutTeasers(): void
    {
        $this->ncr = new InMemoryNcr();
        $article = ArticleV1::create()
            ->set('title', 'article title');
        $this->ncr->putNode($article);

        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        foreach ($this->pbjx->getEventStore()->pipeAllEvents() as [$event, $streamId]) {
            $this->assertTrue($event instanceof TeaserCreatedV1);
            $this->assertTrue($event->get('node')->get('target_ref')->equals($article->generateNodeRef()));
        }
    }

//    public function testHandleSyncByTargetRefWithTeasers(): void
//    {
//        $this->ncr = new MockNcr();
//        $article = ArticleV1::create()
//            ->set('title', 'article title')
//            ->set('status', NodeStatus::PUBLISHED());
//        $teaser1 = ArticleTeaserV1::create()
//            ->set('target_ref', NodeRef::fromNode($article))
//            ->set('sync_with_target', true)
//            ->set('title', 'teaser title 1')
//            ->set('etag', 'before');
//        $teaser2 = ArticleTeaserV1::create()
//            ->set('target_ref', NodeRef::fromNode($article))
//            ->set('sync_with_target', true)
//            ->set('title', 'teaser title 2')
//            ->set('etag', 'before');
//
//        $this->ncr->putNode($article);
//        $this->ncr->putNode($teaser1);
//        $this->ncr->putNode($teaser2);
//
//        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
//        $syncTeaserHandler = new SyncTeaserHandler($this->ncr, new Flags($this->ncr, 'acme:flagset:test'), new TeaserTransformer());
//        $syncTeaserHandler->handleCommand($command, $this->pbjx);
//
//        foreach ($this->pbjx->getEventStore()->pipeAllEvents() as [$event, $streamId]) {
//            var_dump($event);
//        }
//    }
}
