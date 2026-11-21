<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Framework\Cache;
use Rhymix\Framework\HTTP;
use Rhymix\Modules\Img_boarder\Models\Config as ConfigModel;

/**
 * Img_boarder admin controller.
 */
class Admin extends Base {
  /**
   * GitHub raw info.xml URL for version comparison.
   */
  private const GITHUB_INFO_URL = 'https://raw.githubusercontent.com/moonhouse-80san/img_boarder/master/conf/info.xml';

  /**
   * Cache key / TTL for remote version.
   */
  private const VERSION_CACHE_KEY = 'img_boarder:github_version';
  private const VERSION_CACHE_TTL = 21600; // 6 hours

  /**
   * Initialize.
   */
  public function init() {
    $this->setTemplatePath($this->module_path . 'views/admin/');
  }

  /**
   * Display admin config (target boards only).
   */
  public function dispImg_boarderAdminConfig() {
    \Context::set('config', ConfigModel::getConfig());
    $boards = [];
    foreach (\ModuleModel::getMidList(['module' => 'board']) ?: [] as $board) {
      $boards[] = (object)[
        'module_srl' => intval($board->module_srl),
        'mid' => $board->mid,
        'title' => html_entity_decode(\Context::replaceUserLang($board->browser_title, true), ENT_QUOTES) ?: $board->mid,
      ];
    }
    usort($boards, fn($a, $b) => strnatcasecmp($a->title, $b->title));
    \Context::set('boards', $boards);
    $groups = [];
    foreach (\MemberModel::getGroups() ?: [] as $group) {
      $groups[] = (object)[
        'group_srl' => intval($group->group_srl),
        'title' => html_entity_decode(\Context::replaceUserLang($group->title, true), ENT_QUOTES) ?: strval($group->group_srl),
      ];
    }
    \Context::set('groups', $groups);
    \Context::set('version_info', $this->_getVersionInfo());
    $this->setTemplateFile('config');
  }

  /**
   * Save admin config.
   */
  public function procImg_boarderAdminInsertConfig() {
    $config = ConfigModel::getConfig();
    $config->target_srls = array_values(array_unique(array_filter(array_map('intval', (array)\Context::get('target_srls')))));
    $config->upload_group_srls = array_values(array_unique(array_filter(array_map('intval', (array)\Context::get('upload_group_srls')))));

    $output = ConfigModel::setConfig($config);
    if (!$output->toBool()) {
      return $output;
    }

    $this->setMessage('success_registed');
    $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispImg_boarderAdminConfig'));
  }

  /**
   * Compare local conf/info.xml version with GitHub master.
   *
   * @return object{local:string, remote:string, status:string, github_url:string}
   *   status: latest | update | unknown
   */
  private function _getVersionInfo() {
    $info = (object)[
      'local' => $this->_readLocalVersion(),
      'remote' => '',
      'status' => 'unknown',
      'github_url' => 'https://github.com/moonhouse-80san/img_boarder',
      'label' => '',
      'show' => false,
    ];

    $remote = $this->_fetchRemoteVersion();
    if (is_string($remote) && $remote !== '') {
      $info->remote = $remote;
    }

    if ($info->local !== '' && $info->remote !== '') {
      if (version_compare($info->local, $info->remote) < 0) {
        $info->status = 'update';
        $info->label = 'update';
      } else {
        $info->status = 'latest';
        $info->label = 'latest';
      }
      $info->show = true;
    } elseif ($info->local !== '') {
      $info->status = 'unknown';
      $info->label = 'local';
      $info->show = true;
    } elseif ($info->remote !== '') {
      $info->status = 'unknown';
      $info->label = 'remote';
      $info->show = true;
    }

    return $info;
  }

  /**
   * Read version from local conf/info.xml.
   * Reflection 으로 실제 로드된 클래스 파일 위치를 기준으로 찾는다.
   */
  private function _readLocalVersion(): string {
    $candidates = [];

    try {
      $file = (new \ReflectionClass($this))->getFileName();
      if ($file) {
        // .../modules/img_boarder/controllers/Admin.php → .../modules/img_boarder/conf/info.xml
        $candidates[] = dirname($file, 2) . '/conf/info.xml';
        $candidates[] = dirname(dirname($file)) . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'info.xml';
      }
    } catch (\Throwable $e) {
      // ignore
    }

    $candidates[] = dirname(__DIR__) . '/conf/info.xml';
    $candidates[] = __DIR__ . '/../conf/info.xml';

    if (!empty($this->module_path)) {
      $candidates[] = rtrim(str_replace('\\', '/', $this->module_path), '/') . '/conf/info.xml';
    }

    if (defined('RX_BASEDIR')) {
      $candidates[] = RX_BASEDIR . 'modules/img_boarder/conf/info.xml';
    }
    if (defined('_XE_PATH_')) {
      $candidates[] = _XE_PATH_ . 'modules/img_boarder/conf/info.xml';
    }

    foreach (array_unique($candidates) as $path) {
      $ver = $this->_parseVersionFromFile($path);
      if ($ver !== '') {
        return $ver;
      }
    }
    return '';
  }

  /**
   * Parse <version> from an info.xml path (simplexml 또는 정규식).
   */
  private function _parseVersionFromFile(string $path): string {
    if ($path === '' || !is_file($path) || !is_readable($path)) {
      return '';
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
      return '';
    }
    if (preg_match('/<version>\s*([^<\s]+)\s*<\/version>/i', $raw, $m)) {
      return trim($m[1]);
    }
    $xml = @simplexml_load_string($raw);
    if ($xml && isset($xml->version)) {
      $ver = trim((string)$xml->version);
      if ($ver !== '') {
        return $ver;
      }
    }
    return '';
  }

  /**
   * Fetch remote version from GitHub (cached).
   */
  private function _fetchRemoteVersion(): ?string {
    $cached = Cache::get(self::VERSION_CACHE_KEY);
    if (is_string($cached) && $cached !== '') {
      return $cached === '__fail__' ? null : $cached;
    }

    $version = null;
    try {
      $response = HTTP::get(self::GITHUB_INFO_URL, null, [], [], ['timeout' => 3]);
      $status = method_exists($response, 'getStatusCode') ? (int)$response->getStatusCode() : 0;
      if ($status >= 200 && $status < 300 && method_exists($response, 'getBody')) {
        $body = (string)$response->getBody();
        if (preg_match('/<version>\s*([^<\s]+)\s*<\/version>/i', $body, $m)) {
          $version = trim($m[1]);
        }
      }
    } catch (\Throwable $e) {
      $version = null;
    }

    // HTTP 클래스 실패 시 file_get_contents 폴백
    if ($version === null) {
      try {
        $ctx = stream_context_create(['http' => ['timeout' => 3], 'ssl' => ['verify_peer' => true]]);
        $body = @file_get_contents(self::GITHUB_INFO_URL, false, $ctx);
        if (is_string($body) && preg_match('/<version>\s*([^<\s]+)\s*<\/version>/i', $body, $m)) {
          $version = trim($m[1]);
        }
      } catch (\Throwable $e) {
        $version = null;
      }
    }

    Cache::set(self::VERSION_CACHE_KEY, $version ?? '__fail__', self::VERSION_CACHE_TTL);
    return $version;
  }
}
