<?php

namespace Drupal\cleanpager\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

define('CLEANPAGER_ADDITIONAL_PATH_VARIABLE', 'page');
/**
 * Subscriber for cleanpager.
 *
 * @package Drupal\cleanpager\EventSubscriber
 */
class CleanPagerSubscriber implements EventSubscriberInterface {


  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The PathMatcher service.
   *
   * @var Drupal\Core\Path\PathMatcher
   */
  protected $pathMatcher;

  /**
   * The RequestStack service.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * Constructs a new CleanPagerSubscriber instance.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The current path.
   * @param Drupal\Core\Path\PathMatcher $path_matcher
   *   The current path.
   * @param Symfony\Component\HttpFoundation\RequestStack $stack
   *   The current path.
   */
  public function __construct(CurrentPathStack $current_path, ConfigFactoryInterface $config_factory, PathMatcher $path_matcher, RequestStack $stack) {
    $this->currentPath = $current_path;
    $this->configFactory = $config_factory;
    $this->pathMatcher = $path_matcher;
    $this->requestStack = $stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.current'),
      $container->get('config.factory'),
      $container->get('path.matcher'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Replace ?page=1 to /page/1.
    $events[KernelEvents::REQUEST][] = ['checkForRedirection'];
    return $events;
  }

  /**
   * To check the Redirection.
   */
  public function checkForRedirection(GetResponseEvent $event) {
    global $_cleanpager_rewritten;
    $path = $this->currentPath->getPath();
    if ($path_length = strpos($path, '/page/')) {
      $path_test_part = substr($path, 0, $path_length);
    }
    else {
      $path_test_part = $path;
    }
    $pages = $this->configFactory->get('cleanpager.settings')->get('cleanpager_pages');
    if ($this->pathMatcher->matchPath($path_test_part, $pages)) {
      // Pass along additional query string values.
      $query_values = $this->requestStack->getCurrentRequest()->query->all();

      if (isset($query_values['page']) && !empty($query_values['page']) && $_cleanpager_rewritten == FALSE) {
        $path .= '/page/' . $query_values['page'];
        if ($this->configFactory->get('cleanpager.settings')->get('cleanpager_add_trailing')) {
          $path .= '/';
        }
        unset($query_values['page']);
        if (isset($query_values['q'])) {
          unset($query_values['q']);
        }
        $options['query'] = $query_values;
        $path .= (strpos($path, '?') !== FALSE ? '&' : '?') . $this->cleanPagerHttpBuildQuery($options['query']);
        unset($query_values['page']);
        header('Location: ' . $path, FALSE, 302);
        exit();
      }
    }
  }

  /**
   * TO BUILD HTTP QUERY.
   */
  public function cleanPagerHttpBuildQuery(array $query, $parent = '') {
    $params = [];
    foreach ($query as $key => $value) {
      $key = ($parent ? $parent . '[' . rawurlencode($key) . ']' : rawurlencode($key));
      // Recurse into children.
      if (is_array($value)) {
        $params[] = $this->cleanPagerHttpBuildQuery($value, $key);
      }
      // If a query parameter value is NULL, only append its key.
      elseif (!isset($value)) {
        $params[] = $key;
      }
      else {
        // For better readability of paths in query strings, we decode slashes.
        $params[] = $key . '=' . str_replace('%2F', '/', rawurlencode($value));
      }
    }
    return implode('&', $params);
  }

}
