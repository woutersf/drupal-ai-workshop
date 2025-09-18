/**
 * JavaScript file that handles initializing and firing the RealTime SEO
 * analysis library.
 *
 * Support goalgorilla/YoastSEO.js v2.0.0.
 */
(function ($) {
  'use strict';

  Drupal.yoast_seo = Drupal.yoast_seo || {};
  Drupal.yoast_seo_node_new = false;

  Drupal.behaviors.yoast_seo = {
    attach: function (context, settings) {
      if (typeof settings.yoast_seo === 'undefined') {
        throw 'No settings specified for the YoastSEO analysis library.';
      }

      once('realtime-seo', 'body', context).forEach(function () {
        // TODO: This fails if there are multiple forms.
        var $form = $('form').first();

        window.orchestrator = new Orchestrator($form, settings.yoast_seo);
      });

      // Update the text fields behind the CKEditor when it changes.
      // TODO: Incorporate this with the update event binder.
      if (typeof CKEDITOR !== 'undefined') {
        CKEDITOR.on('instanceReady', function (ev) {
          // The editor that is now ready.
          var editor = ev.editor;

          // Ensure we only attach the change event once.
          if (!editor.yoast_seo_changed) {
            editor.on('blur', function () {
              // Let CKEditor handle updating the linked text element.
              editor.updateElement();
              // Tell our analyser data has changed.
              window.orchestrator.scheduleUpdate();
            });
            editor.yoast_seo_changed = true;
          }
        });
      }
    }
  };

  function verifyRequirements(config) {
    // Make a string.endsWidth method available if its not supported.
    if (!String.prototype.endsWith) {
      String.prototype.endsWith = function (searchStr, Position) {
        // This works much better than >= because
        // it compensates for NaN:
        if (!(Position < this.length)) {
          Position = this.length;
        } else {
          Position |= 0; // round position
        }
        return this.substr(Position - searchStr.length,
          searchStr.length) === searchStr;
      };
    }

    if (typeof config.targets !== 'object') {
      throw '`targets` is a required Orchestrator argument, `targets` is not an object.';
    }
    else {
      // Turn {name}_target_id into {name}: target_id.
      for (var key in config.targets) {
        if (key.endsWith('target_id')) {
          var target = key.substr(0, key.length - '_target_id'.length);
          config.targets[target] = config.targets[key];
          delete config.targets[key];
        }
      }
    }

    if (typeof RealTimeSEO === 'undefined') {
      $('#' + config.targets.output).html('<p><strong>' + Drupal.t('It looks like something went wrong when we tried to load the Real-Time SEO content analysis library. Please check it the module is installed correctly.') + '</strong></p>');
      throw 'RealTimeSEO is not defined. Is the library attached?';
    }

  }

  /**
   * Couples Drupal with the RealTimeSEO implementation.
   *
   * @param $form
   *   A jQuery selector reference to the form that we are analysing.
   * @param config
   *   The configuration for this orchestrator.
   *
   * @constructor
   */
  var Orchestrator = function ($form, config) {

    verifyRequirements(config);

    this.$form = $form;
    this.config = config;

    this.configureCallbacks();

    this.initializeApp();
  };

  /**
   * Set up the callbacks required by our analyzer library.
   */
  Orchestrator.prototype.configureCallbacks = function () {
    var defaultCallbacks = {
      getData: this.getData.bind(this),
      saveScores: this.saveScores.bind(this),
      saveContentScore: this.saveContentScore.bind(this)
    };

    // If any callbacks were set in config already, they will take precedence.
    this.config.callbacks = Object.assign(defaultCallbacks, this.config.callbacks);
  };

  /**
   * Creates and launches our analyzer library.
   */
  Orchestrator.prototype.initializeApp = function () {
    // Ensure this has no effect if called twice.
    if (typeof this.app !== 'undefined') {
      return;
    }

    var self = this;

    // We listen to the `updateSeoData` event on the body which is called by Drupal AJAX
    // after we send our form data up for analysis.
    jQuery('body').on('updateSeoData', function (e, data) {
      self.setData(data);
    });

    // Set up our event listener for normal form elements
    this.$form.change(this.handleChange.bind(this));

    // Set up event handlers for editable properties.
    once('yoast_seo-editable', '#yoast-snippet', this.$form.get(0)).forEach(el => $(el).focusout(this.handleBlur.bind(this)));

    // Set up our event listener for CKEditor instances if any.
    // We do this in a setTimeout to allow CKEDITOR to load.
    setTimeout(function () {
      if (typeof CKEDITOR !== 'undefined') {
        for (var i in CKEDITOR.instances) {
          CKEDITOR.instances[i].on('blur', self.handleBlur.bind(self));
        }
      }
    }, 200);

    // Default data.
    this.data = {
      meta: '',
      metaTitle: '',
      locale: 'en_US',
      keyword: jQuery('[data-drupal-selector=' + this.config.fields.focus_keyword + ']').val()
    };

    this.app = new RealTimeSEO.App(this.config);

    // We update what data we have available so that this.data is always
    // initialised. We also run the initializer to review existing entities.
    this.refreshData();
  };

  /**
   * Handles a change on the form. If our keyword was changed we just rerun
   * the analysis. In all other cases we schedule a reload of our data.
   */
  Orchestrator.prototype.handleChange = function (event) {
    var $target = $(event.target);

    if ($target.attr('data-drupal-selector') === this.config.fields.focus_keyword) {
      // Update the keyword and re-analyze.
      this.setData({ keyword: $target.val() });
      return;
    }

    this.scheduleUpdate();
  };

  /**
   * Handles the blur of a CKEditor field. We just rerun the analysis.
   */
  Orchestrator.prototype.handleBlur = function (event) {
    var $target = $(event.target);

    // If one of the editable properties was edited, update the field values.
    if ($target.is('.title.editable')) {
      jQuery('[data-drupal-selector="' + this.config.fields.edit_title + '"').val($target.text());
    }
    else if ($target.is('.desc.editable')) {
      jQuery('[data-drupal-selector="' + this.config.fields.edit_description + '"').val($target.text());
    }

    this.scheduleUpdate();
  };

  /**
   * Schedules an update in a short moment. Will undo any previously scheduled
   * updates to avoid excessive HTTP requests.
   */
  Orchestrator.prototype.scheduleUpdate = function () {
    if (this.update_timeout) {
      clearTimeout(this.update_timeout);
      this.update_timeout = false;
    }

    var self = this;
    this.update_timeout = setTimeout(function () {
      self.update_timeout = false;
      self.refreshData();
    }, 500);
  };

  /**
   * Tells the library to retrieve its data and runs the analyzer.
   */
  Orchestrator.prototype.analyze = function () {
    this.app.getData();
    this.app.runAnalyzer();
  };

  /**
   * Sends a request to our Drupal endpoint to refresh our local data.
   *
   * This is the most important part of our part of the equation.
   *
   * We talk to Drupal to provide all the data that the YoastSEO.js library
   * needs to do the analysis.
   */
  Orchestrator.prototype.refreshData = function () {
    // We use Drupal's AJAX progress indicator to check that we're not
    // interfering with an already running AJAX request. If an AJAX request is
    // already running then we reschedule the update.
    if (
      !jQuery('.ajax-progress').length &&
       this.config.auto_refresh_seo_result
    ) {
      // Click the refresh data button to perform a Drupal AJAX submit.
      this.$form.find('.yoast-seo-preview-submit-button').mousedown();
    }
    else {
      this.scheduleUpdate();
    }
  };

  /**
   * Provides a method to set the data that we provide to the Real Time SEO
   * library for analysis.
   *
   * Can be used as a callback to our Drupal analysis endpoint.
   *
   * @param data
   */
  Orchestrator.prototype.setData = function (data) {
    // We merge the data so we can selectively overwrite things.
    this.data = Object.assign({}, this.data, data);

    this.updatePreview();

    // Some things are composed of others.
    this.data.titleWidth = document.getElementById('snippet_title').offsetWidth;
    this.data.permalink = this.config.base_root + this.data.url;

    // Our data has changed so we rerun the analyzer.
    this.analyze();
  };

  /**
   * This is used as a callback in the Real Time SEO library to provide the data
   * that is needed for analysis.
   *
   * @return analyzerData
   */
  Orchestrator.prototype.getData = function () {
    return this.data;
  };

  // Temporary function to keep things working.
  Orchestrator.prototype.getDataFromInput = function (f) {
    return 'static';
  };

  /**
   * Sets the SEO score in the hidden element.
   * @param score
   */
  Orchestrator.prototype.saveScores = function (score) {
    var rating = 0;
    if (typeof score === 'number' && score > 0) {
      rating = ( score / 10 );
    }

    const newLabelText = this.scoreToRating(rating);

    // The element holding our score indicator bubble and label.
    const scoreDisplay = document.getElementById(this.config.targets.overall_score);

    // Get the current rating label so we can remove the class name for the color icon and replace it with the new one.
    const scoreLabel = scoreDisplay.getElementsByClassName('score_value')[0];

    // We convert the label to lowercase here, which is not as good as clean_css that's being called on the back-end,
    // but is good enough for the constraints on the classes we use.
    scoreDisplay.classList.remove(scoreLabel.innerHTML.toLowerCase().replace(' ', '-'));
    scoreDisplay.classList.add(newLabelText.toLowerCase().replace(' ', '-'));

    // Update the label for the user to the new text.
    scoreLabel.innerHTML = newLabelText;

    // Store the value in the input field that gets submitted.
    document.querySelector('[data-drupal-selector="' + this.config.fields.seo_status + '"]').setAttribute('value', rating);
  };

  /**
   * Returns a string that indicates the score to the user.
   *
   * @param score integer
   * @returns integer
   *   The human readable rating
   */
  Orchestrator.prototype.scoreToRating = function (score) {
    // Get the label thresholds from high to low.
    var thresholds = Object.keys(this.config.score_rules).sort().reverse();

    for (var i in thresholds) {
      var minimum = thresholds[i];

      if (score >= minimum) {
        return this.config.score_rules[minimum];
      }
    }

    return Drupal.t('Unknown');
  };

  /**
   * Sets the content score in the hidden element.
   * @param score
   */
  Orchestrator.prototype.saveContentScore = function (score) {
    // TODO: Implement this method, we're not currently using this score.
  };

  /**
   * Updates the preview with the newest snippet.
   */
  Orchestrator.prototype.updatePreview = function () {
    // Title
    const snippetTitle = document.createElement('span');
    snippetTitle.classList.add("title");
    snippetTitle.textContent = this.data.metaTitle;
    snippetTitle.id = "snippet_title";

    if (this.config.enable_editing.title) {
      snippetTitle.classList.add("editable");
      snippetTitle.attr('contenteditable', 'true');
    }

    // Highlight keyword by splitting the sentence on the keyword and then
    // reinserting it as strong element.
    if (this.data.keyword) {
      const highlight = document.createElement('strong');
      highlight.textContent = this.data.keyword;
      snippetTitle.replaceChildren(
        ...snippetTitle.textContent
          .split(this.data.keyword)
          .flatMap((
            part, index, arr) => index < arr.length-1 ? [part, highlight.cloneNode(true)] : part
          )
      );
    }

    const titleContainer = document.createElement('div');
    titleContainer.classList.add("snippet_container", "snippet-editor__container");
    titleContainer.id = "title_container";
    titleContainer.appendChild(snippetTitle);

    // URL Base
    const urlBase = document.createElement('cite');
    urlBase.classList.add("url", "urlBase");
    urlBase.id = "snippet_citeBase";
    urlBase.textContent = this.config.base_root;

    // URL Path
    const urlPath = document.createElement('cite');
    urlPath.classList.add("url");
    urlPath.id = "snippet_cite";
    urlPath.textContent = this.data.url;


    const urlContainer = document.createElement('div');
    urlContainer.classList.add("snippet_container", "snippet-editor__container");
    urlContainer.id = "url_container";
    urlContainer.appendChild(urlBase);
    urlContainer.appendChild(urlPath);

    // Description
    const snippetMeta = document.createElement('span');
    snippetMeta.classList.add("desc", "desc-default");
    snippetMeta.id = "snippet_meta";
    snippetMeta.textContent = this.data.meta;

    if (this.config.enable_editing.description) {
      snippetMeta.classList.add("editable");
      snippetMeta.attr('contenteditable', 'true');
    }

    const metaContainer = document.createElement('div');
    metaContainer.classList.add("snippet_container", "snippet-editor__container");
    metaContainer.id = "meta_container";
    metaContainer.appendChild(snippetMeta);

    // Preview snippet
    const previewSnippet = document.createElement('section');
    previewSnippet.classList.add('snippet-editor__preview');
    previewSnippet.append(titleContainer, urlContainer, metaContainer);

    document.getElementById(this.config.targets.snippet).replaceChildren(previewSnippet);
  };

})(jQuery);
