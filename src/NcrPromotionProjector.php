<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractNodeProjector;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\EventSubscriberTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Mixin\NodeCreated\NodeCreated;
use Gdbots\Schemas\Ncr\Mixin\NodeDeleted\NodeDeleted;
use Gdbots\Schemas\Ncr\Mixin\NodeExpired\NodeExpired;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsDraft\NodeMarkedAsDraft;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsPending\NodeMarkedAsPending;
use Gdbots\Schemas\Ncr\Mixin\NodePublished\NodePublished;
use Gdbots\Schemas\Ncr\Mixin\NodeScheduled\NodeScheduled;
use Gdbots\Schemas\Ncr\Mixin\NodeUnpublished\NodeUnpublished;
use Gdbots\Schemas\Ncr\Mixin\NodeUpdated\NodeUpdated;
use Triniti\Schemas\Curator\Mixin\Promotion\PromotionV1Mixin;

class NcrPromotionProjector extends AbstractNodeProjector implements EventSubscriber
{
    use EventSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $curie = PromotionV1Mixin::findOne()->getCurie();
        return [
            "{$curie->getVendor()}:{$curie->getPackage()}:event:*" => 'onEvent',
        ];
    }

    /**
     * @param NodeCreated $event
     * @param Pbjx        $pbjx
     */
    public function onPromotionCreated(NodeCreated $event, Pbjx $pbjx): void
    {
        $this->handleNodeCreated($event, $pbjx);
    }

    /**
     * @param NodeDeleted $event
     * @param Pbjx        $pbjx
     */
    public function onPromotionDeleted(NodeDeleted $event, Pbjx $pbjx): void
    {
        $this->handleNodeDeleted($event, $pbjx);
    }

    /**
     * @param NodeExpired $event
     * @param Pbjx        $pbjx
     */
    public function onPromotionExpired(NodeExpired $event, Pbjx $pbjx): void
    {
        $this->handleNodeExpired($event, $pbjx);
    }

    /**
     * @param NodeMarkedAsDraft $event
     * @param Pbjx              $pbjx
     */
    public function onPromotionMarkedAsDraft(NodeMarkedAsDraft $event, Pbjx $pbjx): void
    {
        $this->handleNodeMarkedAsDraft($event, $pbjx);
    }

    /**
     * @param NodeMarkedAsPending $event
     * @param Pbjx                $pbjx
     */
    public function onPromotionMarkedAsPending(NodeMarkedAsPending $event, Pbjx $pbjx): void
    {
        $this->handleNodeMarkedAsPending($event, $pbjx);
    }

    /**
     * @param NodePublished $event
     * @param Pbjx          $pbjx
     */
    public function onPromotionPublished(NodePublished $event, Pbjx $pbjx): void
    {
        $this->handleNodePublished($event, $pbjx);
    }

    /**
     * @param NodeScheduled $event
     * @param Pbjx          $pbjx
     */
    public function onPromotionScheduled(NodeScheduled $event, Pbjx $pbjx): void
    {
        $this->handleNodeScheduled($event, $pbjx);
    }

    /**
     * @param NodeUnpublished $event
     * @param Pbjx            $pbjx
     */
    public function onPromotionUnpublished(NodeUnpublished $event, Pbjx $pbjx): void
    {
        $this->handleNodeUnpublished($event, $pbjx);
    }

    /**
     * @param NodeUpdated $event
     * @param Pbjx        $pbjx
     */
    public function onPromotionUpdated(NodeUpdated $event, Pbjx $pbjx): void
    {
        $this->handleNodeUpdated($event, $pbjx);
    }
}
