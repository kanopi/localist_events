<?php

namespace Drupal\localist_events;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use IvoPetkov\HTML5DOMDocument;
use IvoPetkov\HTML5DOMElement;

/**
 * Fetches Localist event script and parses the HTML.
 */
final class FetchEvents {
  use StringTranslationTrait;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cache;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Constructs a FetchEvents object.
   */
  public function __construct(
    private readonly CacheFactoryInterface $cacheFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
  ) {
    $this->config = $this->configFactory->get('localist_events.settings');
    $this->cache = $this->cacheFactory->get('cache.default');
  }

  /**
   * Fetches and parses the Localist events data.
   *
   * @param string $domain
   *   The domain to request.
   * @param string $image_selector
   *   The CSS selector for the thumbnail image.
   * @param array $config
   *   The configuration data to send to the Localist events widget.
   *
   * @return array|string
   *   The array of parsed data/HTML items or an error message.
   */
  public function fetch(string $domain, string $image_selector, array $config) {
    // Build domain from config.
    $domain = rtrim($domain, '/') . '/widget/view';
    // Build options from config values.
    $options = [
      'id' => $config['id'],
      'schools' => $config['schools'] ?? '',
      'groups' => $config['groups'] ?? '',
      'days' => intval($config['days']) ?? 31,
      'num' => intval($config['total']) ?? 3,
      'all_instances' => $config['all_instances'] ?? TRUE,
      'show_times' => $config['show_times'] ?? FALSE,
      'target_blank' => $config['target_blank'] ?? TRUE,
    ];
    // Manually build the query as passing the options argument to
    // $this->httpClient->request() doesn't work for some reason.
    $query = array_map(function ($key, $option) {
      return "$key=$option";
    }, array_keys($options), array_values($options));
    $url = "$domain?" . implode('&', $query);
    $cid = 'localist_events:' . implode(':', $options);

    if ($cache = $this->cache->get($cid)) {
      $contents = $cache->data;
    }
    else {
      $request = $this->httpClient->request('GET', $url);

      if ($request->getStatusCode() == 200) {
        $contents = $request->getBody()->getContents();
      }
      else {
        return [
          'error' => $this->t('Localist Events could not be reached.'),
        ];
      }

      // Cache the results for a day.
      $this->cache->set($cid, $contents, time() + (60 * 60 * 24));
    }

    // Capture the HTML from the JavaScript output. Splitting at the end of the
    // style tag and the beginning of the first div.
    $raw_html = preg_match('/\\\u003c\/style\\\u003e (\\\u003cdiv.*\\\u003c\/div\\\u003e)"/i', $contents, $matches);
    $items = [];

    if (!count($matches)) {
      return [
        'error' => $this->t('Localist Events could not be reached.'),
      ];
    }
    else {
      $raw_html = json_decode("\"$matches[1]\"");
      $doc = new HTML5DOMDocument();
      $doc->loadHTML($raw_html, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);

      /** @var \IvoPetkov\HTML5DOMElement $item */
      foreach ($doc->querySelector('ul')->getElementsByTagName('li') as $item) {
        $date = $item->querySelector('.lwn > .lwn0');
        $link = $item->querySelector('.lwn > a');
        $description = $item->querySelector('.lwd');
        $location = $item->querySelector('.lwl > a');

        if ($link instanceof HTML5DOMElement) {
          $url = $link->getAttribute('href');

          if (filter_var($url, FILTER_VALIDATE_URL)) {
            $result_request = $this->httpClient->request('GET', $url);
            $result_doc = new HTML5DOMDocument();
            $result_doc->loadHTML($result_request->getBody()->getContents(), HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
            $image = $result_doc->querySelector($image_selector);

            if ($image->tagName != 'img') {
              unset($image);
            }
          }
        }

        $items_temp = [
          'date' => isset($date) ? trim($date->innerHTML) : '',
          'description' => isset($description) ? trim($description->getTextContent()) : '',
          'image' => isset($image) ? Markup::create($image->outerHTML) : '',
          'link' => isset($link) ? Markup::create($link->outerHTML) : '',
          'location' => isset($location) ? Markup::create($location->outerHTML) : '',
        ];

        $items[] = array_filter($items_temp);
      }
    }

    return $items;
  }

}
