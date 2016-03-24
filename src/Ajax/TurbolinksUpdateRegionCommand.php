<?php

/**
 * @file
 * Contains \Drupal\turbolinks\Ajax\TurbolinksUpdateRegionCommand.
 */

namespace Drupal\turbolinks\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;

/**
 * AJAX command for updating a region in the page.
 *
 * This command is implemented by Drupal.AjaxCommands.prototype.turbolinksUpdateRegion()
 * defined in js/turbolinks.js
 *
 * @ingroup ajax
 *
 * @see \Drupal\Core\Ajax\InsertCommand
 */
class TurbolinksUpdateRegionCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  /**
   * A region name.
   *
   * @var string
   */
  protected $region;

  /**
   * The render array for the region.
   *
   * @var array
   */
  protected $content;

  /**
   * Constructs an TurbolinksUpdateRegionCommand object.
   *
   * @param string $region
   *   A region name.
   * @param array $content
   *   The render array with the content for the region.
   */
  public function __construct($region, array $content) {
    assert('is_string($region)');
    $this->region = $region;
    $this->content = $content;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'turbolinksUpdateRegion',
      'region' => $this->region,
      'data' => $this->getRenderedContent(),
    ];
  }

}
