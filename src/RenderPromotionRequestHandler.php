<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\PbjxHelperTrait;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Triniti\Schemas\Curator\Enum\SearchPromotionsSort;
use Triniti\Schemas\Curator\Mixin\RenderPromotionRequest\RenderPromotionRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderPromotionResponse\RenderPromotionResponseV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\SearchPromotionsRequest\SearchPromotionsRequestV1Mixin;

class RenderPromotionRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;
    use PbjxHelperTrait;

    /** @var Ncr */
    protected $ncr;

    /** @var LoggerInterface */
    protected $logger;

    public static function handlesCuries(): array
    {
        return [
            RenderPromotionRequestV1Mixin::findOne()->getCurie(),
        ];
    }

    public function __construct(Ncr $ncr, ?LoggerInterface $logger = null)
    {
        $this->ncr = $ncr;
        $this->logger = $logger ?: new NullLogger();
    }

    protected function handle(Message $request, Pbjx $pbjx): Message
    {
        $response = RenderPromotionResponseV1Mixin::findOne()->createMessage();
        $promotion = $this->getPromotion($request, $pbjx);
        if (null === $promotion) {
            return $response;
        }

        $response->set('promotion', $promotion);

        if (NodeStatus::DELETED()->equals($promotion->get('status'))) {
            // a deleted promotion cannot promote
            return $response;
        }

        /** @var Message $context */
        $context = $request->get('context');
        $widgets = [];

        foreach ($promotion->get('widget_refs', []) as $widgetRef) {
            $widget = $this->renderWidget($widgetRef, $request, $context, $pbjx);
            if (null !== $widget) {
                $widgets[] = $widget;
            }
        }

        $slotName = $context->get('promotion_slot');
        /** @var Message $slot */
        foreach ($promotion->get('slots', []) as $slot) {
            if (!$slot->has('widget_ref') || $slotName !== $slot->get('name')) {
                continue;
            }

            $context = clone $context;
            $context->addToMap('strings', 'rendering', (string)$slot->get('rendering'));
            $widget = $this->renderWidget($slot->get('widget_ref'), $request, $context, $pbjx);
            if (null !== $widget) {
                $widgets[] = $widget;
            }
        }

        return $response->addToList('widgets', $widgets);
    }

    protected function getPromotion(Message $request, Pbjx $pbjx): ?Message
    {
        if (!$request->has('promotion_ref')) {
            return $this->findPromotion($request, $pbjx);
        }

        try {
            return $this->ncr->getNode(
                $request->get('promotion_ref'), false, $this->createNcrContext($request)
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Unable to getPromotion for request [{pbj_schema}]',
                [
                    'exception'  => $e,
                    'pbj_schema' => $request->schema()->getId()->toString(),
                    'pbj'        => $request->toArray(),
                ]
            );
        }

        return null;
    }

    protected function findPromotion(Message $request, Pbjx $pbjx): ?Message
    {
        if (!$request->has('slot')) {
            return null;
        }

        try {
            $searchRequest = SearchPromotionsRequestV1Mixin::findOne()->createMessage()
                ->set('count', 1)
                ->set('status', NodeStatus::PUBLISHED())
                ->set('sort', SearchPromotionsSort::PRIORITY_DESC())
                ->set('slot', $request->get('slot'))
                ->set('render_at', $request->get('render_at') ?: $request->get('occurred_at')->toDateTime());

            $response = $pbjx->copyContext($request, $searchRequest)->request($searchRequest);
            if (!$response->has('nodes')) {
                return null;
            }

            return $response->getFromListAt('nodes', 0);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Unable to findPromotion for request [{pbj_schema}]',
                [
                    'exception'  => $e,
                    'pbj_schema' => $request->schema()->getId()->toString(),
                    'pbj'        => $request->toArray(),
                ]
            );
        }

        return null;
    }

    protected function renderWidget(NodeRef $widgetRef, Message $request, Message $context, Pbjx $pbjx): ?Message
    {
        try {
            $renderRequest = RenderWidgetRequestV1Mixin::findOne()->createMessage()
                ->set('widget_ref', $widgetRef)
                ->set('context', $context);
            return $pbjx->copyContext($request, $renderRequest)->request($renderRequest);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Unable to renderWidget for request [{pbj_schema}]',
                [
                    'exception'      => $e,
                    'pbj_schema'     => $request->schema()->getId()->toString(),
                    'pbj'            => $request->toArray(),
                    'render_context' => $context->toArray(),
                ]
            );
        }

        return null;
    }
}
