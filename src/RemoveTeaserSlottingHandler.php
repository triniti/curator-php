<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Schemas\Curator\Event\TeaserSlottingRemovedV1;
use Triniti\Schemas\Curator\Request\SearchTeasersRequestV1;

class RemoveTeaserSlottingHandler implements CommandHandler
{
    public static function handlesCuries(): array
    {
        // deprecated mixins, will be removed in 3.x
        $curies = MessageResolver::findAllUsingMixin('triniti:curator:mixin:remove-teaser-slotting', false);
        $curies[] = 'triniti:curator:command:remove-teaser-slotting';
        return $curies;
    }

    public function handleCommand(Message $command, Pbjx $pbjx): void
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

        $request = $this->createSearchTeasersRequest($command, $pbjx)
            ->set('q', implode(' OR ', $query))
            ->set('status', NodeStatus::PUBLISHED());
        $response = $pbjx->request($request);

        /** @var Message $node */
        foreach ($response->get('nodes', []) as $node) {
            $nodeRef = $node->generateNodeRef();
            if (null !== $exceptRef && $exceptRef->equals($nodeRef)) {
                continue;
            }

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
            $pbjx->getEventStore()->putEvents(
                StreamId::fromString(sprintf('%s:%s:%s', $nodeRef->getVendor(), $nodeRef->getLabel(), $nodeRef->getId())),
                [$event],
            );
        }
    }

    protected function createSearchTeasersRequest(Message $command, Pbjx $pbjx): Message
    {
        $request = SearchTeasersRequestV1::create();
        $pbjx->copyContext($command, $request);
        return $request;
    }

    protected function createTeaserSlottingRemoved(Message $command, Pbjx $pbjx): Message
    {
        $event = TeaserSlottingRemovedV1::create();
        $pbjx->copyContext($command, $event);
        return $event;
    }
}
