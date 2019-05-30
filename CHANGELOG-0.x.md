# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.1.8
* Update `NcrGalleryProjector::onGalleryAssetReordered` to handle remove asset from gallery.
* Update `NcrGalleryProjector::onAssetCreated` event listener priority.


## v0.1.7
* Update `NcrGalleryProjector::onGalleryAssetReordered` event listener priority. 
* In `NcrGalleryProjector` update image count on node update events as well.


## v0.1.6
* In `SearchPromotionsRequestHandler` assume date is an immutable one.


## v0.1.5
* Update gallery `image_count` when new image asset nodes are added.


## v0.1.4
* Add more search fields in `SearchGalleriesRequestHandler`, `SearchTeasersRequestHandler`, `SearchTimelinesRequestHandler`.
* Add logic to `TeaserableEnricher` to ensure `order_date` field auto updates to `published_at` when the last event was `NodePublished`.
* Add find template logic to `RenderWidgetRequestHandler` so developer experience is better.


## v0.1.3
* Ensure all widgets are published when updated.  Cheap "restore" option.
* Allow deleted widgets to render.
* Add `has_nodes` variable to twig context when rendering widgets.  Will be true when search response has nodes.


## v0.1.2
* Log caught exceptions in `RenderPromotionRequestHandler` `renderWidget`.


## v0.1.1
* Add handlers for `triniti:curator:mixin:render-promotion-request` and `triniti:curator:mixin:render-widget-request`.
* Add twig extension with functions `curator_render_promotion` and `curator_render_widget`.
* Add `TeaserableEnricher` to ensure `order_date` field is always populated.


## v0.1.0
* Initial version.
