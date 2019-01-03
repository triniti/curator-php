<?php
declare(strict_types=1);

namespace Triniti\Curator\Enricher;

use Gdbots\Pbjx\DependencyInjection\PbjxEnricher;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Triniti\Schemas\Curator\Mixin\Teaserable\Teaserable;

final class TeaserableEnricher implements EventSubscriber, PbjxEnricher
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'triniti:curator:mixin:teaserable.enrich' => 'enrichWithOrderDate',
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
        /** @var Teaserable $node */
        $node = $pbjxEvent->getMessage();
        if ($node->isFrozen() || $node->has('order_date')) {
            return;
        }

        if ($node->has('published_at')) {
            $node->set('order_date', $node->get('published_at'));
            return;
        }

        $node->set('order_date', $node->get('updated_at', $node->get('created_at'))->toDateTime());
    }
}
