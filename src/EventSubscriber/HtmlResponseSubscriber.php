<?php

/**
 * @file
 * Contains \Drupal\turbolinks\EventSubscriber\HtmlResponseSubscriber.
 */

namespace Drupal\turbolinks\EventSubscriber;

use Drupal\Core\Render\HtmlResponse;
use Drupal\turbolinks\TurbolinksPageState;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Response subscriber to handle HTML responses.
 */
class HtmlResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a HtmlResponseSubscriber object.
   *
   * @param \Drupal\turbolinks\TurbolinksPageState $turbolinks_page_state
   */
  public function __construct(TurbolinksPageState $turbolinks_page_state) {
    $this->turbolinksPageState = $turbolinks_page_state;
  }

  /**
   * Initializes Turbolinks page state.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function initializeTurbolinksPageState(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof HtmlResponse) {
      return;
    }

    $response->addAttachments(['drupalSettings' => ['turbolinksPageState' => $this->turbolinksPageState->build($response->getCacheableMetadata())]]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run just before \Drupal\Core\EventSubscriber\HtmlResponseSubscriber,
    // which invokes
    // \Drupal\Core\Render\HtmlResponseAttachmentsProcessor::processAttachments,
    // which is what renders the attached drupalSettings into the HTML response.
    $events[KernelEvents::RESPONSE][] = ['initializeTurbolinksPageState', 1];

    return $events;
  }

}
