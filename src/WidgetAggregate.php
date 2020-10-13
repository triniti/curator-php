<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Aggregate;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;

class WidgetAggregate extends Aggregate
{
    protected function __construct(Message $node, Pbjx $pbjx, bool $syncAllEvents = false)
    {
        parent::__construct($node, $pbjx, $syncAllEvents);
        $this->enforceWidgetRules($this->node);
    }

    protected function enrichNodeCreated(Message $event): void
    {
        $this->enforceWidgetRules($event->get('node'));
        parent::enrichNodeCreated($event);
    }

    protected function enrichNodeUpdated(Message $event): void
    {
        $this->enforceWidgetRules($event->get('new_node'));
        parent::enrichNodeUpdated($event);
    }

    protected function enforceWidgetRules(Message $node): void
    {
        /**
         * a widget can only be "published". if it gets deleted
         * and later updated it will be published again.
         */
        $node->set('status', NodeStatus::PUBLISHED());
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
        $newName = str_replace('Widget', 'Node', $name);
        if ($newName !== $name && is_callable([$this, $newName])) {
            return $this->$newName(...$arguments);
        }
    }
}
