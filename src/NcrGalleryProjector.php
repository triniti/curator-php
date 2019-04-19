<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractNodeProjector;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\EventSubscriberTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
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
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Triniti\Schemas\Curator\Mixin\Gallery\GalleryV1Mixin;
use Triniti\Schemas\Dam\Mixin\ImageAsset\ImageAsset;
use Triniti\Schemas\Dam\Mixin\ImageAsset\ImageAssetV1Mixin;
use Triniti\Schemas\Dam\Mixin\SearchAssetsRequest\SearchAssetsRequestV1Mixin;

class NcrGalleryProjector extends AbstractNodeProjector implements EventSubscriber
{
    use EventSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $assetCurie = ImageAssetV1Mixin::findOne()->getCurie();
        $curie = GalleryV1Mixin::findOne()->getCurie();

        $damVendor = $assetCurie->getVendor();
        $damPackage = $assetCurie->getPackage();

        return [
            "{$curie->getVendor()}:{$curie->getPackage()}:event:*"     => 'onEvent',
            "{$damVendor}:{$damPackage}:event:asset-created"           => 'onAssetCreated',
            "{$damVendor}:{$damPackage}:event:gallery-asset-reordered" => [['onGalleryAssetReordered', -5000]],
        ];
    }

    /**
     * @param Message $event
     * @param Pbjx    $pbjx
     */
    public function onAssetCreated(Message $event, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        /** @var Node $node */
        $node = $event->get('node');
        if (!$node instanceof ImageAsset || !$node->has('gallery_ref')) {
            return;
        }

        $this->updateImageCount($event, $node->get('gallery_ref'), $pbjx);
    }

    /**
     * @param Message $event
     * @param Pbjx    $pbjx
     */
    public function onGalleryAssetReordered(Message $event, Pbjx $pbjx): void
    {
        if ($event->isReplay() || !$event->has('gallery_ref')) {
            return;
        }

        $this->updateImageCount($event, $event->get('gallery_ref'), $pbjx);
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

    /**
     * {@inheritdoc}
     */
    protected function updateAndIndexNode(Node $node, Event $event, Pbjx $pbjx): void
    {
        if ($event->isReplay() || $node->isFrozen() || !$event instanceof NodeUpdated) {
            parent::updateAndIndexNode($node, $event, $pbjx);
            return;
        }

        $nodeRef = $event->get('node_ref') ?: NodeRef::fromNode($node);
        $node->set('image_count', $this->getImageCount($event, $nodeRef, $pbjx));
        parent::updateAndIndexNode($node, $event, $pbjx);
    }

    /**
     * @param Message $event
     * @param NodeRef $galleryRef
     * @param Pbjx    $pbjx
     */
    protected function updateImageCount(Message $event, NodeRef $galleryRef, Pbjx $pbjx): void
    {
        $node = $this->ncr->getNode($galleryRef, true, $this->createNcrContext($event));
        $oldCount = $node->get('image_count');
        $newCount = $this->getImageCount($event, $galleryRef, $pbjx);

        if ($oldCount === $newCount) {
            return;
        }

        $node->set('image_count', $newCount);
        $this->updateAndIndexNode($node, $event, $pbjx);
    }

    /**
     * @param Message $event
     * @param NodeRef $galleryRef
     * @param Pbjx    $pbjx
     *
     * @return int
     */
    protected function getImageCount(Message $event, NodeRef $galleryRef, Pbjx $pbjx): int
    {
        $request = SearchAssetsRequestV1Mixin::findOne()->createMessage();
        $request
            ->addToSet('types', ['image-asset'])
            ->set('count', 1)
            ->set('gallery_ref', $galleryRef)
            ->set('status', NodeStatus::PUBLISHED());

        try {
            return (int)$pbjx->copyContext($event, $request)->request($request)->get('total', 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
