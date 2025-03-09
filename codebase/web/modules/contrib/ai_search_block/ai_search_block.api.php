<?php

/**
 * @file
 * The Api documentation for this module.
 */

/**
 * Implements hook_ai_search_block_prompt_alter().
 */
function hook_ai_search_block_prompt_alter(&$prompt) {
  $variable = time();
  // Alter the prompt here.
  $prompt = str_replace('[my custom token]', $variable, $prompt);
}

/**
 * Implements hook_ai_search_block_entity_html_alter().
 */
function hook_ai_search_block_entity_html_alter(&$rendered_entity, $entity) {
  // Change the html for the entity.
}

/**
 * Implements hook_ai_search_block_entity_markdown_alter().
 */
function hook_ai_search_block_entity_markdown_alter(&$rendered_entity, $entity) {
  // Change the markdown for the entity.
}
