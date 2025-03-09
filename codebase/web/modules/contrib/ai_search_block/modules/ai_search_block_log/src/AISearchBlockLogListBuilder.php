<?php

declare(strict_types=1);

namespace Drupal\ai_search_block_log;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the ai search block log entity type.
 */
final class AISearchBlockLogListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ai_search_block_log\AISearchBlockLogInterface $entity */
    $row['id'] = $entity->toLink();
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
