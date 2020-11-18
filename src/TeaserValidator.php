<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\MessageRef;
use Gdbots\Pbjx\DependencyInjection\PbjxValidator;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Triniti\Curator\Exception\TargetNotPublished;
use Triniti\Schemas\Curator\Mixin\Teaser\TeaserV1Mixin;

class TeaserValidator implements EventSubscriber, PbjxValidator
{
    /** @var Ncr */
    protected $ncr;

    public function __construct(Ncr $ncr)
    {
        $this->ncr = $ncr;
    }

    public static function getSubscribedEvents()
    {
        return [
            'gdbots:ncr:mixin:publish-node.validate' => 'validatePublishNode',
        ];
    }

    public function validatePublishNode(PbjxEvent $pbjxEvent): void
    {
        $command = $pbjxEvent->getMessage();
        if (!$command->has('node_ref')) {
            return;
        }

        /** @var NodeRef $teaserRef */
        $teaserRef = $command->get('node_ref');
        if (!$this->isTeaser($teaserRef)) {
            return;
        }

        /** @var \DateTime $publishAt */
        $publishAt = $command->get('publish_at') ?: $command->get('occurred_at')->toDateTime();
        $now = time();

        if ($publishAt->getTimestamp() > $now) {
            // this teaser is being scheduled to publish and thus
            // we don't need to validate the target yet.
            return;
        }

        // if we are publishing this teaser as a result of its target being
        // published (effect of sync_with_target) then we ignore it.
        if ($command->has('ctx_causator_ref')) {
            /** @var MessageRef $causator */
            $causator = $command->get('ctx_causator_ref');
            if (strpos($causator->getCurie()->getMessage(), '-published')) {
                return;
            }
        }

        $teaser = $this->ncr->getNode($teaserRef);
        if (!$teaser->has('target_ref')) {
            return;
        }

        $target = $this->ncr->getNode($teaser->get('target_ref'));
        if (!NodeStatus::PUBLISHED()->equals($target->get('status'))) {
            throw new TargetNotPublished();
        }
    }

    protected function isTeaser(NodeRef $nodeRef): bool
    {
        static $validQNames = null;
        if (null === $validQNames) {
            $validQNames = [];
            foreach (TeaserV1Mixin::findAll() as $schema) {
                $validQNames[$schema->getQName()->toString()] = true;
            }
        }

        return isset($validQNames[$nodeRef->getQName()->toString()]);
    }
}
