<?php
declare(strict_types=1);

namespace Triniti\Curator\Twig;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Serializer\PhpArraySerializer;
use Gdbots\Pbj\Serializer\Serializer;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Triniti\Schemas\Common\RenderContext;
use Triniti\Schemas\Common\RenderContextV1;
use Triniti\Schemas\Curator\Mixin\Promotion\Promotion;
use Triniti\Schemas\Curator\Mixin\RenderPromotionRequest\RenderPromotionRequest;
use Triniti\Schemas\Curator\Mixin\RenderPromotionRequest\RenderPromotionRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderPromotionResponse\RenderPromotionResponse;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequest;
use Triniti\Schemas\Curator\Mixin\RenderWidgetRequest\RenderWidgetRequestV1Mixin;
use Triniti\Schemas\Curator\Mixin\RenderWidgetResponse\RenderWidgetResponse;
use Triniti\Schemas\Curator\Mixin\Widget\Widget;

final class CuratorExtension extends \Twig_Extension
{
    /** @var PhpArraySerializer */
    private static $serializer;

    /** @var Pbjx */
    private $pbjx;

    /** @var RequestStack */
    private $requestStack;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Pbjx            $pbjx
     * @param RequestStack    $requestStack
     * @param LoggerInterface $logger
     */
    public function __construct(Pbjx $pbjx, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->pbjx = $pbjx;
        $this->requestStack = $requestStack;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction(
                'curator_render_widget',
                [$this, 'renderWidget'],
                ['needs_environment' => true, 'is_safe' => ['html']]
            ),

            new \Twig_SimpleFunction(
                'curator_render_promotion',
                [$this, 'renderPromotion'],
                ['needs_environment' => true, 'is_safe' => ['html']]
            ),
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'triniti_curator_extension';
    }

    /**
     * @param \Twig_Environment           $twig
     * @param Widget|NodeRef|array|string $widget
     * @param RenderContext|array         $context
     * @param bool                        $returnResponse For when you want the raw render response and not its html.
     *                                                    This is some next level shit here folks.
     *
     * @return string|null|RenderWidgetResponse
     */
    public function renderWidget(\Twig_Environment $twig, $widget, $context = [], bool $returnResponse = false)
    {
        try {
            /** @var RenderWidgetRequest $request */
            $request = RenderWidgetRequestV1Mixin::findOne()->createMessage();

            if (!$context instanceof RenderContext) {
                $container = $context['container'] ?? null;
                if ($container instanceof Message) {
                    unset($context['container']);
                }

                $context = RenderContextV1::fromArray($context);
                if ($container instanceof Message) {
                    $context->set('container', $container);
                }
            }

            $this->enrichContext($context);
            $request->set('context', $context);

            if ($widget instanceof Widget) {
                $request->set('widget', $widget);
            } elseif ($widget instanceof NodeRef) {
                $request->set('widget_ref', $widget);
            } elseif (is_string($widget)) {
                $request->set('widget_ref', NodeRef::fromString($widget));
            } elseif (is_array($widget)) {
                $widget = self::getSerializer()->deserialize($widget);
                $request->set('widget', $widget);
            } else {
                // no widget?, no problem
                return null;
            }

            // ensures permission check is bypassed
            $request->set('ctx_causator_ref', $request->generateMessageRef());

            /** @var RenderWidgetResponse $response */
            $response = $this->pbjx->request($request);

            return $returnResponse ? $response : trim($response->get('html', ''));
        } catch (\Throwable $e) {
            if ($twig->isDebug()) {
                throw $e;
            }

            $widgetRef = $widget instanceof Widget ? NodeRef::fromNode($widget) : $widget;
            $this->logger->warning(
                'curator_render_widget failed to render [{widget_ref}].',
                [
                    'exception'      => $e,
                    'widget_ref'     => (string)$widgetRef,
                    'widget'         => $widget instanceof Widget ? $widget->toArray() : $widget,
                    'render_context' => $context instanceof RenderContext ? $context->toArray() : $context,
                ]
            );
        }

        return null;
    }

    /**
     * @param \Twig_Environment   $twig
     * @param string              $slot
     * @param RenderContext|array $context
     * @param bool                $returnResponse For when you want the raw render response.
     *
     * @return string|null|RenderPromotionResponse
     */
    public function renderPromotion(\Twig_Environment $twig, string $slot, $context = [], bool $returnResponse = false)
    {
        try {
            /** @var RenderPromotionRequest $request */
            $request = RenderPromotionRequestV1Mixin::findOne()->createMessage();

            if (!$context instanceof RenderContext) {
                $container = $context['container'] ?? null;
                if ($container instanceof Message) {
                    unset($context['container']);
                }

                $context = RenderContextV1::fromArray($context);
                if ($container instanceof Message) {
                    $context->set('container', $container);
                }
            }

            $this->enrichContext($context);
            if (!$context->isFrozen()) {
                $context->set('promotion_slot', $slot);
            }

            $request->set('context', $context);
            $request->set('slot', $slot);

            // ensures permission check is bypassed
            $request->set('ctx_causator_ref', $request->generateMessageRef());

            /** @var RenderPromotionResponse $response */
            $response = $this->pbjx->request($request);

            if ($returnResponse) {
                return $response;
            }

            $html = ["<!-- start: promotion-slot ${slot} -->"];

            /** @var Promotion $promotion */
            $promotion = $response->get('promotion');
            if (null !== $promotion) {
                $html[] = trim($promotion->get('pre_render_code', ''));
            }

            /** @var RenderWidgetResponse $renderWidgetResponse */
            foreach ($response->get('widgets', []) as $renderWidgetResponse) {
                $html[] = trim($renderWidgetResponse->get('html', ''));
            }

            if (null !== $promotion) {
                $html[] = trim($promotion->get('post_render_code', ''));
            }

            $html[] = "<!-- end: promotion-slot ${slot} -->";

            return trim(implode(PHP_EOL, $html));
        } catch (\Throwable $e) {
            if ($twig->isDebug()) {
                throw $e;
            }

            $this->logger->warning(
                'curator_render_promotion failed to render slot [{slot}].',
                [
                    'exception'      => $e,
                    'slot'           => $slot,
                    'render_context' => $context instanceof RenderContext ? $context->toArray() : $context,
                ]
            );
        }

        return null;
    }

    /**
     * @param RenderContext $context
     */
    private function enrichContext(RenderContext $context): void
    {
        if ($context->isFrozen()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        foreach (['device_view', 'viewer_country'] as $key) {
            if (!$context->has($key) && $request->attributes->has($key)) {
                $context->set($key, $request->attributes->get($key));
            }
        }
    }

    /**
     * @return Serializer
     */
    private function getSerializer(): Serializer
    {
        if (null === self::$serializer) {
            self::$serializer = new PhpArraySerializer();
        }

        return self::$serializer;
    }
}
