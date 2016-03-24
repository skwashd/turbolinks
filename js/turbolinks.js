(function ($, Drupal, drupalSettings) {

  'use strict';

  var relativeLinksSelector = 'a[href^="/"]';

  function getLibraries() {
    return drupalSettings.ajaxPageState.libraries.split(',');
  }

  function urlUsesDifferentTheme(url) {
    // Links pointing to a route that uses a different theme need full page
    // reloads. Any route can use any arbitrary theme based on arbitrary rules.
    // But, the 99% case is:
    // - a site with two themes: a front-end and an admin theme
    // - all '/admin/*' routes use the admin theme
    // We can optimize for that common case, and handle the uncommon case by
    // attempting to do a Turbolinks-accelerated page load, and if the theme
    // doesn't match the current theme, do a full reload anyway.
    // @see \Drupal\system\EventSubscriber\AdminRouteSubscriber
    // @see …
    var urlIsAdmin = url.startsWith(drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix + 'admin');
    return drupalSettings.path.currentPathIsAdmin != urlIsAdmin;
  }

  function debug(url, state) {
    console.debug('Received Turbolinks response for "' + url + '".');
    console.debug('  Replaced ' + state.updateRegionCommands.length + ' out of ' + document.querySelectorAll('[data-turbolinks-region]').length + ' regions (' + state.updateRegionCommands.map(function (command) { return command.region; }).join(', ') + ').');
    console.debug('  Loaded ' + state.newLibraries.length + ' additional asset libraries: ', state.newLibraries);
  }

  function handleClick(event) {
    // Middle click, cmd click, and ctrl click should open
    // links in a new tab as normal.
    if (event.which > 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    var url = this.getAttribute('href');
    if (urlUsesDifferentTheme(url)) {
      return;
    }

    if (!event.isDefaultPrevented()) {
      event.preventDefault();

      var newPath = url.replace(drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix, '');
      var librariesBefore = getLibraries();

      // Create a Drupal.Ajax object without associating an element, a
      // progress indicator or a URL.
      var ajaxObject = Drupal.ajax({
        url: url,
        base: false,
        element: false,
        // @todo turbolinks progress?
        progress: false,
        // @todo vastly improve this.
        wrapper: drupalSettings.ajaxPageState.theme === 'bartik' ? 'block-bartik-content .content > *' : 'block-seven-content > *',
        dialogType: 'turbolinks'
      });
      var ajaxInstanceIndex = Drupal.ajax.instances.length;

      // Use GET, not the default of POST.
      ajaxObject.options.type = 'GET';

      // As soon as the page's settings are updated, also update currentPath.
      // (None of the other settings under drupalSettings.path can ever change,
      // if they would, Turbolinks would trigger a full reload.)
      var originalSettingsCommand = ajaxObject.commands.settings;
      ajaxObject.commands.settings= function (ajax, response, status) {
        originalSettingsCommand(ajax, response, status);
        drupalSettings.path.currentPath = newPath;
      };

      // The server responds with a 412 when the current page's theme doesn't
      // match the response's theme. This means we need to do a full reload
      // after all.
      ajaxObject.options.error = function (response, status, xmlhttprequest) {
        if (response.status === 412) {
          window.location = url;
        }
      };

      // When the Turbolinks request receives a succesful response, update the
      // URL using the history.pushState() API.
      var originalSuccess = ajaxObject.options.success;
      ajaxObject.options.success = function (response, status, xmlhttprequest) {
        originalSuccess(response, status, xmlhttprequest);

        var state = {
          updateRegionCommands: response.filter(function (command) { return command.command === 'turbolinksUpdateRegion' }),
          librariesBefore: librariesBefore,
          newLibraries: getLibraries().filter(function (value) { return !librariesBefore.includes(value); })
        };
        history.pushState(state, '', url);

        // @todo trigger drupal:path:changed event, ensure contextual.js listens to this event.

        debug(url, state);

        // Set this to null and allow garbage collection to reclaim
        // the memory.
        Drupal.ajax.instances[ajaxInstanceIndex] = null;
      };

      // Pass Turbolinks' page state, to allow the server to determine which
      // parts of the page need to be updated and which don't.
      ajaxObject.options.data.turbolinks_page_state = drupalSettings.turbolinksPageState;

      ajaxObject.execute();
    }
  }

  jQuery('body').once('turbolinks').on('click', relativeLinksSelector, handleClick);

  /**
   * Command to insert new content into the DOM.
   *
   * @param {Drupal.Ajax} ajax
   *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
   * @param {object} response
   *   The response from the Ajax request.
   * @param {string} response.region
   *   The region name.
   * @param {string} response.data
   *   The new markup for the given region.
   * @param {number} [status]
   *   The XMLHttpRequest status.
   */
  Drupal.AjaxCommands.prototype.turbolinksUpdateRegion = function (ajax, response, status) {
    // @see turbolinks_preprocess_region()
    response.selector = '[data-turbolinks-region=' + response.region + ']';

    return this.insert(ajax, response, status);
  };

})(jQuery, Drupal, drupalSettings);
