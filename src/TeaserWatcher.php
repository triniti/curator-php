<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Triniti\Schemas\Curator\Command\RemoveTeaserSlottingV1;

class TeaserWatcher implements EventSubscriber
{
    protected Ncr $ncr;

    public function __construct(Ncr $ncr)
    {
        $this->ncr = $ncr;
    }

    public static function getSubscribedEvents()
    {
        return [
            'gdbots:ncr:mixin:node-published'               => 'onNodePublished',
            'gdbots:ncr:mixin:node-updated'                 => 'onNodeUpdated',
            'triniti:curator:event:teaser-slotting-removed' => 'onTeaserSlottingRemoved',
        ];
    }

    public function onNodePublished(Message $event, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        $node = $this->ncr->getNode($event->get('node_ref'));

        if (!$node::schema()->hasMixin('triniti:curator:mixin:teaser')) {
            return;
        }

        if (!$node->has('slotting')) {
            return;
        }

        $command = RemoveTeaserSlottingV1::create()
            ->set('except_ref', $event->get('node_ref'));

        foreach ($node->get('slotting') as $key => $value) {
            $command->addToMap('slotting', $key, $value);
        }

        $pbjx->copyContext($event, $command);
        $command->clear('ctx_app');
        $pbjx->sendAt($command, strtotime('+5 seconds'));
    }
}
