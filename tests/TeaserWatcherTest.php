<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Event\TeaserPublishedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\News\Node\ArticleV1;
use Triniti\Curator\TeaserWatcher;
use Triniti\Schemas\Curator\Command\RemoveTeaserSlottingV1;

final class TeaserWatcherTest extends AbstractPbjxTest
{
    public function setup(): void
    {
        parent::setup();
        $this->pbjx = new MockPbjx($this->locator);
    }

    public function testOnTeaserPublished(): void
    {
        $node = ArticleV1::create();
        $this->ncr->putNode($node);
        $nodeRef = $node->generateNodeRef();
        $teaser = ArticleTeaserV1::create()
            ->set('target_ref', $nodeRef)
            ->addToMap('slotting', 'home', 1);
        $this->ncr->putNode($teaser);
        $teaserRef = $teaser->generateNodeRef();
        $watcher = new TeaserWatcher($this->ncr);
        $watcher->onNodePublished(TeaserPublishedV1::create()->set('node_ref', $teaserRef), $this->pbjx);
        $sentCommand = $this->pbjx->getSent()[0];
        $this->assertInstanceOf(RemoveTeaserSlottingV1::class, $sentCommand);
        $this->assertTrue($teaserRef->equals($sentCommand->get('except_ref')));
        $this->assertSame(1, $sentCommand->get('slotting')['home']);
    }
}
