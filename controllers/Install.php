<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use ModuleModel;
use Rhymix\Framework\DB;
use Rhymix\Modules\Img_boarder\Models\Border as BorderModel;
use Rhymix\Modules\Img_boarder\Models\Config as ConfigModel;

/**
 * Img_boarder install/update handler.
 * Migrates data from legacy module mh_img_boarder when present.
 * Ensures min_image_width column exists on existing installs.
 */
class Install extends Base {
  /**
   * Legacy table / config name.
   */
  private const LEGACY_MODULE = 'mh_img_boarder';

  /**
   * Module install callback.
   *
   * @return \BaseObject
   */
  public function moduleInstall() {
    $this->_migrateFromLegacy();
    return new \BaseObject();
  }

  /**
   * Check if the module needs to be updated.
   *
   * @return bool
   */
  public function checkUpdate() {
    $oDB = DB::getInstance();
    if ($oDB->isTableExists('img_boarder') && !$oDB->isColumnExists('img_boarder', 'min_image_width')) {
      return true;
    }
    return $this->_needsLegacyMigration();
  }

  /**
   * Module update callback.
   *
   * @return \BaseObject
   */
  public function moduleUpdate() {
    $this->_ensureMinImageWidthColumn();
    $this->_migrateFromLegacy();
    return new \BaseObject();
  }

  /**
   * Cache recompile callback.
   *
   * @return void
   */
  public function recompileCache() {

  }

  /**
   * Add min_image_width column if missing (existing installs).
   *
   * @return void
   */
  protected function _ensureMinImageWidthColumn(): void {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder') || $oDB->isColumnExists('img_boarder', 'min_image_width')) {
      return;
    }
    $oDB->addColumn('img_boarder', 'min_image_width', 'number', 11, BorderModel::DEFAULT_MIN_WIDTH, true);
  }

  /**
   * Whether legacy table/config still has data to copy.
   *
   * @return bool
   */
  protected function _needsLegacyMigration(): bool {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists(self::LEGACY_MODULE) || !$oDB->isTableExists('img_boarder')) {
      return $this->_needsConfigMigration();
    }
    $stmt = $oDB->query(
      'SELECT COUNT(*) AS cnt FROM ' . self::LEGACY_MODULE
      . ' WHERE document_srl NOT IN (SELECT document_srl FROM img_boarder)'
    );
    $row = $oDB->fetch($stmt);
    if ($row && intval($row->cnt) > 0) {
      return true;
    }
    return $this->_needsConfigMigration();
  }

  /**
   * Whether old module config should be copied into img_boarder config.
   *
   * @return bool
   */
  protected function _needsConfigMigration(): bool {
    $old = ModuleModel::getModuleConfig(self::LEGACY_MODULE);
    if (!$old) {
      return false;
    }
    $new = ModuleModel::getModuleConfig('img_boarder');
    if (!$new) {
      return true;
    }
    $oldTargets = array_filter((array)($old->target_srls ?? []));
    $newTargets = array_filter((array)($new->target_srls ?? []));
    if ($oldTargets && !$newTargets) {
      return true;
    }
    if (!isset($new->migrated_from_mh)) {
      return true;
    }
    return false;
  }

  /**
   * Copy rows and config from mh_img_boarder → img_boarder.
   * Idempotent: only inserts missing document_srl rows.
   *
   * @return void
   */
  protected function _migrateFromLegacy(): void {
    $oDB = DB::getInstance();
    $this->_ensureMinImageWidthColumn();

    if ($oDB->isTableExists(self::LEGACY_MODULE) && $oDB->isTableExists('img_boarder')) {
      $hasMinCol = $oDB->isColumnExists('img_boarder', 'min_image_width');
      if ($hasMinCol) {
        $oDB->query(
          'INSERT INTO img_boarder'
          . ' (document_srl, module_srl, border_width, border_color, border_style, shadow, min_image_width, regdate)'
          . ' SELECT document_srl, module_srl, border_width, border_color, \'solid\', shadow, '
          . BorderModel::DEFAULT_MIN_WIDTH . ', regdate'
          . ' FROM ' . self::LEGACY_MODULE
          . ' WHERE document_srl NOT IN (SELECT document_srl FROM img_boarder)'
        );
      } else {
        $oDB->query(
          'INSERT INTO img_boarder'
          . ' (document_srl, module_srl, border_width, border_color, border_style, shadow, regdate)'
          . ' SELECT document_srl, module_srl, border_width, border_color, \'solid\', shadow, regdate'
          . ' FROM ' . self::LEGACY_MODULE
          . ' WHERE document_srl NOT IN (SELECT document_srl FROM img_boarder)'
        );
      }
    }

    $old = ModuleModel::getModuleConfig(self::LEGACY_MODULE);
    if (!$old) {
      return;
    }
    $config = ConfigModel::getConfig();
    $oldTargets = array_values(array_unique(array_filter(array_map('intval', (array)($old->target_srls ?? [])))));
    if ($oldTargets && empty($config->target_srls)) {
      $config->target_srls = $oldTargets;
    }
    $config->migrated_from_mh = true;
    ConfigModel::setConfig($config);
  }
}
