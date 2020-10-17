<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Schemas\Curator\Event\GalleryImageCountUpdatedV1;
use Triniti\Schemas\Dam\Request\SearchAssetsRequestV1;

class UpdateGalleryImageCountHandler implements CommandHandler
{
    protected Ncr $ncr;

    public function __construct(Ncr $ncr)
    {
        $this->ncr = $ncr;
    }

    public static function handlesCuries(): array
    {
        // deprecated mixins, will be removed in 3.x
        $curies = MessageResolver::findAllUsingMixin('triniti:curator:mixin:update-gallery-image-count', false);
        $curies[] = 'triniti:curator:command:update-gallery-image-count';
        return $curies;
    }

    public function handleCommand(Message $command, Pbjx $pbjx): void
    {
        /** @var NodeRef $nodeRef */
        $nodeRef = $command->get('node_ref');

        $gallery = $this->ncr->getNode($nodeRef, true);
        $imageCount = $this->getImageCount($command, $pbjx);
        if ($imageCount === $gallery->get('image_count')) {
            return;
        }

        $event = $this->createGalleryImageCountUpdated($command, $pbjx)
            ->set('node_ref', $nodeRef)
            ->set('image_count', $imageCount);
        $pbjx->getEventStore()->putEvents(
            StreamId::fromString(sprintf('%s:%s:%s', $nodeRef->getVendor(), $nodeRef->getLabel(), $nodeRef->getId())),
            [$event]
        );
    }

    protected function getImageCount(Message $command, Pbjx $pbjx): int
    {
        $request = SearchAssetsRequestV1::create()
            ->addToSet('types', ['image-asset'])
            ->set('count', 1)
            ->set('gallery_ref', $command->get('node_ref'))
            ->set('status', NodeStatus::PUBLISHED());

        try {
            return (int)$pbjx->copyContext($command, $request)->request($request)->get('total', 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function createGalleryImageCountUpdated(Message $command, Pbjx $pbjx): Message
    {
        $event = GalleryImageCountUpdatedV1::create();
        $pbjx->copyContext($command, $event);
        return $event;
    }
}
