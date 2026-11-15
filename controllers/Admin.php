<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Modules\Img_boarder\Models\Config as ConfigModel;

/**
 * Img_boarder admin controller.
 */
class Admin extends Base {
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
    $this->setTemplateFile('config');
  }

  /**
   * Save admin config.
   */
  public function procImg_boarderAdminInsertConfig() {
    $config = ConfigModel::getConfig();
    $config->target_srls = array_values(array_unique(array_filter(array_map('intval', (array)\Context::get('target_srls')))));

    $output = ConfigModel::setConfig($config);
    if (!$output->toBool()) {
      return $output;
    }

    $this->setMessage('success_registed');
    $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispImg_boarderAdminConfig'));
  }
}
