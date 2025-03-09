<?php

namespace Drupal\ai_provider_mistral;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;

/**
 * Mistral Chat message iterator.
 */
class MistralChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    foreach ($this->iterator->getIterator() as $data) {
      $metadata = $data->metadata ?? [];
      if (!empty($metadata) && is_array($metadata)) {
        $metadata = json_encode($metadata, TRUE);
      }
      yield new StreamedChatMessage(
        $data->choices[0]->delta->role ?? '',
        $data->choices[0]->delta->content ?? '',
        $metadata,
      );
    }
  }

}
