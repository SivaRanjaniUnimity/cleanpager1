<?php

namespace Drupal\cleanpager\StackMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcher;

/**
 * Provides a HTTP middleware.
 */
class CleanPager implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The RequestStack service.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

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
   * Constructs a new TestMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param Symfony\Component\HttpFoundation\RequestStack $stack
   *   The decorated kernel.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The decorated kernel.
   * @param Drupal\Core\Path\PathMatcher $path_matcher
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel, RequestStack $stack, ConfigFactoryInterface $config_factory, PathMatcher $path_matcher) {
    $this->httpKernel = $http_kernel;
    $this->requestStack = $stack;
    $this->configFactory = $config_factory;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * Rewrite Urls.
   */
  public function rewriteUrl(Request $request, $path) {
    global $_cleanpager_rewritten;
    $path_array = explode('/', $path);
    if ($this->configFactory->get('cleanpager.settings')->get('cleanpager_add_trailing')) {
      array_pop($path_array);
    }
    if ($this->cleanPagerIsPagerElement(end($path_array))) {
      $_cleanpager_rewritten = FALSE;
      $p = array_pop($path_array);
      if (end($path_array) == 'page') {
        array_pop($path_array);
        $_cleanpager_rewritten = TRUE;
        $path = implode('/', $path_array);
        $current_path = $request->getPathInfo();
        $path_args = explode('/', $current_path);
        // $this->requestStack->getCurrentRequest()->query->all()
        if ($path_args[1] == 'views' && $path_args[2] == 'ajax' && !empty($this->requestStack->getCurrentRequest()->query->all()['view_path'])) {
          $path = '/views/ajax';
        }
        // $_REQUEST['page'] = $_GET['page'] = $p;
        $request->server->set('REQUEST_URI', $path);
        $request->server->set('REDIRECT_URL', $path);
        $query_string = $request->server->get('QUERY_STRING', $path);
        $request->server->set('QUERY_STRING', $query_string . '&page=' . $p);
        $request->query->add(['page' => $p]);
        $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent());
      }
    }
    return $request;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    global $_cleanpager_pagination;
    $path = $request->getPathInfo();

    if ($path_length = strpos($path, '/page/')) {
      $path_test_part = substr($path, 0, $path_length);
    }
    else {
      $path_test_part = $path;
    }
    $pages = $this->configFactory->get('cleanpager.settings')->get('cleanpager_pages');
    if ($this->pathMatcher->matchPath($path_test_part, $pages)) {
      $_cleanpager_pagination = TRUE;
      $result = $this->rewriteUrl($request, $path);
    }
    else {
      $_cleanpager_pagination = FALSE;
      $result = $request;
    }
    return $this->httpKernel->handle($result, $type, $catch);
  }

  /**
   * Handle pagers.
   */
  public function cleanPagerIsPagerElement($value) {
    if (is_numeric($value)) {
      return TRUE;
    }
    // Handle multiple pagers (i.e. 0,0,1,0);.
    $parts = explode(',', $value);
    foreach ($parts as $p) {
      if (!is_numeric($p)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
