<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Framework\DB;
use Rhymix\Modules\Img_boarder\Models\Border as BorderModel;

/**
 * Img_boarder install/update handler.
 * Ensures min_image_width / border_radius columns exist on existing installs.
 */
class Install extends Base {
  /**
   * Module install callback.
   *
   * @return \BaseObject
   */
  public function moduleInstall() {
    return new \BaseObject();
  }

  /**
   * Check if the module needs to be updated.
   *
   * @return bool
   */
  public function checkUpdate() {
    $oDB = DB::getInstance();
    return $oDB->isTableExists('img_boarder')
      && (!$oDB->isColumnExists('img_boarder', 'min_image_width') || !$oDB->isColumnExists('img_boarder', 'border_radius'));
  }

  /**
   * Module update callback.
   *
   * @return \BaseObject
   */
  public function moduleUpdate() {
    $this->_ensureMinImageWidthColumn();
    $this->_ensureBorderRadiusColumn();
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
   * Add border_radius column if missing (existing installs).
   *
   * @return void
   */
  protected function _ensureBorderRadiusColumn(): void {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder') || $oDB->isColumnExists('img_boarder', 'border_radius')) {
      return;
    }
    $oDB->addColumn('img_boarder', 'border_radius', 'number', 11, 0, true);
  }
}
