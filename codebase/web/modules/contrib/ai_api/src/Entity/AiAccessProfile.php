<?php

declare(strict_types=1);

namespace Drupal\ai_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_api\AiAccessProfileInterface;

/**
 * Defines the AI Access Profile entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_access_profile",
 *   label = @Translation("AI Access Profile"),
 *   label_collection = @Translation("AI Access Profiles"),
 *   label_singular = @Translation("AI Access Profile"),
 *   label_plural = @Translation("AI Access Profiles"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI Access Profile",
 *     plural = "@count AI Access Profiles",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_api\AiAccessProfileListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_api\Form\AiAccessProfileForm",
 *       "edit" = "Drupal\ai_api\Form\AiAccessProfileForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_access_profile",
 *   admin_permission = "administer ai_access_profile",
 *   links = {
 *     "collection" = "/admin/structure/ai-access-profile",
 *     "add-form" = "/admin/structure/ai-access-profile/add",
 *     "edit-form" = "/admin/structure/ai-access-profile/{ai_access_profile}",
 *     "delete-form" = "/admin/structure/ai-access-profile/{ai_access_profile}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "access_key",
 *     "access_method",
 *     "permission",
 *     "operation_types",
 *   },
 * )
 */
final class AiAccessProfile extends ConfigEntityBase implements AiAccessProfileInterface {

  /**
   * The access profiles ID.
   */
  protected string $id;

  /**
   * The access profiles label.
   */
  protected string $label;

  /**
   * The access value.
   */
  protected string $access_key;

  /**
   * The access method.
   */
  protected string $access_method;

  /**
   * The access profiles description.
   */
  protected string $description;

  /**
   * The permissions to use.
   */
  protected string $permission;

  /**
   * The access profiles operation types supported.
   */
  protected array $operation_types = [];

}
