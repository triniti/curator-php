# CHANGELOG for 1.x
This changelog references the relevant changes done in 1.x versions.


## v1.4.9
* Fix bug in `TeaserValidator::validatePublishNode` where publishing a teaser fails when `sync_with_target:true` and its being published as a result of the target being published.


## v1.4.8
* Fix invalid `TeaserValidator::validatePublishNode` signature.  Needs `PbjxEvent`, not `(Message $command, Pbjx $pbjx)`.


## v1.4.7
* Add `TeaserValidator` that requires a teaser's target to be published when it is being published.


## v1.4.6
* Remove `TeaserTransformer::transformExpirable` so teasers that are synced with their targets can set their own `expires_at`.


## v1.4.5
* Add `curator_find_promotion` and `curator_render_promotion_slots` twig functions.
* Implement rendering of a promotion's `slots` in `RenderPromotionRequestHandler`.


## v1.4.4
* In `SyncTeaserHandler::shouldAutoCreateTeaser` just auto create for article, gallery and video by default.
* In `NcrGalleryProjector` also update image counts when assets are deleted or expired.


## v1.4.3
* Do not, by default, autocreate teasers for assets.
* Transform asset teaser fields in TeaserTransformer.


## v1.4.2
* Transform for `expirable` mixin in TeaserTransformer.


## v1.4.1
* Adjust timing of teaser sync (doesn't need to be delayed so long).
* Do not auto create teaser if the target is already published (we'll handle this scenario at a late date).


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
