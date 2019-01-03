<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\PbjxHelperTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Triniti\Schemas\Curator\Enum\SearchPromotionsSort;
use Triniti\Schemas\Curator\Mixin\Promotion\Promotion;
use Triniti\Schemas\Curator\Mixin\RenderPromotionRequest\RenderPromotionRequest;
use Triniti\Schemas\Curator\Mixin\RenderPromotionRequest\RenderPromotionRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderPromotionResponse\RenderPromotionResponse;
use Triniti\Schemas\Curator\Mixin\RenderPromotionResponse\RenderPromotionResponseV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequest;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderWidgetResponse\RenderWidgetResponse;
use Triniti\Schemas\Curator\Mixin\SearchPromotionsRequest\SearchPromotionsRequest;
use Triniti\Schemas\Curator\Mixin\SearchPromotionsRequest\SearchPromotionsRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\SearchPromotionsResponse\SearchPromotionsResponse;

class RenderPromotionRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;
    use PbjxHelperTrait;

    /** @var Ncr */
    protected $ncr;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param Ncr             $ncr
     * @param LoggerInterface $logger
     */
    public function __construct(Ncr $ncr, ?LoggerInterface $logger = null)
    {
        $this->ncr = $ncr;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param RenderPromotionRequest $request
     * @param Pbjx                   $pbjx
     *
     * @return RenderPromotionResponse
     */
    protected function handle(RenderPromotionRequest $request, Pbjx $pbjx): RenderPromotionResponse
    {
        $response = $this->createRenderPromotionResponse($request, $pbjx);
        $promotion = $this->getPromotion($request, $pbjx);

        if (null === $promotion) {
            return $response;
        }

        $response->set('promotion', $promotion);

        if (NodeStatus::DELETED()->equals($promotion->get('status'))) {
            // a deleted promotion cannot promote
            return $response;
        }

        $widgets = [];
        foreach ($promotion->get('widget_refs', []) as $widgetRef) {
            $widget = $this->renderWidget($widgetRef, $request, $pbjx);
            if (null !== $widget) {
                $widgets[] = $widget;
            }
        }

        return $response->addToList('widgets', $widgets);
    }

    /**
     * Gets a promotion by its NodeRef if available or falls back to findPromotion.
     *
     * @param RenderPromotionRequest $request
     * @param Pbjx                   $pbjx
     *
     * @return Promotion
     */
    protected function getPromotion(RenderPromotionRequest $request, Pbjx $pbjx): ?Promotion
    {
        if (!$request->has('promotion_ref')) {
            return $this->findPromotion($request, $pbjx);
        }

        try {
            /** @var Promotion $promotion */
            $promotion = $this->ncr->getNode(
                $request->get('promotion_ref'),
                false,
                $this->createNcrContext($request)
            );
            return $promotion;
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

    /**
     * Finds the promotion that should render for a given slot/time/etc.
     *
     * @param RenderPromotionRequest $request
     * @param Pbjx                   $pbjx
     *
     * @return Promotion
     */
    protected function findPromotion(RenderPromotionRequest $request, Pbjx $pbjx): ?Promotion
    {
        if (!$request->has('slot')) {
            return null;
        }

        try {
            /** @var SearchPromotionsRequest $searchRequest */
            $searchRequest = SearchPromotionsRequestV1Mixin::findOne()->createMessage()
                ->set('count', 1)
                ->set('status', NodeStatus::PUBLISHED())
                ->set('sort', SearchPromotionsSort::PRIORITY_DESC())
                ->set('slot', $request->get('slot'))
                ->set('render_at', $request->get('render_at') ?: $request->get('occurred_at')->toDateTime());

            /** @var SearchPromotionsResponse $response */
            $response = $pbjx->copyContext($request, $searchRequest)->request($searchRequest);

            if (!$response->has('nodes')) {
                return null;
            }

            /** @var Promotion $promotion */
            $promotion = $response->getFromListAt('nodes', 0);
            return $promotion;
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

    /**
     * @param NodeRef                $widgetRef
     * @param RenderPromotionRequest $request
     * @param Pbjx                   $pbjx
     *
     * @return RenderWidgetResponse
     */
    protected function renderWidget(
        NodeRef $widgetRef,
        RenderPromotionRequest $request,
        Pbjx $pbjx
    ): ?RenderWidgetResponse {
        try {
            /** @var RenderWidgetRequest $renderRequest */
            $renderRequest = RenderWidgetRequestV1Mixin::findOne()->createMessage()
                ->set('widget_ref', $widgetRef)
                ->set('context', $request->get('context'));

            /** @var RenderWidgetResponse $response */
            $response = $pbjx->copyContext($request, $renderRequest)->request($renderRequest);
            return $response;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param RenderPromotionRequest $request
     * @param Pbjx                   $pbjx
     *
     * @return RenderPromotionResponse
     */
    protected function createRenderPromotionResponse(RenderPromotionRequest $request, Pbjx $pbjx): RenderPromotionResponse
    {
        /** @var RenderPromotionResponse $response */
        $response = RenderPromotionResponseV1Mixin::findOne()->createMessage();
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            RenderPromotionRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
