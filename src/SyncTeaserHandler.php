<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Common\Util\StringUtils;
use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\CommandHandlerTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Schemas\Curator\Mixin\SyncTeaser\SyncTeaser;
use Triniti\Schemas\Curator\Mixin\Teaser\TeaserV1Mixin;
use Triniti\Schemas\Curator\Mixin\TeaserHasTarget\TeaserHasTarget;
use Triniti\Sys\Flags;

class SyncTeaserHandler implements CommandHandler
{
    const DISABLED_FLAG_NAME = 'teaser_sync_disabled';
    const AUTOCREATE_DISABLED_FLAG_NAME = 'teaser_autocreate_disabled';
    const AUTOCREATE_TYPE_DISABLED_FLAG_NAME = 'teaser_autocreate_%s_disabled';

    use CommandHandlerTrait;
    use SyncTeaserTrait;

    protected Flags $flags;
    protected TeaserTransformer $transformer;

    public function __construct(Ncr $ncr, Flags $flags, TeaserTransformer $transformer)
    {
        $this->ncr = $ncr;
        $this->flags = $flags;
        $this->transformer = $transformer;
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        $schema = TeaserV1Mixin::findAll()[0];
        $curie = $schema->getCurie();
        return [
            SchemaCurie::fromString("{$curie->getVendor()}:{$curie->getPackage()}:command:sync-teaser"),
        ];
    }

    /**
     * @param SyncTeaser $command
     * @param Pbjx       $pbjx
     */
    protected function handle(SyncTeaser $command, Pbjx $pbjx): void
    {
        if ($this->flags->getBoolean(self::DISABLED_FLAG_NAME)) {
            return;
        }

        if ($command->has('target_ref')) {
            $this->handleSyncByTargetRef($command, $pbjx);
            return;
        }

        if ($command->has('teaser_ref')) {
            $this->handleSyncByTeaserRef($command, $pbjx);
        }
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    protected function isNodeSupported(Node $node): bool
    {
        return $node instanceof TeaserHasTarget;
    }

    /**
     * When syncing by target_ref, any existing teasers for that target that
     * are set to sync_with_target will be synced.
     *
     * If no teasers exist for that target, one will be created.
     *
     * @param SyncTeaser $command
     * @param Pbjx       $pbjx
     */
    protected function handleSyncByTargetRef(SyncTeaser $command, Pbjx $pbjx): void
    {
        /** @var NodeRef $targetRef */
        $targetRef = $command->get('target_ref');
        $teasers = $this->getTeasers($targetRef);

        $target = $this->ncr->getNode($targetRef, true);
        if (count($teasers['all']) > 0) {
            $this->updateTeasers($command, $pbjx, $teasers['sync'], $target);
            return;
        }

        if ($this->flags->getBoolean(self::AUTOCREATE_DISABLED_FLAG_NAME)) {
            return;
        }

        $typeDisabledFlag = sprintf(
            self::AUTOCREATE_TYPE_DISABLED_FLAG_NAME,
            StringUtils::toSnakeFromSlug($target::schema()->getQName()->getMessage())
        );

        if ($this->flags->getBoolean($typeDisabledFlag)) {
            return;
        }

        if (!$this->shouldAutoCreateTeaser($target)) {
            return;
        }

        if (NodeStatus::PUBLISHED()->equals($target->get('status'))) {
            /*
             * for now we don't create teasers for already published targets
             * simply because we are not yet handling the auto publishing
             * of the teaser when that scenario occurs.
             *
             * todo: solve auto publishing on newly created teasers.
             */
            return;
        }

        static $class = null;
        if (null === $class) {
            $class = MessageResolver::resolveCurie(
                SchemaCurie::fromString("{$targetRef->getVendor()}:curator:event:teaser-created")
            );
        }

        $teaser = $this->transformer::transform($target);
        $event = $class::create()->set('node', $teaser);
        $pbjx->copyContext($command, $event);
        $teaser
            ->clear('updated_at')
            ->clear('updater_ref')
            ->set('created_at', $event->get('occurred_at'))
            ->set('creator_ref', $event->get('ctx_user_ref', $target->get('updater_ref', $target->get('creator_ref'))))
            ->set('last_event_ref', $event->generateMessageRef());

        $teaserRef = NodeRef::fromNode($teaser);
        $streamId = StreamId::fromString(sprintf('%s.history:%s', $teaserRef->getLabel(), $teaserRef->getId()));
        $pbjx->getEventStore()->putEvents($streamId, [$event]);
    }

    /**
     * When syncing by teaser_ref, the teaser will always be synced regardless
     * of the value of sync_with_target.
     *
     * @param Message $command
     * @param Pbjx    $pbjx
     */
    protected function handleSyncByTeaserRef(Message $command, Pbjx $pbjx): void
    {
        $teaser = $this->ncr->getNode($command->get('teaser_ref'), true);
        if (!$this->isNodeSupported($teaser)) {
            return;
        }

        $target = $this->ncr->getNode($teaser->get('target_ref'), true);
        $this->updateTeasers($command, $pbjx, [$teaser], $target);
    }

    /**
     * @param Message   $causator
     * @param Pbjx      $pbjx
     * @param Message[] $teasers
     * @param Message   $target
     */
    protected function updateTeasers(Message $causator, Pbjx $pbjx, array $teasers, Message $target): void
    {
        static $class = null;
        if (null === $class) {
            $class = MessageResolver::resolveCurie(
                SchemaCurie::fromString("{$causator::schema()->getCurie()->getVendor()}:curator:event:teaser-updated")
            );
        }

        foreach ($teasers as $teaser) {
            $nodeRef = NodeRef::fromNode($teaser);
            $newTeaser = $this->transformer::transform($target, (clone $teaser));
            $event = $class::create()
                ->set('node_ref', $nodeRef)
                ->set('old_node', $teaser)
                ->set('new_node', $newTeaser);
            $pbjx->copyContext($causator, $event);

            $newTeaser
                ->set('updated_at', $event->get('occurred_at'))
                ->set('updater_ref', $event->get('ctx_user_ref', $target->get('updater_ref')))
                ->set('last_event_ref', $event->generateMessageRef());

            $pbjx->triggerLifecycle($event);
            $event->freeze();

            if ($event->get('old_etag') === $event->get('new_etag')) {
                continue;
            }

            $streamId = StreamId::fromString(sprintf('%s.history:%s', $nodeRef->getLabel(), $nodeRef->getId()));
            $pbjx->getEventStore()->putEvents($streamId, [$event]);
        }
    }

    protected function shouldAutoCreateTeaser(Message $target): bool
    {
        $types = [
            'article' => true,
            'gallery' => true,
            'video'   => true,
        ];

        return $types[$target::schema()->getQName()->getMessage()] ?? false;
    }
}
