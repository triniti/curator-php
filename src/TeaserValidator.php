<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\DependencyInjection\PbjxValidator;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
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

    public function validatePublishNode(Message $command, Pbjx $pbjx): void
    {
        if (!$command->has('node_ref')) {
            return;
        }

        /** @var NodeRef $teaserRef */
        $teaserRef = $command->get('node_ref');
        if (!$this->isTeaser($teaserRef)) {
            return;
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
