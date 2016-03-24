<?php

/**
 * @file
 * Contains \Drupal\turbolinks\Render\MainContent\AjaxRenderer.
 */

namespace Drupal\turbolinks\Render\MainContent;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\turbolinks\Ajax\TurbolinksUpdateRegionCommand;
use Drupal\turbolinks\TurbolinksPageState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Default main content renderer for Turbolinks requests.
 */
class TurbolinksRenderer implements MainContentRendererInterface {

  public function __construct(MainContentRendererInterface $html_renderer, TurbolinksPageState $turbolinks_page_state) {
    $this->htmlRenderer = $html_renderer;
    $this->renderer = \Drupal::service('renderer');
    $this->turbolinksPageState = $turbolinks_page_state;
  }

  /**
   * Validates preconditions required to be able to respond to this request.
   *
   * Verifies:
   * - the theme remains the same (relying on Ajax page state)
   * - the theme token is valid (relying on Ajax page state)
   */
  protected function validatePreconditions(Request $request) {
    // The theme token is only validated when the theme requested is not the
    // default, so don't generate it unless necessary.
    // @see \Drupal\Core\Theme\AjaxBasePageNegotiator::determineActiveTheme()
    $active_theme_key = \Drupal::theme()->getActiveTheme()->getName();
    if ($active_theme_key !== \Drupal::service('theme_handler')->getDefault()) {
      $theme_token = \Drupal::csrfToken()->get($active_theme_key);
    }
    else {
      $theme_token = '';
    }

    $request_theme_token = $request->get('ajax_page_state')['theme_token'];

    return $theme_token == $request_theme_token;
  }

  /**
   * {@inheritdoc}
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match) {
    if (!$this->validatePreconditions($request)) {
      throw new PreconditionFailedHttpException();
    }

    list($page, $title) = $this->htmlRenderer->prepare($main_content, $request, $route_match);

    // Render each region separately and determine whether it has changed.
    $response = new AjaxResponse();
    $regions = \Drupal::theme()->getActiveTheme()->getRegions();
    $all_regions_cacheability = new CacheableMetadata();
    foreach ($regions as $region) {
      if (!empty($page[$region])) {
        // @todo Future improvement: only render a region if it is actually
        // going to change. This would yield an even bigger benefit. The benefit
        // today is less data on the wire and particularly fewer things to
        // render in the browser. But we still render everything on the server.
        // This is sufficient for a prototype, but that would yield even better
        // performance.
        $this->renderer->renderRoot($page[$region]);
        $region_cacheability = CacheableMetadata::createFromRenderArray($page[$region]);
        if ($this->turbolinksPageState->hasChanged($region_cacheability, $request)) {
          $response->addCommand(new TurbolinksUpdateRegionCommand($region, \Drupal::service('render_cache')->getCacheableRenderArray($page[$region])));
        }

        // Collect the total set of cacheability metadata for all regions.
        $all_regions_cacheability->addCacheableDependency($region_cacheability);
      }
    }

    // Send updated Turbolinks page state.
    $response->addAttachments(['drupalSettings' => ['turbolinksPageState' => $this->turbolinksPageState->build($all_regions_cacheability)]]);

    // @todo page-level attachments, set or bubbled by hook_page_attachments()

    return $response;
  }

}
