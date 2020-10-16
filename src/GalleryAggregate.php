<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Aggregate;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Triniti\Schemas\Curator\Event\GalleryImageCountUpdatedV1;
use Triniti\Schemas\Dam\Request\SearchAssetsRequestV1;

class GalleryAggregate extends Aggregate
{
    public function updateGalleryImageCount(Message $command, Pbjx $pbjx): void
    {
        $imageCount = $this->getImageCount($command, $pbjx);
        if ($this->node->get('image_count') === $imageCount) {
            return;
        }
        $event = GalleryImageCountUpdatedV1::create()
            ->set('node_ref', $this->node->generateNodeRef())
            ->set('image_count', $imageCount);
        $this->recordEvent($event);
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
