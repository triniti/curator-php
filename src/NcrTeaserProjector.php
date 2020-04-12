<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractNodeProjector;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\EventSubscriberTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\ExpireNode\ExpireNode;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\Mixin\NodeCreated\NodeCreated;
use Gdbots\Schemas\Ncr\Mixin\NodeDeleted\NodeDeleted;
use Gdbots\Schemas\Ncr\Mixin\NodeExpired\NodeExpired;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsDraft\NodeMarkedAsDraft;
use Gdbots\Schemas\Ncr\Mixin\NodeMarkedAsPending\NodeMarkedAsPending;
use Gdbots\Schemas\Ncr\Mixin\NodePublished\NodePublished;
use Gdbots\Schemas\Ncr\Mixin\NodeScheduled\NodeScheduled;
use Gdbots\Schemas\Ncr\Mixin\NodeUnpublished\NodeUnpublished;
use Gdbots\Schemas\Ncr\Mixin\NodeUpdated\NodeUpdated;
use Gdbots\Schemas\Ncr\Mixin\PublishNode\PublishNode;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Psr\Cache\CacheItemPoolInterface;
use Triniti\Schemas\Curator\Mixin\RemoveTeaserSlotting\RemoveTeaserSlottingV1Mixin;
use Triniti\Schemas\Curator\Mixin\Teaser\TeaserV1Mixin;

class NcrTeaserProjector extends AbstractNodeProjector implements EventSubscriber
{
    use EventSubscriberTrait;

    /** @var CacheItemPoolInterface */
    protected $cache;

    /**
     * @param Ncr                    $ncr
     * @param NcrSearch              $ncrSearch
     * @param CacheItemPoolInterface $cache
     * @param bool                   $indexOnReplay
     */
    public function __construct(
        Ncr $ncr,
        NcrSearch $ncrSearch,
        CacheItemPoolInterface $cache,
        bool $indexOnReplay = false
    ) {
        parent::__construct($ncr, $ncrSearch, $indexOnReplay);
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        /** @var Schema $schema */
        $schema = TeaserV1Mixin::findAll()[0];
        $curie = $schema->getCurie();
        return [
            "{$curie->getVendor()}:{$curie->getPackage()}:event:*" => 'onEvent',
        ];
    }

    /**
     * @param NodeCreated $event
     * @param Pbjx        $pbjx
     */
    public function onTeaserCreated(NodeCreated $event, Pbjx $pbjx): void
    {
        $this->handleNodeCreated($event, $pbjx);
    }

    /**
     * @param NodeDeleted $event
     * @param Pbjx        $pbjx
     */
    public function onTeaserDeleted(NodeDeleted $event, Pbjx $pbjx): void
    {
        $this->handleNodeDeleted($event, $pbjx);
    }

    /**
     * @param NodeExpired $event
     * @param Pbjx        $pbjx
     */
    public function onTeaserExpired(NodeExpired $event, Pbjx $pbjx): void
    {
        $this->handleNodeExpired($event, $pbjx);
    }

    /**
     * @param NodeMarkedAsDraft $event
     * @param Pbjx              $pbjx
     */
    public function onTeaserMarkedAsDraft(NodeMarkedAsDraft $event, Pbjx $pbjx): void
    {
        $this->handleNodeMarkedAsDraft($event, $pbjx);
    }

    /**
     * @param NodeMarkedAsPending $event
     * @param Pbjx                $pbjx
     */
    public function onTeaserMarkedAsPending(NodeMarkedAsPending $event, Pbjx $pbjx): void
    {
        $this->handleNodeMarkedAsPending($event, $pbjx);
    }

    /**
     * @param NodePublished $event
     * @param Pbjx          $pbjx
     */
    public function onTeaserPublished(NodePublished $event, Pbjx $pbjx): void
    {
        $this->handleNodePublished($event, $pbjx);
        if ($event->isReplay()) {
            return;
        }

        $node = $this->ncr->getNode($event->get('node_ref'), false, $this->createNcrContext($event));
        if (!$node->has('slotting')) {
            return;
        }

        /** @var Command $command */
        $command = $this->createRemoveTeaserSlotting($node, $event, $pbjx)
            ->set('except_ref', $event->get('node_ref'));

        foreach ($node->get('slotting') as $key => $value) {
            $command->addToMap('slotting', $key, $value);
        }

        $pbjx->copyContext($event, $command);
        $command->clear('ctx_app');
        $pbjx->sendAt($command, strtotime('+5 seconds'));
    }

    /**
     * @param NodeScheduled $event
     * @param Pbjx          $pbjx
     */
    public function onTeaserScheduled(NodeScheduled $event, Pbjx $pbjx): void
    {
        $this->handleNodeScheduled($event, $pbjx);
    }

    /**
     * @param Message|Event $event
     * @param Pbjx          $pbjx
     */
    public function onTeaserSlottingRemoved(Message $event, Pbjx $pbjx): void
    {
        $node = $this->ncr->getNode($event->get('node_ref'), true, $this->createNcrContext($event));

        $cacheKeys = [];
        foreach ($event->get('slotting_keys', []) as $key) {
            $node->removeFromMap('slotting', $key);
            $cacheKeys[] = "curator.slotting.{$key}.php";
        }

        $this->updateAndIndexNode($node, $event, $pbjx);

        if ($event->isReplay()) {
            return;
        }

        $this->cache->deleteItems($cacheKeys);
    }

    /**
     * @param NodeUnpublished $event
     * @param Pbjx            $pbjx
     */
    public function onTeaserUnpublished(NodeUnpublished $event, Pbjx $pbjx): void
    {
        $this->handleNodeUnpublished($event, $pbjx);
    }

    /**
     * @param NodeUpdated $event
     * @param Pbjx        $pbjx
     */
    public function onTeaserUpdated(NodeUpdated $event, Pbjx $pbjx): void
    {
        $this->handleNodeUpdated($event, $pbjx);
        if ($event->isReplay() || !$event->has('old_node')) {
            return;
        }

        /** @var Message $oldNode */
        $oldNode = $event->get('old_node');

        /** @var Message $newNode */
        $newNode = $event->get('new_node');

        if (!NodeStatus::PUBLISHED()->equals($newNode->get('status'))) {
            return;
        }

        $oldSlotting = $oldNode->get('slotting', []);
        $newSlotting = $newNode->get('slotting', []);
        ksort($oldSlotting);
        ksort($newSlotting);

        if ($oldSlotting === $newSlotting || empty($newSlotting)) {
            return;
        }

        /** @var Command $command */
        $command = $this->createRemoveTeaserSlotting($newNode, $event, $pbjx)
            ->set('except_ref', $event->get('node_ref'));

        foreach ($newNode->get('slotting') as $key => $value) {
            $command->addToMap('slotting', $key, $value);
        }

        $pbjx->copyContext($event, $command);
        $command->clear('ctx_app');
        $pbjx->sendAt($command, strtotime('+5 seconds'));
    }

    /**
     * {@inheritdoc}
     */
    protected function createExpireNode(Node $node, Event $event, Pbjx $pbjx): ExpireNode
    {
        $curie = $node::schema()->getCurie();
        $commandCurie = "{$curie->getVendor()}:{$curie->getPackage()}:command:expire-teaser";

        /** @var ExpireNode $class */
        $class = MessageResolver::resolveCurie(SchemaCurie::fromString($commandCurie));
        return $class::create();
    }

    /**
     * {@inheritdoc}
     */
    protected function createPublishNode(Node $node, Event $event, Pbjx $pbjx): PublishNode
    {
        $curie = $node::schema()->getCurie();
        $commandCurie = "{$curie->getVendor()}:{$curie->getPackage()}:command:publish-teaser";

        /** @var PublishNode $class */
        $class = MessageResolver::resolveCurie(SchemaCurie::fromString($commandCurie));
        return $class::create();
    }

    /**
     * @param Message $node
     * @param Message $event
     * @param Pbjx    $pbjx
     *
     * @return Message
     */
    protected function createRemoveTeaserSlotting(Message $node, Message $event, Pbjx $pbjx): Message
    {
        return RemoveTeaserSlottingV1Mixin::findOne()->createMessage();
    }
}
