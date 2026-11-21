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
   * @return object{local:string, remote:?string, status:string, github_url:string}
   *   status: latest | update | unknown
   */
  private function _getVersionInfo() {
    $info = (object)[
      'local' => $this->_readLocalVersion(),
      'remote' => null,
      'status' => 'unknown',
      'github_url' => 'https://github.com/moonhouse-80san/img_boarder',
    ];

    $remote = $this->_fetchRemoteVersion();
    if ($remote === null || $remote === '') {
      return $info;
    }

    $info->remote = $remote;
    $cmp = version_compare($info->local, $remote);
    if ($cmp < 0) {
      $info->status = 'update';
    } elseif ($cmp === 0) {
      $info->status = 'latest';
    } else {
      // local newer than GitHub (dev / pre-release)
      $info->status = 'latest';
    }
    return $info;
  }

  /**
   * Read version from local conf/info.xml.
   */
  private function _readLocalVersion(): string {
    $path = $this->module_path . 'conf/info.xml';
    if (!is_readable($path)) {
      return '0';
    }
    $xml = @simplexml_load_file($path);
    if (!$xml || !isset($xml->version)) {
      return '0';
    }
    $ver = trim((string)$xml->version);
    return $ver !== '' ? $ver : '0';
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
        $xml = @simplexml_load_string($body);
        if ($xml && isset($xml->version)) {
          $ver = trim((string)$xml->version);
          if ($ver !== '') {
            $version = $ver;
          }
        }
      }
    } catch (\Throwable $e) {
      $version = null;
    }

    Cache::set(self::VERSION_CACHE_KEY, $version ?? '__fail__', self::VERSION_CACHE_TTL);
    return $version;
  }
}
