curator-php
=============

[![Build Status](https://api.travis-ci.org/triniti/curator-php.svg)](https://travis-ci.org/triniti/curator-php)

Php library that provides implementations for __triniti:curator__ schemas. Using this library assumes that you've already created and compiled your own pbj classes using the [Pbjc](https://github.com/gdbots/pbjc-php) and are making use of the __"triniti:curator:mixin:*"__ mixins from [triniti/schemas](https://github.com/triniti/schemas).


## Symfony Integration
Enabling these services in a Symfony app is done by importing classes and letting Symfony autoconfigure and autowire them.

__config/packages/curator.yml:__

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Triniti\Curator\:
    resource: '%kernel.project_dir%/vendor/triniti/curator/src/**/*'

```


## Twig Extension
A few twig functions are provided to simplify rendering promotions and widgets.

> The Twig extension is automatically available if using Symfony autowiring.

### Twig Function: curator_find_promotion
Returns the promotion if found for the given slot.

__Arguments:__

+ `string $slot`


__Example:__

```txt
# page.html.twig

{# one promotion for the entire desktop-home screen #}
{% set promotion = curator_find_promotion("#{device_view}-home") %}

{{ curator_render_promotion_slots(promotion, {promotion_slot: 'header', section: 'header'}) }}
{{ curator_render_promotion_slots(promotion, {promotion_slot: 'footer', section: 'footer'}) }}

</body>
</html>
```

### Twig Function: curator_render_widget
Renders a widget using the request with mixin `triniti:curator:mixin:render-widget-request`. The `html` field from the response with mixin `triniti:curator:mixin:render-widget-response` is returned and twig renders the raw value.

__Arguments:__

+ `Widget|NodeRef|array|string $widget`
+ `RenderContext|array $context = []`
+ `bool $returnResponse = false` For when you want the raw render response.

__Example:__

```txt
# page.html.twig

{{ curator_render_widget('acme:slider-widget:4d2dd0bf-70af-4f5e-875b-fcd7db73fb78', {
  section: 'permalink',
  booleans: {
    enable_ads: true,
    dnt: false,
    autoplay_videos: false,
  },
  strings: {
    custom1: 'val1',
    custom2: 'val2',
    customN: 'val3',
  },
}) }}

</body>
</html>
```

This function will render the widget using Twig by resolving the context and the widget type to a Twig template.  The filename requested must be in the Twig [namespaced path](http://symfony.com/doc/current/templating/namespaced_paths.html) `@curator_widgets`.  All segments are optional except the platform, which defaults to "web".

__First template found is used:__

- `@curator_widgets/{$platform}/{$section}/{$widgetName}/{$widgetName}.{$deviceView}.twig`
- `@curator_widgets/{$platform}/{$section}/{$widgetName}/{$widgetName}.twig`
- `@curator_widgets/{$platform}/{$blockName}/{$widgetName}.{$deviceView}.twig`
- `@curator_widgets/{$platform}/{$blockName}/{$widgetName}.twig`
- `@curator_widgets/{$platform}/missing_widget.twig`

__Example output:__
> e.g. `@curator_widgets/web/blogroll/slider_widget/slider_widget.smartphone.twig`


```php
$output = $this->twig->render($name, [
    'pbj'             => $widget,
    'pbj_name'        => $widgetName, // e.g. slider_widget
    'context'         => $context,
    'render_request'  => $request,
    'search_response' => $searchResponse,
    'has_nodes'       => $hasNodes,
    'device_view'     => $context->get('device_view'),
    'viewer_country'  => $context->get('viewer_country'),
]);
```

### Twig Function: curator_render_promotion
Renders a promotion using the request with mixin `triniti:curator:mixin:render-promotion-request`. The response with mixin `triniti:curator:mixin:render-promotion-response` contains a `widgets` field which is an array of `triniti:curator:mixin:render-widget-response` messages.  All of the `html` fields from those are aggregated into a single string which is rendered by twig.

__Arguments:__

+ `string $slot`
+ `RenderContext|array $context = []`
+ `bool $returnResponse = false` For when you want the raw render response.


__Example:__

```txt
# page.html.twig

{{ curator_render_promotion('smartphone-home-sidebar', {
  section: 'permalink',
  booleans: {
    enable_ads: true,
    dnt: false,
    autoplay_videos: false,
  },
  strings: {
    custom1: 'val1',
    custom2: 'val2',
    customN: 'val3',
  },
}) }}

</body>
</html>
```


### Twig Function: curator_render_promotion_slots
Rendering a promotion's slots is very similar to `curator_render_promotion` except in this case you already know the promotion and are  telling twig to render all the `slots` which are `triniti:curator::slot` instances. All slots matching the render context `promotion_slot` value will be rendered.

> Using `triniti:curator::slot` allows you to render different code for server, client and lazy scenarios. See `triniti:curator:slot-rendering` enum.

__Arguments:__

+ `Message|NodeRef|string $promotionOrRef`
+ `RenderContext|array $context`
+ `bool $returnResponse = false` For when you want the raw render response.


__Example:__

```txt
# page.html.twig

{{ curator_render_promotion_slots('acme:promotion:4a0a55f2-c6ac-4044-bcc0-cb7e47be2509', {
  promotion_slot: 'jumbotron-top',
  section: 'jumbotron',
  booleans: {
    enable_ads: true,
    dnt: false,
    autoplay_videos: false,
  },
  strings: {
    custom1: 'val1',
    custom2: 'val2',
    customN: 'val3',
  },
}) }}

</body>
</html>
```
