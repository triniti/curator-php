<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\PbjxHelperTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Triniti\Schemas\Common\RenderContext;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequest;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderWidgetResponse\RenderWidgetResponse;
use Triniti\Schemas\Curator\Mixin\RenderWidgetResponse\RenderWidgetResponseV1Mixin;
use Triniti\Schemas\Curator\Mixin\Widget\Widget;
use Triniti\Schemas\Curator\Mixin\WidgetHasSearchRequest\WidgetHasSearchRequest;
use Triniti\Schemas\Curator\Mixin\WidgetSearchRequest\WidgetSearchRequest;
use Triniti\Schemas\Curator\Mixin\WidgetSearchResponse\WidgetSearchResponse;

class RenderWidgetRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;
    use PbjxHelperTrait;

    /** @var Ncr */
    protected $ncr;

    /** @var \Twig_Environment */
    protected $twig;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param Ncr               $ncr
     * @param \Twig_Environment $twig
     * @param LoggerInterface   $logger
     */
    public function __construct(Ncr $ncr, \Twig_Environment $twig, ?LoggerInterface $logger = null)
    {
        $this->ncr = $ncr;
        $this->twig = $twig;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param RenderWidgetRequest $request
     * @param Pbjx                $pbjx
     *
     * @return RenderWidgetResponse
     */
    protected function handle(RenderWidgetRequest $request, Pbjx $pbjx): RenderWidgetResponse
    {
        $response = $this->createRenderWidgetResponse($request, $pbjx);
        $widget = $this->getWidget($request, $pbjx);

        if (null === $widget) {
            return $response;
        }

        $searchResponse = $this->runWidgetSearchRequest($widget, $request, $pbjx);

        /** @var RenderContext $context */
        $context = $request->get('context');
        $platform = $context->get('platform', 'web');
        $section = $context->has('section') ? "{$context->get('section')}/" : '';
        $deviceView = $context->has('device_view') ? ".{$context->get('device_view')}" : '';
        $format = $context->has('format') ? ".{$context->get('format')}" : '';

        $template = strtolower(str_replace(
            '-',
            '_',
            "@curator_widgets/{$platform}/{$section}%s/%s{$deviceView}{$format}.twig"
        ));

        $loader = $this->twig->getLoader();
        $curie = $widget::schema()->getCurie();
        $widgetName = str_replace('-', '_', $curie->getMessage());
        $name = sprintf($template, $widgetName, $widgetName);

        if ($context->has('device_view') && !$loader->exists($name)) {
            $name = str_replace($deviceView, '', $name);
        }

        $hasNodes = null !== $searchResponse ? $searchResponse->has('nodes') : false;

        try {
            $html = $this->twig->render($name, [
                'pbj'             => $widget,
                'pbj_name'        => $widgetName,
                'context'         => $context,
                'render_request'  => $request,
                'search_response' => $searchResponse,
                'has_nodes'       => $hasNodes,
                'device_view'     => $context->get('device_view'),
                'viewer_country'  => $context->get('viewer_country'),
            ]);
        } catch (\Throwable $e) {
            if ($this->twig->isDebug()) {
                throw $e;
            }

            $this->logger->warning(
                'Unable to render [{curie}] with template [{twig_template}].',
                [
                    'exception'      => $e,
                    'curie'          => $curie->toString(),
                    'twig_template'  => $name,
                    'pbj'            => $widget->toArray(),
                    'render_context' => $context->toArray(),
                ]
            );

            $html = null;
        }

        return $response->set('html', $html);
    }

    /**
     * @param RenderWidgetRequest $request
     * @param Pbjx                $pbjx
     *
     * @return Widget
     */
    protected function getWidget(RenderWidgetRequest $request, Pbjx $pbjx): ?Widget
    {
        if ($request->has('widget')) {
            return $request->get('widget');
        }

        if (!$request->has('widget_ref')) {
            return null;
        }

        try {
            /** @var Widget $widget */
            $widget = $this->ncr->getNode(
                $request->get('widget_ref'),
                false,
                $this->createNcrContext($request)
            );
            return $widget;
        } catch (\Throwable $e) {
            if ($this->twig->isDebug()) {
                throw $e;
            }

            $this->logger->warning(
                'Unable to getWidget for request [{pbj_schema}]',
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
     * @param Widget              $widget
     * @param RenderWidgetRequest $request
     * @param Pbjx                $pbjx
     *
     * @return WidgetSearchResponse
     */
    protected function runWidgetSearchRequest(
        Widget $widget,
        RenderWidgetRequest $request,
        Pbjx $pbjx
    ): ?WidgetSearchResponse {
        if (!$widget instanceof WidgetHasSearchRequest || !$widget->has('search_request')) {
            return null;
        }

        /** @var WidgetSearchRequest $searchRequest */
        $searchRequest = clone $widget->get('search_request');

        /*
         * a search request is stored with the widget so these fields
         * need to be reset so they are correct for when the request
         * is actually running, which is now.  not now now, as of me
         * writing this comment right now, but the now when the now
         * is at runtime.
         */
        foreach ($searchRequest::schema()->getMixin('gdbots:pbjx:mixin:request')->getFields() as $field) {
            $searchRequest->clear($field->getName());
        }

        /*
         * widgets are, at the time of writing this, for the consumers so we only
         * want to include published content when running the search request.
         */
        if ($searchRequest instanceof SearchNodesRequest) {
            $searchRequest->set('status', NodeStatus::PUBLISHED());
        }

        try {
            /** @var WidgetSearchResponse $response */
            $response = $pbjx->copyContext($request, $searchRequest)->request($searchRequest);
            return $response;
        } catch (\Throwable $e) {
            if ($this->twig->isDebug()) {
                throw $e;
            }

            $this->logger->warning(
                'Unable to run widget search request [{pbj_schema}]',
                [
                    'exception'  => $e,
                    'pbj_schema' => $searchRequest->schema()->getId()->toString(),
                    'pbj'        => $searchRequest->toArray(),
                ]
            );
        }

        return null;
    }

    /**
     * @param RenderWidgetRequest $request
     * @param Pbjx                $pbjx
     *
     * @return RenderWidgetResponse
     */
    protected function createRenderWidgetResponse(RenderWidgetRequest $request, Pbjx $pbjx): RenderWidgetResponse
    {
        /** @var RenderWidgetResponse $response */
        $response = RenderWidgetResponseV1Mixin::findOne()->createMessage();
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            RenderWidgetRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
