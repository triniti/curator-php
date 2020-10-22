<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Aggregate;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Schemas\Curator\Event\GalleryImageCountUpdatedV1;
use Triniti\Schemas\Dam\Request\SearchAssetsRequestV1;

class GalleryAggregate extends Aggregate
{
    public function updateGalleryImageCount(Message $command): void
    {
        $imageCount = $this->getImageCount($command);
        if ($imageCount === $this->node->get('image_count')) {
            return;
        }

        $event = $this->createGalleryImageCountUpdated($command)
            ->set('node_ref', $this->nodeRef)
            ->set('image_count', $imageCount);
        $this->pbjx->getEventStore()->putEvents(
            StreamId::fromString(sprintf('%s:%s:%s', $this->nodeRef->getVendor(), $this->nodeRef->getLabel(), $this->nodeRef->getId())),
            [$event]
        );
    }

    protected function getImageCount(Message $command): int
    {
        $request = SearchAssetsRequestV1::create()
            ->addToSet('types', ['image-asset'])
            ->set('count', 1)
            ->set('gallery_ref', $this->nodeRef)
            ->set('status', NodeStatus::PUBLISHED());

        try {
            return (int)$this->pbjx->copyContext($command, $request)->request($request)->get('total', 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function createGalleryImageCountUpdated(Message $command): Message
    {
        $event = GalleryImageCountUpdatedV1::create();
        $this->pbjx->copyContext($command, $event);
        return $event;
    }

    /**
     * This is for legacy uses for command/event mixins for common
     * ncr operations. It will be removed in 3.x.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $newName = str_replace('Gallery', 'Node', $name);
        if ($newName !== $name && is_callable([$this, $newName])) {
            return $this->$newName(...$arguments);
        }
    }
}
