(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.aiTalkWithNode = {
    attach: function (context, settings) {
      // Attach the submit handler only once per form.
      console.log('attach');
      once('aiTalkWithNodeForm', '.ai-talk-with-node-form', context).forEach(function (formElem) {
        var $form = $(formElem);

        // Locate the results output region and the suffix text.
        var $resultsBlock = $('#ai-talk-with-node-response .ai-talk-with-node-output');
        var $suffixText = $('#ai-talk-block .suffix_text');
        if (!$resultsBlock.length) {
          console.warn('AI Search: Could not find a results block relative to the form.');
          return;
        }

        // Hide suffix text initially.
        if ($suffixText.length) {
          $suffixText.hide();
        }

        // Determine loading message and create a reusable loader element.
        var loadingMsg = drupalSettings.ai_talk_with_node && drupalSettings.ai_talk_with_node.loading_text
            ? drupalSettings.ai_talk_with_node.loading_text
            : 'Loading...';
        var $loader = $('<p class="loading_text"><span class="loader"></span>' + loadingMsg + '</p>');

        $form.on('submit', function (event) {
          console.log('Submit');
          event.preventDefault();

          // Show the loader initially.
          $resultsBlock.html($loader);

          // Retrieve form values.
          var queryVal = $form.find('[data-drupal-selector="edit-query"]').val() || '';
          var streamVal = $form.find('[data-drupal-selector="edit-stream"]').val() === 'true';
          var blockIdVal = $form.find('[data-drupal-selector="edit-block-id"]').val() || '';
          var nodeIdVal = $form.find('[data-drupal-selector="edit-node-id"]').val() || '';

          // If streaming is enabled (using '1' as true).
          if (streamVal) {
            try {
              var xhr = new XMLHttpRequest();
              xhr.open('POST', drupalSettings.ai_talk_with_node.submit_url, true);
              xhr.setRequestHeader('Content-Type', 'application/json');
              xhr.setRequestHeader('Accept', 'application/json');

              // Cache variables to hold the full output.
              var lastResponseLength = 0;
              var joined = '';

              xhr.onprogress = function () {
                var responseText = xhr.responseText || '';
                // Get only the new part of the response.
                var newData = responseText.substring(lastResponseLength);
                lastResponseLength = responseText.length;

                // Split new data using the delimiter.
                var chunks = newData.trim().split('|§|').filter(Boolean);

                // Parse each chunk and accumulate the answer pieces.
                chunks.forEach(function (chunk) {
                  try {
                    var parsed = JSON.parse(chunk);
                    // Update log Id from each chunk.
                    if (parsed.log_id) {
                      drupalSettings.ai_talk_with_node.logId = parsed.log_id;
                    }
                    joined += parsed.answer_piece || '';
                  } catch (e) {
                    console.error('Error parsing chunk:', e, chunk);
                  }
                });

                // Overwrite the full output (letting browsers fix broken HTML)
                // and re-append the loader.
                $resultsBlock.html(joined).append($loader);
              };

              xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                  if (xhr.status === 200) {
                    // Remove the loader upon successful completion.
                    $loader.remove();
                    if ($suffixText.length) {
                      $suffixText.html(drupalSettings.ai_talk_with_node.suffix_text);
                      Drupal.attachBehaviors($suffixText[0]);
                      $suffixText.show();
                    }
                    // (Optional) If needed, update log Id from final response here.
                  } else if (xhr.status === 500) {
                    $resultsBlock.html('An error happened.');
                    console.error('Error response:', xhr.responseText);
                    try {
                      var parsedError = JSON.parse(xhr.responseText);
                      if (parsedError.response && parsedError.response.answer_piece) {
                        $resultsBlock.html(parsedError.response.answer_piece);
                      }
                      Drupal.attachBehaviors($resultsBlock[0]);
                    } catch (e) {
                      console.error('Error parsing 500 response:', e);
                    }
                  }
                }
              };

              // Send the streaming request.
              xhr.send(
                  JSON.stringify({
                    query: queryVal,
                    stream: streamVal,
                    block_id: blockIdVal,
                    node_id: nodeIdVal
                  })
              );
            } catch (e) {
              console.error('XHR error:', e);
            }
          } else {
            // Non-streaming: use jQuery.post.
            $.post(
                drupalSettings.ai_talk_with_node.submit_url,
                {
                  query: queryVal,
                  stream: streamVal,
                  block_id: blockIdVal,
                  node_id: nodeIdVal
                },
                function (data) {
                  if (data && data.response) {
                    $resultsBlock.html(data.response);
                  }
                  // Set log Id if available.
                  if (data && data.log_id) {
                    drupalSettings.ai_talk_with_node.logId = data.log_id;
                  }
                  if ($suffixText.length) {
                    $suffixText.html(drupalSettings.ai_talk_with_node.suffix_text).show();
                    Drupal.attachBehaviors($suffixText[0]);
                  }
                }
            ).fail(function () {
              $resultsBlock.html('An error happened.');
            });
          }

          return false;
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
