<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\NcrProjector;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Pbjx\EventSubscriberTrait;
use Gdbots\Pbjx\Pbjx;
use Triniti\Schemas\Curator\Command\UpdateGalleryImageCountV1;

class NcrGalleryProjector extends NcrProjector
{
    use EventSubscriberTrait;

    public static function getSubscribedEvents()
    {
        $vendor = MessageResolver::getDefaultVendor();
        return [
            "{$vendor}:dam:event:asset-created"         => 'onAssetCreated',
            "{$vendor}:dam:event:asset-deleted"         => 'onAssetDeletedOrExpired',
            "{$vendor}:dam:event:asset-expired"         => 'onAssetDeletedOrExpired',
            'triniti:dam:event:gallery-asset-reordered' => 'onGalleryAssetReordered',
        ];
    }

//    /**
//     * {@inheritdoc}
//     */
//    public static function getSubscribedEvents()
//    {
//        $assetCurie = ImageAssetV1Mixin::findOne()->getCurie();
//        $curie = GalleryV1Mixin::findOne()->getCurie();
//
//        $damVendor = $assetCurie->getVendor();
//        $damPackage = $assetCurie->getPackage();
//
//        return [
//            "{$curie->getVendor()}:{$curie->getPackage()}:event:*"     => 'onEvent',
//            "{$damVendor}:{$damPackage}:event:asset-created"           => 'onAssetCreated',
//            "{$damVendor}:{$damPackage}:event:asset-deleted"           => 'onAssetDeletedOrExpired',
//            "{$damVendor}:{$damPackage}:event:asset-expired"           => 'onAssetDeletedOrExpired',
//            "{$damVendor}:{$damPackage}:event:gallery-asset-reordered" => 'onGalleryAssetReordered',
//        ];
//    }

    public function onAssetCreated(Message $event, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        /** @var Message $node */
        $node = $event->get('node');
        if (!$node::schema()->hasMixin('triniti:dam:mixin:image-asset') || !$node->has('gallery_ref')) {
            return;
        }

        $this->updateGalleryImageCount($event, $node->get('gallery_ref'), $pbjx);
    }

    public function onGalleryUpdated(Message $event, Pbjx $pbjx): void
    {
        $this->onNodeUpdated($event, $pbjx);
        $this->updateGalleryImageCount($event, $event->get('node_ref'), $pbjx);
    }

    public function onGalleryAssetReordered(Message $event, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        if ($event->has('gallery_ref')) {
            $this->updateGalleryImageCount($event, $event->get('gallery_ref'), $pbjx);
        }

        if ($event->has('old_gallery_ref')) {
            $this->updateGalleryImageCount($event, $event->get('old_gallery_ref'), $pbjx);
        }
    }

    protected function updateGalleryImageCount(Message $event, NodeRef $nodeRef, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        static $jobs = [];
        if (isset($jobs[$nodeRef->toString()])) {
            // it's possible to get a bunch of asset events in one batch but
            // we only need to count the gallery images one time per request
            return;
        }

        $jobs[$nodeRef->toString()] = true;
        $command = UpdateGalleryImageCountV1::create()->set('node_ref', $nodeRef);
        $pbjx->copyContext($event, $command);
        $command
            ->set('ctx_correlator_ref', $event->generateMessageRef())
            ->clear('ctx_app');

        $pbjx->sendAt($command, strtotime('+300 seconds'), "{$nodeRef}.update-gallery-image-count");
    }

//    /**
//     * @param Message $event
//     * @param Pbjx    $pbjx
//     */
//    public function onAssetDeletedOrExpired(Message $event, Pbjx $pbjx): void
//    {
//        if ($event->isReplay()) {
//            return;
//        }
//
//        try {
//            $node = $this->ncr->getNode($event->get('node_ref'), false, $this->createNcrContext($event));
//        } catch (\Throwable $e) {
//            return;
//        }
//
//        if (!$node instanceof ImageAsset || !$node->has('gallery_ref')) {
//            return;
//        }
//
//        $this->updateGalleryImageCount($event, $node->get('gallery_ref'), $pbjx);
//    }
//
//    /**
//     * @param Message $event
//     * @param Pbjx    $pbjx
//     */
//    public function onGalleryAssetReordered(Message $event, Pbjx $pbjx): void
//    {
//        if ($event->isReplay()) {
//            return;
//        }
//
//        if ($event->has('gallery_ref')) {
//            $this->updateGalleryImageCount($event, $event->get('gallery_ref'), $pbjx);
//        }
//
//        if ($event->has('old_gallery_ref')) {
//            $this->updateGalleryImageCount($event, $event->get('old_gallery_ref'), $pbjx);
//        }
//    }
//
//    /**
//     * @param NodeCreated $event
//     * @param Pbjx        $pbjx
//     */
//    public function onGalleryCreated(NodeCreated $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeCreated($event, $pbjx);
//    }
//
//    /**
//     * @param NodeDeleted $event
//     * @param Pbjx        $pbjx
//     */
//    public function onGalleryDeleted(NodeDeleted $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeDeleted($event, $pbjx);
//    }
//
//    /**
//     * @param NodeExpired $event
//     * @param Pbjx        $pbjx
//     */
//    public function onGalleryExpired(NodeExpired $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeExpired($event, $pbjx);
//    }
//
//    /**
//     * @param GalleryImageCountUpdated $event
//     * @param Pbjx                     $pbjx
//     */
//    public function onGalleryImageCountUpdated(GalleryImageCountUpdated $event, Pbjx $pbjx): void
//    {
//        $node = $this->ncr->getNode($event->get('node_ref'), true, $this->createNcrContext($event));
//        $node->set('image_count', $event->get('image_count'));
//        $this->updateAndIndexNode($node, $event, $pbjx);
//    }
//
//    /**
//     * @param NodeMarkedAsDraft $event
//     * @param Pbjx              $pbjx
//     */
//    public function onGalleryMarkedAsDraft(NodeMarkedAsDraft $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeMarkedAsDraft($event, $pbjx);
//    }
//
//    /**
//     * @param NodeMarkedAsPending $event
//     * @param Pbjx                $pbjx
//     */
//    public function onGalleryMarkedAsPending(NodeMarkedAsPending $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeMarkedAsPending($event, $pbjx);
//    }
//
//    /**
//     * @param NodePublished $event
//     * @param Pbjx          $pbjx
//     */
//    public function onGalleryPublished(NodePublished $event, Pbjx $pbjx): void
//    {
//        $this->handleNodePublished($event, $pbjx);
//        $this->updateGalleryImageCount($event, $event->get('node_ref'), $pbjx);
//    }
//
//    /**
//     * @param NodeRenamed $event
//     * @param Pbjx        $pbjx
//     */
//    public function onGalleryRenamed(NodeRenamed $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeRenamed($event, $pbjx);
//    }
//
//    /**
//     * @param NodeScheduled $event
//     * @param Pbjx          $pbjx
//     */
//    public function onGalleryScheduled(NodeScheduled $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeScheduled($event, $pbjx);
//    }
//
//    /**
//     * @param NodeUnpublished $event
//     * @param Pbjx            $pbjx
//     */
//    public function onGalleryUnpublished(NodeUnpublished $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeUnpublished($event, $pbjx);
//    }
//
//    /**
//     * @param NodeUpdated $event
//     * @param Pbjx        $pbjx
//     */
//    public function onGalleryUpdated(NodeUpdated $event, Pbjx $pbjx): void
//    {
//        $this->handleNodeUpdated($event, $pbjx);
//        $this->updateGalleryImageCount($event, $event->get('node_ref'), $pbjx);
//    }
//
//    /**
//     * @param Message $event
//     * @param NodeRef $nodeRef
//     * @param Pbjx    $pbjx
//     */
//    protected function updateGalleryImageCount(Message $event, NodeRef $nodeRef, Pbjx $pbjx): void
//    {
//        if ($event->isReplay()) {
//            return;
//        }
//
//        static $jobs = [];
//        if (isset($jobs[$nodeRef->toString()])) {
//            // it's possible to get a bunch of asset events in one batch but
//            // we only need to count the gallery images one time per request
//            return;
//        }
//
//        $jobs[$nodeRef->toString()] = true;
//        $command = UpdateGalleryImageCountV1Mixin::findOne()->createMessage()->set('node_ref', $nodeRef);
//        $pbjx->copyContext($event, $command);
//        $command
//            ->set('ctx_correlator_ref', $event->generateMessageRef())
//            ->clear('ctx_app');
//
//        $pbjx->sendAt($command, strtotime('+300 seconds'), "{$nodeRef}.update-gallery-image-count");
//    }
}
