<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Traits\File\FileMimeTypeTrait;
use Drupal\file\Entity\File;

/**
 * One chat messages for chat input.
 */
class ChatMessage {

  use FileMimeTypeTrait;

  /**
   * The role of the message.
   *
   * @var string
   */
  private string $role;

  /**
   * The text.
   *
   * @var string
   */
  private string $text;

  /**
   * The images files in an array.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile[]
   */
  private array $images;

  /**
   * The tools.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsOutputInterface[]|null
   */
  private ?array $tools = NULL;

  /**
   * The tool id if any.
   *
   * @var string
   */
  private string $toolId = "";

  /**
   * The constructor.
   *
   * @param string $role
   *   The role of the message.
   * @param string $text
   *   The text.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile[] $images
   *   The images.
   */
  public function __construct(string $role = "", string $text = "", array $images = []) {
    $this->role = $role;
    $this->text = $text;
    $this->images = $images;
  }

  /**
   * Get the role of the text.
   *
   * @return string
   *   The role.
   */
  public function getRole(): string {
    return $this->role;
  }

  /**
   * Get the text.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Set the role of the message.
   *
   * @param string $role
   *   The role.
   */
  public function setRole(string $role): void {
    $this->role = $role;
  }

  /**
   * Set the text.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text): void {
    $this->text = $text;
  }

  /**
   * Get the images.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile[]
   *   The images.
   */
  public function getImages(): array {
    return $this->images;
  }

  /**
   * Set the image.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   The image.
   */
  public function setImage(ImageFile $image): void {
    $this->images[] = $image;
  }

  /**
   * Get the tools.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsOutputInterface[]|null
   *   The tools.
   */
  public function getTools(): ?array {
    return $this->tools;
  }

  /**
   * Set the tools.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsOutputInterface[] $tools
   *   The tools.
   */
  public function setTools(array $tools): void {
    $this->tools = $tools;
  }

  /**
   * Get rendered tools output array.
   *
   * @return array
   *   The rendered array.
   */
  public function getRenderedTools(): array {
    $output = [];
    if ($this->tools) {
      foreach ($this->tools as $tool) {
        $output[] = $tool->getOutputRenderArray();
      }
    }
    return $output;
  }

  /**
   * Get the tool id.
   *
   * @return string
   *   The tool id.
   */
  public function getToolsId(): string {
    return $this->toolId;
  }

  /**
   * Set the tool id.
   *
   * @param string $tool_id
   *   The tool id.
   */
  public function setToolsId(string $tool_id): void {
    $this->toolId = $tool_id;
  }

  /**
   * Sets the image from a binary string.
   *
   * @param string $binary
   *   The binary string.
   * @param string $mime_type
   *   The mime type.
   */
  public function setImageFromBinary(string $binary, string $mime_type): void {
    $this->images[] = new ImageFile($binary, $mime_type);
  }

  /**
   * Sets the image from an url.
   *
   * @param string $url
   *   The url.
   */
  public function setImageFromUrl(string $url): void {
    // Get mime type from the uri.
    $mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($url);
    $filename = basename($url);
    $this->images[] = new ImageFile(file_get_contents($url), $mime_type, $filename);
  }

  /**
   * Set the image from a Drupal uri.
   *
   * @param string $uri
   *   The uri.
   */
  public function setImageFromUri(string $uri): void {
    // Get mime type from the uri.
    $mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($uri);
    $filename = basename($uri);
    $this->images[] = new ImageFile(file_get_contents($uri), $mime_type, $filename);
  }

  /**
   * Sets the image from a Drupal file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file.
   */
  public function setImageFromFile(File $file): void {
    $this->images[] = new ImageFile(file_get_contents($file->getFileUri()), $file->getMimeType(), $file->getFilename());
  }

  /**
   * Create an array of the message.
   *
   * @return array
   *   The array of the message.
   */
  public function toArray(): array {
    $images = [];
    foreach ($this->images as $image) {
      $images[] = $image->getBinary();
    }
    return [
      'role' => $this->role,
      'text' => $this->text,
      'images' => $images,
      'tools' => $this->tools ? $this->getRenderedTools() : NULL,
      'tool_id' => $this->toolId,
    ];
  }

}
