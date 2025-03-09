<?php

/**
 * @file
 * The Api documentation for this module.
 */

/**
 * Implements hook_ai_talk_with_node_prompt_alter().
 */
function hook_ai_talk_with_node_prompt_alter(&$prompt, $blockId) {
  if ($blockId == 'olivero_aiblock') {
    $variable = time();
    // Alter the prompt here.
    $prompt = str_replace('[my custom token]', $variable, $prompt);
  }
}


/**
 * Implements hook_ai_talk_with_node_response_alter().
 */
function hook_ai_talk_with_node_response_alter(&$aiResponse, $blockId) {
  $variable = time();
  // Alter the Ai response here.
  $prompt = str_replace('[my custom token]', $variable, $aiResponse);
}
