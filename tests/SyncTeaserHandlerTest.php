<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\SyncTeaserV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Ncr\Repository\InMemoryNcr;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Event\NodeCreatedV1;
use Gdbots\Schemas\Ncr\Event\NodeUpdatedV1;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\SyncTeaserHandler;
use Triniti\Curator\TeaserTransformer;
use Triniti\Sys\Flags;

final class SyncTeaserHandlerTest extends AbstractPbjxTest
{
    public function testHandleSyncByTargetRefWithoutTeasers(): void
    {
        $ncr = new InMemoryNcr();
        $article = ArticleV1::create()
            ->set('title', 'article title');
        $ncr->putNode($article);

        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
        $syncTeaserHandler = new SyncTeaserHandler($ncr, new Flags($ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        foreach ($this->pbjx->getEventStore()->pipeAllEvents() as [$event, $streamId]) {
            $this->assertTrue($event instanceof NodeCreatedV1);
            $this->assertTrue($event->get('node')->get('target_ref')->equals($article->generateNodeRef()));
        }
    }

    public function testHandleSyncByTargetRefWithTeasers(): void
    {
        $ncr = new MockNcr();
        $article = ArticleV1::create()
            ->set('title', 'article title')
            ->set('status', NodeStatus::PUBLISHED());
        $teaser1 = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true)
            ->set('title', 'teaser title 1');
        $teaser2 = ArticleTeaserV1::create()
            ->set('target_ref', NodeRef::fromNode($article))
            ->set('sync_with_target', true)
            ->set('title', 'teaser title 2');

        $ncr->putNode($article);
        $ncr->putNode($teaser1);
        $ncr->putNode($teaser2);

        $command = SyncTeaserV1::create()->set('target_ref', NodeRef::fromNode($article));
        $syncTeaserHandler = new SyncTeaserHandler($ncr, new Flags($ncr, 'acme:flagset:test'), new TeaserTransformer());
        $syncTeaserHandler->handleCommand($command, $this->pbjx);

        foreach ([$teaser1, $teaser2] as $teaser) {
            foreach ($this->pbjx->getEventStore()->pipeEvents(StreamId::fromNodeRef($teaser->generateNodeRef())) as $event) {
                $this->assertTrue($event instanceof NodeUpdatedV1);
                $this->assertTrue($event->get('node_ref')->equals($teaser->generateNodeRef()));
                $this->assertSame('article title', $event->get('new_node')->get('title'));
            }
        }
    }
}
