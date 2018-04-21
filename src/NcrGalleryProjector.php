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
use Gdbots\Schemas\Ncr\Mixin\NodeRenamed\NodeRenamed;
use Gdbots\Schemas\Ncr\Mixin\NodeScheduled\NodeScheduled;
use Gdbots\Schemas\Ncr\Mixin\NodeUnpublished\NodeUnpublished;
use Gdbots\Schemas\Ncr\Mixin\NodeUpdated\NodeUpdated;
use Triniti\Schemas\Curator\Mixin\Gallery\GalleryV1Mixin;

class NcrGalleryProjector extends AbstractNodeProjector implements EventSubscriber
{
    use EventSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $curie = GalleryV1Mixin::findOne()->getCurie();
        return [
            "{$curie->getVendor()}:{$curie->getPackage()}:event:*" => 'onEvent',
        ];
    }

    /**
     * @param NodeCreated $event
     * @param Pbjx        $pbjx
     */
    public function onGalleryCreated(NodeCreated $event, Pbjx $pbjx): void
    {
        $this->handleNodeCreated($event, $pbjx);
    }

    /**
     * @param NodeDeleted $event
     * @param Pbjx        $pbjx
     */
    public function onGalleryDeleted(NodeDeleted $event, Pbjx $pbjx): void
    {
        $this->handleNodeDeleted($event, $pbjx);
    }

    /**
     * @param NodeExpired $event
     * @param Pbjx        $pbjx
     */
    public function onGalleryExpired(NodeExpired $event, Pbjx $pbjx): void
    {
        $this->handleNodeExpired($event, $pbjx);
    }

    /**
     * @param NodeMarkedAsDraft $event
     * @param Pbjx              $pbjx
     */
    public function onGalleryMarkedAsDraft(NodeMarkedAsDraft $event, Pbjx $pbjx): void
    {
        $this->handleNodeMarkedAsDraft($event, $pbjx);
    }

    /**
     * @param NodeMarkedAsPending $event
     * @param Pbjx                $pbjx
     */
    public function onGalleryMarkedAsPending(NodeMarkedAsPending $event, Pbjx $pbjx): void
    {
        $this->handleNodeMarkedAsPending($event, $pbjx);
    }

    /**
     * @param NodePublished $event
     * @param Pbjx          $pbjx
     */
    public function onGalleryPublished(NodePublished $event, Pbjx $pbjx): void
    {
        $this->handleNodePublished($event, $pbjx);
    }

    /**
     * @param NodeRenamed $event
     * @param Pbjx        $pbjx
     */
    public function onGalleryRenamed(NodeRenamed $event, Pbjx $pbjx): void
    {
        $this->handleNodeRenamed($event, $pbjx);
    }

    /**
     * @param NodeScheduled $event
     * @param Pbjx          $pbjx
     */
    public function onGalleryScheduled(NodeScheduled $event, Pbjx $pbjx): void
    {
        $this->handleNodeScheduled($event, $pbjx);
    }

    /**
     * @param NodeUnpublished $event
     * @param Pbjx            $pbjx
     */
    public function onGalleryUnpublished(NodeUnpublished $event, Pbjx $pbjx): void
    {
        $this->handleNodeUnpublished($event, $pbjx);
    }

    /**
     * @param NodeUpdated $event
     * @param Pbjx        $pbjx
     */
    public function onGalleryUpdated(NodeUpdated $event, Pbjx $pbjx): void
    {
        $this->handleNodeUpdated($event, $pbjx);
    }
}
