# CHANGELOG for 1.x
This changelog references the relevant changes done in 1.x versions.


## v1.4.0
* Auto-generate and sync teasers for teaserable content.


## v1.3.0
* Implement slotting on search teasers request handler.
* Require php `>=7.4`.


## v1.2.1
* Add `null` return in `RenderPromotionRequestHandler::renderWidget`.


## v1.2.0
* When `context` format is `json`, simply include `search_response` when creating `renderWidgetResponse` and skip html rendering.


## v1.1.0
* Add `UpdateGalleryImageCountHandler` and change `NcrGalleryProjector` to send commands to update image count rather than updating gallery directly.


## v1.0.0
* First stable version.
