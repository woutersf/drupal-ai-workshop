<?php

/**
 * @file
 * The Api documentation for this module.
 */

/**
 * Implements hook_ai_block_prompt_alter().
 */
function hook_ai_block_prompt_alter(&$prompt, $blockId) {
  if ($blockId == 'olivero_aiblock') {
    $variable = time();
    // Alter the prompt here.
    $prompt = str_replace('[my custom token]', $variable, $prompt);
  }
}


/**
 * Implements hook_ai_block_response_alter().
 */
function hook_ai_block_response_alter(&$aiResponse, $blockId) {
  $variable = time();
  // Alter the Ai response here.
  $prompt = str_replace('[my custom token]', $variable, $aiResponse);
}
