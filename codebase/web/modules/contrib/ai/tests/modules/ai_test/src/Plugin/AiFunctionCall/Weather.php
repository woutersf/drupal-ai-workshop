<?php

namespace Drupal\ai_test\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of a weather function - just for tests.
 */
#[FunctionCall(
  id: 'ai:weather',
  function_name: 'weather',
  name: 'Weather',
  description: 'Get the weather for a text based location.',
  context_definitions: [
    'city' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("City"),
      required: TRUE,
      description: new TranslatableMarkup("The city to get the weather for (e.g. London).")
    ),
    'country' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Country"),
      required: TRUE,
      description: new TranslatableMarkup("The country to get the weather for. If not provided, try to guess it from the context. The country where the city is located (e.g., USA for Los Angeles).")
    ),
    'unit' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Unit"),
      required: FALSE,
      description: new TranslatableMarkup("The unit to get the weather in (celsius or fahrenheit)."),
      default_value: 'celsius',
      constraints: [
        'Choice' => [
          'choices' => [
            'celsius',
            'fahrenheit',
          ],
        ],
      ],
    ),
  ]
)]
class Weather extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The temperature.
   *
   * @var string
   */
  protected string $temperature;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new ContextDefinitionNormalizer(),
    );
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $cities = json_decode($this->httpClient->get('https://geocoding-api.open-meteo.com/v1/search?name=' . $this->getContextValue('city') . '%2C+' . $this->getContextValue('country') . '&count=10&language=en&format=json')->getBody()->getContents(), TRUE);
    $this->temperature = 'No data found';
    if (isset($cities['results'][0])) {
      $data = json_decode($this->httpClient->get('https://api.open-meteo.com/v1/forecast?latitude=' . $cities['results'][0]['latitude'] . '&longitude=' . $cities['results'][0]['longitude'] . '&hourly=temperature_2m&forecast_days=1&temperature_unit=' . $this->getContextValue('unit'))->getBody()->getContents(), TRUE);
      $this->temperature = $data['hourly']['temperature_2m'][0] . ' °' . $data['hourly_units']['temperature_2m'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return (string) $this->temperature;
  }

}
