curator-php
=============

[![Build Status](https://api.travis-ci.org/triniti/curator-php.svg)](https://travis-ci.org/triniti/curator-php)
[![Code Climate](https://codeclimate.com/github/triniti/curator-php/badges/gpa.svg)](https://codeclimate.com/github/triniti/curator-php)
[![Test Coverage](https://codeclimate.com/github/triniti/curator-php/badges/coverage.svg)](https://codeclimate.com/github/triniti/curator-php/coverage)

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
