<?php

namespace Rhymix\Modules\Img_boarder\Models;

use ModuleController;
use ModuleModel;

/**
 * Img_boarder configuration model.
 */
class Config {
  /**
   * Default minimum image width (px) used when a document has no saved value.
   */
  public const DEFAULT_MIN_WIDTH = 350;

  /**
   * Cache for module config.
   */
  protected static $_cache = null;

  /**
   * Get module config.
   * - target_srls: board module_srl list (empty = all boards)
   *
   * @return object
   */
  public static function getConfig() {
    if (self::$_cache === null) {
      self::$_cache = ModuleModel::getModuleConfig('img_boarder') ?: new \stdClass;
      self::$_cache->target_srls = array_map('intval', (array)(self::$_cache->target_srls ?? []));
    }
    return self::$_cache;
  }

  /**
   * Save module config.
   *
   * @param object $config
   * @return object
   */
  public static function setConfig($config) {
    $result = ModuleController::getInstance()->insertModuleConfig('img_boarder', $config);
    if ($result->toBool()) {
      self::$_cache = $config;
    }
    return $result;
  }
}
