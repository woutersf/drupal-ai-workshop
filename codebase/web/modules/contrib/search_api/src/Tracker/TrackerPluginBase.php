<?php

namespace Drupal\search_api\Tracker;

use Drupal\search_api\Plugin\IndexPluginBase;

/**
 * Defines a base class from which other tracker classes may extend.
 *
 *  Plugins extending this class need to provide the plugin definition using the
 *  \Drupal\search_api\Attribute\SearchApiTracker attribute. These definitions
 *  may be altered using the "search_api.gathering_trackers" event.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * #[SearchApiTracker(
 *   id: 'my_tracker',
 *   label: new TranslatableMarkup('My tracker'),
 *   description: new TranslatableMarkup('Simple tracking system.')
 * )]
 * @endcode
 *
 * @see \Drupal\search_api\Attribute\SearchApiTracker
 * @see \Drupal\search_api\Tracker\TrackerPluginManager
 * @see \Drupal\search_api\Tracker\TrackerInterface
 * @see \Drupal\search_api\Event\SearchApiEvents::GATHERING_TRACKERS
 * @see plugin_api
 */
abstract class TrackerPluginBase extends IndexPluginBase implements TrackerInterface {

  // @todo Move some of the methods from
  //   \Drupal\search_api\Plugin\search_api\tracker\Basic to here?

}
