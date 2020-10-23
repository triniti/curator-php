<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Aggregate;
use Gdbots\Pbj\Message;
use Gdbots\Schemas\Ncr\Event\NodeUpdatedV1;
use Triniti\Schemas\Curator\Event\TeaserSlottingRemovedV1;

class TeaserAggregate extends Aggregate
{
    public function removeTeaserSlotting(Message $command): void
    {
        $event = $this->createTeaserSlottingRemoved($command);
        $event->set('node_ref', $this->nodeRef);
        $slottingKeys = [];

        foreach ($command->get('slotting') as $key => $value) {
            $currentSlot = $this->node->getFromMap('slotting', $key, 0);
            if ($currentSlot === $value) {
                $slottingKeys[] = $key;
            }
        }

        if (empty($slottingKeys)) {
            return;
        }

        $event->addToSet('slotting_keys', $slottingKeys);
        $this->recordEvent($event);
    }

    public function syncTeaser(Message $command, Message $newTeaser): void
    {
        $oldETag = static::generateEtag($this->node);
        $newETag = static::generateEtag($newTeaser);
        if ($oldETag === $newETag) {
            return;
        }

        $event = NodeUpdatedV1::create()
            ->set('node_ref', $this->nodeRef)
            ->set('old_node', $this->node)
            ->set('old_etag', $oldETag)
            ->set('new_node', $newTeaser)
            ->set('new_etag', $newETag);
        $this->pbjx->copyContext($command, $event);
        $this->recordEvent($event);
    }

    protected function createTeaserSlottingRemoved(Message $command): Message
    {
        $event = TeaserSlottingRemovedV1::create();
        $this->pbjx->copyContext($command, $event);
        return $event;
    }

    /**
     * This is for legacy uses of command/event mixins for common
     * ncr operations. It will be removed in 3.x.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $newName = str_replace('Teaser', 'Node', $name);
        if ($newName !== $name && is_callable([$this, $newName])) {
            return $this->$newName(...$arguments);
        }
    }
}
