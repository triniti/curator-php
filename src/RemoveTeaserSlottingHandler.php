<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractNodeCommandHandler;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Triniti\Schemas\Curator\Mixin\RemoveTeaserSlotting\RemoveTeaserSlottingV1Mixin;
use Triniti\Schemas\Curator\Mixin\SearchTeasersRequest\SearchTeasersRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\TeaserSlottingRemoved\TeaserSlottingRemovedV1Mixin;

class RemoveTeaserSlottingHandler extends AbstractNodeCommandHandler
{
    /**
     * @param Message|Command $command
     * @param Pbjx            $pbjx
     */
    protected function handle(Message $command, Pbjx $pbjx): void
    {
        if (!$command->has('slotting')) {
            return;
        }

        /** @var NodeRef $exceptRef */
        $exceptRef = $command->get('except_ref');

        $query = [];
        foreach ($command->get('slotting') as $key => $value) {
            $query[] = "slotting.{$key}:{$value}";
        }

        /** @var Request $request */
        $request = $this->createSearchTeasersRequest($command, $pbjx)
            ->set('q', implode(' OR ', $query))
            ->set('status', NodeStatus::PUBLISHED());

        $response = $pbjx->request($request);

        /** @var Node $node */
        foreach ($response->get('nodes', []) as $node) {
            $nodeRef = NodeRef::fromNode($node);
            if (null !== $exceptRef && $exceptRef->equals($nodeRef)) {
                continue;
            }

            /** @var Event $event */
            $event = $this->createTeaserSlottingRemoved($command, $pbjx);
            $event->set('node_ref', $nodeRef);
            $slottingKeys = [];

            foreach ($command->get('slotting') as $key => $value) {
                $currentSlot = $node->getFromMap('slotting', $key, 0);
                if ($currentSlot === $value) {
                    $slottingKeys[] = $key;
                }
            }

            if (empty($slottingKeys)) {
                continue;
            }

            $event->addToSet('slotting_keys', $slottingKeys);
            $this->putEvents($command, $pbjx, $this->createStreamId($nodeRef, $command, $event), [$event]);
        }
    }

    /**
     * @param Message $command
     * @param Pbjx    $pbjx
     *
     * @return Message
     */
    protected function createSearchTeasersRequest(Message $command, Pbjx $pbjx): Message
    {
        $request = SearchTeasersRequestV1Mixin::findOne()->createMessage();
        $pbjx->copyContext($command, $request);
        return $request;
    }

    /**
     * @param Message $command
     * @param Pbjx    $pbjx
     *
     * @return Message
     */
    protected function createTeaserSlottingRemoved(Message $command, Pbjx $pbjx): Message
    {
        $event = TeaserSlottingRemovedV1Mixin::findOne()->createMessage();
        $pbjx->copyContext($command, $event);
        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            RemoveTeaserSlottingV1Mixin::findOne()->getCurie(),
        ];
    }
}
