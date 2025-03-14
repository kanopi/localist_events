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
   * @param array $config
   *   The configuration data to send to the Localist events widget.
   *
   * @return array|string
   *   The array of parsed data/HTML items or an error message.
   */
  public function fetch(array $config) {
    $domain = $this->configFactory->get('localist_events.settings')->get('domain');
    $domain = rtrim($domain, '/');
    $image_selector = $this->configFactory->get('localist_events.settings')->get('image_selector');
    $tag_selector = $this->configFactory->get('localist_events.settings')->get('tag_selector');
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
    $url = $domain . '/widget/view?' . implode('&', $query);
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
      $this->cache->set($cid, $contents, strtotime('today') + (60 * 60 * 24));
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
            $cid = 'localist_events:external:' . base64_encode($url);

            if ($cache = $this->cache->get($cid)) {
              $contents = $cache->data;
            }
            else {
              $result_request = $this->httpClient->request('GET', $url);
              $contents = '';

              if ($result_request->getStatusCode() == 200) {
                $contents = $result_request->getBody()->getContents();
              }

              // Cache the results for a week.
              $this->cache->set($cid, $contents, strtotime('today') + (60 * 60 * 24 * 7));
            }

            $result_doc = new HTML5DOMDocument();
            $result_doc->loadHTML($contents, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
            /** @var \IvoPetkov\HTML5DOMElement */
            $image = $image_selector ? $result_doc->querySelector($image_selector) : FALSE;
            /** @var \IvoPetkov\HTML5DOMNodeList */
            $tags = $tag_selector ? $result_doc->querySelectorAll($tag_selector) : FALSE;
            $tags_array = [];

            if (!$image || $image->tagName != 'img') {
              unset($image);
            }

            if (!$tags || !$tags->length) {
              unset($tags);
            }
            else {
              /** @var \IvoPetkov\HTML5DOMElement $tag */
              foreach ($tags->getIterator() as $tag) {
                // Skip if this tag is not a link.
                if ($tag->tagName != 'a') {
                  continue;
                }

                $href = $tag->getAttribute('href');

                // If it is a relative link, prepend the domain.
                if (preg_match('|^/|i', $href)) {
                  $tag->setAttribute('href', $domain . $href);
                }

                // Mimic the target attribute from the block config.
                if ($config['target_blank']) {
                  $tag->setAttribute('target', '_blank');
                }

                $tags_array[] = Markup::create($tag->outerHTML);
              }
            }
          }
        }

        $items_temp = [
          'date' => isset($date) ? trim($date->innerHTML) : '',
          'description' => isset($description) ? trim($description->getTextContent()) : '',
          'image' => isset($image) ? Markup::create($image->outerHTML) : '',
          'link' => isset($link) ? Markup::create($link->outerHTML) : '',
          'location' => isset($location) ? Markup::create($location->outerHTML) : '',
          'tags' => !empty($tags_array) ? $tags_array : '',
        ];

        $items[] = array_filter($items_temp);
      }
    }

    return $items;
  }

}
