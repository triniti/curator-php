<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator\Enricher;

use Acme\Schemas\News\Event\ArticleMarkedAsDraftV1;
use Acme\Schemas\News\Event\ArticlePublishedV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Ncr\Event\BeforePutNodeEvent;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\Enricher\TeaserableEnricher;
use Triniti\Tests\Curator\AbstractPbjxTest;

final class TeaserableEnricherTest extends AbstractPbjxTest
{
    public function testEnrichWithOrderDateWhenNotPublished(): void
    {
        $node = ArticleV1::create()->set('published_at', new \DateTime('+2 weeks'));
        $event = ArticleMarkedAsDraftV1::create()
            ->set('node_ref', NodeRef::fromNode($node));

        $pbjxEvent = new BeforePutNodeEvent($node, $event);
        $enricher = new TeaserableEnricher();
        $enricher->enrichWithOrderDate($pbjxEvent);

        $expected = $node->get('created_at')->toDateTime()->format('c');
        $actual = $node->get('order_date')->format('c');

        $this->assertSame($expected, $actual, 'order_date should match created_at');
    }

    public function testEnrichWithOrderDateWhenPublished(): void
    {
        $node = ArticleV1::create()->set('order_date', new \DateTime('-1 month'));
        $nodeRef = NodeRef::fromNode($node);
        $publishedAt = new \DateTime();

        $event = ArticlePublishedV1::create()
            ->set('node_ref', $nodeRef)
            ->set('published_at', $publishedAt);

        $pbjxEvent = new BeforePutNodeEvent($node, $event);
        $enricher = new TeaserableEnricher();
        $enricher->enrichWithOrderDate($pbjxEvent);

        $expected = $publishedAt->format('c');
        $actual = $node->get('order_date')->format('c');

        $this->assertSame($expected, $actual, 'order_date should match published_at');
    }
}
