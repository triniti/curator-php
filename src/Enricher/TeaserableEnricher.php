<?php
declare(strict_types=1);

namespace Triniti\Curator\Enricher;

use Gdbots\Ncr\Event\BeforePutNodeEvent;
use Gdbots\Pbjx\DependencyInjection\PbjxEnricher;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Ncr\Mixin\NodePublished\NodePublished;

final class TeaserableEnricher implements EventSubscriber, PbjxEnricher
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'triniti:curator:mixin:teaser.enrich'              => 'enrichWithOrderDate',
            'triniti:curator:mixin:teaser.before_put_node'     => 'enrichWithOrderDate',
            'triniti:curator:mixin:teaserable.enrich'          => 'enrichWithOrderDate',
            'triniti:curator:mixin:teaserable.before_put_node' => 'enrichWithOrderDate',
        ];
    }

    /**
     * Ensures that a teaserable node always has its order_date
     * field populated.  This is important because we use this
     * date for sorting virtually all lists of content on the site.
     *
     * @param PbjxEvent $pbjxEvent
     */
    public function enrichWithOrderDate(PbjxEvent $pbjxEvent): void
    {
        $node = $pbjxEvent->getMessage();
        if ($node->isFrozen()) {
            return;
        }

        if ($pbjxEvent instanceof BeforePutNodeEvent) {
            $lastEvent = $pbjxEvent->getLastEvent();
            if ($lastEvent instanceof NodePublished) {
                $node->set('order_date', $lastEvent->get('published_at'));
                return;
            }
        }

        if ($node->has('order_date')) {
            return;
        }

        $node->set('order_date', $node->get('created_at')->toDateTime());
    }
}
