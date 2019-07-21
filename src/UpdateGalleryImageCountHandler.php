<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractNodeCommandHandler;
use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Schemas\Curator\Mixin\GalleryImageCountUpdated\GalleryImageCountUpdatedV1Mixin;
use Triniti\Schemas\Curator\Mixin\UpdateGalleryImageCount\UpdateGalleryImageCountV1Mixin;
use Triniti\Schemas\Dam\Mixin\SearchAssetsRequest\SearchAssetsRequestV1Mixin;

class UpdateGalleryImageCountHandler extends AbstractNodeCommandHandler
{
    /** @var Ncr */
    protected $ncr;

    /**
     * @param Ncr $ncr
     */
    public function __construct(Ncr $ncr)
    {
        $this->ncr = $ncr;
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            UpdateGalleryImageCountV1Mixin::findOne()->getCurie(),
        ];
    }

    /**
     * @param Message $command
     * @param Pbjx    $pbjx
     */
    protected function handle(Message $command, Pbjx $pbjx): void
    {
        /** @var NodeRef $nodeRef */
        $nodeRef = $command->get('node_ref');

        $gallery = $this->ncr->getNode($nodeRef, true, $this->createNcrContext($command));
        $imageCount = $this->getImageCount($command, $pbjx);
        if ($gallery->get('image_count') === $imageCount) {
            return;
        }

        $event = $this->createGalleryImageCountUpdated($command, $pbjx);
        $event->set('node_ref', $nodeRef);
        $event->set('image_count', $imageCount);
        $this->putEvents($command, $pbjx, $this->createStreamId($nodeRef, $command, $event), [$event]);
    }

    /**
     * @param Message $command
     * @param Pbjx    $pbjx
     *
     * @return Message
     */
    protected function createGalleryImageCountUpdated(Message $command, Pbjx $pbjx): Message
    {
        $event = GalleryImageCountUpdatedV1Mixin::findOne()->createMessage();
        $pbjx->copyContext($command, $event);
        return $event;
    }

    /**
     * @param Message $command
     * @param Pbjx    $pbjx
     *
     * @return int
     */
    protected function getImageCount(Message $command, Pbjx $pbjx): int
    {
        $request = SearchAssetsRequestV1Mixin::findOne()->createMessage();
        $request
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
}
