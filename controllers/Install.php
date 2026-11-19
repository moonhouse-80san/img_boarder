<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Framework\DB;
use Rhymix\Modules\Img_boarder\Models\Border as BorderModel;
use Rhymix\Modules\Img_boarder\Models\Config as ConfigModel;
use Rhymix\Modules\Img_boarder\Models\Frame as FrameModel;

/**
 * Img_boarder install/update handler.
 * Ensures min_image_width / border_radius / frame_srl columns and the frames table exist on existing installs.
 */
class Install extends Base {
  /**
   * Module install callback.
   *
   * @return \BaseObject
   */
  public function moduleInstall() {
    $this->_ensureDefaultFrames();
    return new \BaseObject();
  }

  /**
   * Check if the module needs to be updated.
   *
   * @return bool
   */
  public function checkUpdate() {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder')) {
      return false;
    }
    return !$oDB->isTableExists('img_boarder_frames')
      || !$oDB->isColumnExists('img_boarder', 'min_image_width')
      || !$oDB->isColumnExists('img_boarder', 'border_radius')
      || !$oDB->isColumnExists('img_boarder', 'frame_srl')
      || !$oDB->isColumnExists('img_boarder', 'frame_slice')
      || !$oDB->isColumnExists('img_boarder', 'frame_repeat')
      || $this->_renamedBuiltins()
      || $this->_missingBuiltins()
      || $this->_outdatedBuiltins();
  }

  /**
   * Module update callback.
   *
   * @return \BaseObject
   */
  public function moduleUpdate() {
    $this->_ensureMinImageWidthColumn();
    $this->_ensureBorderRadiusColumn();
    $this->_ensureFrameSrlColumn();
    $this->_ensureFrameOptionColumns();
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder_frames')) {
      $oDB->createTable($this->module_path . 'schemas/img_boarder_frames.xml');
    }
    $this->_renameBuiltins();
    $this->_ensureDefaultFrames();
    foreach ($this->_outdatedBuiltins() as $frame) {
      $builtin = FrameModel::BUILTINS[$frame->file_name];
      FrameModel::updateOptions(intval($frame->frame_srl), $builtin['name'], intval($builtin['slice']), $builtin['repeat']);
    }
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

  /**
   * Add frame_srl column if missing (existing installs).
   *
   * @return void
   */
  protected function _ensureFrameSrlColumn(): void {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder') || $oDB->isColumnExists('img_boarder', 'frame_srl')) {
      return;
    }
    $oDB->addColumn('img_boarder', 'frame_srl', 'number', 11, 0, true);
  }

  /**
   * Add per-document frame slice / repeat columns if missing (existing installs).
   *
   * @return void
   */
  protected function _ensureFrameOptionColumns(): void {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder')) {
      return;
    }
    if (!$oDB->isColumnExists('img_boarder', 'frame_slice')) {
      $oDB->addColumn('img_boarder', 'frame_slice', 'number', 11, 0, true);
    }
    if (!$oDB->isColumnExists('img_boarder', 'frame_repeat')) {
      $oDB->addColumn('img_boarder', 'frame_repeat', 'varchar', 8, 'stretch', true);
    }
  }

  /**
   * Registered built-in frames still stored under an old file name (FrameModel::RENAMED_BUILTINS).
   *
   * @return \stdClass[]
   */
  protected function _renamedBuiltins(): array {
    return array_values(array_filter(FrameModel::getSystem(), fn($frame) => isset(FrameModel::RENAMED_BUILTINS[$frame->file_name])));
  }

  /**
   * Rename old built-in frame files and rows so they look like they were always stored under the new name.
   *
   * @return void
   */
  protected function _renameBuiltins(): void {
    $dir = FrameModel::getDir();
    $registered = array_column(FrameModel::getSystem(), 'file_name');
    foreach ($this->_renamedBuiltins() as $frame) {
      $new = FrameModel::RENAMED_BUILTINS[$frame->file_name];
      if (in_array($new, $registered, true)) {
        continue;
      }
      $old = $dir . $frame->file_name;
      $path = $dir . $new;
      if (!\Rhymix\Framework\Storage::exists($path)) {
        $done = \Rhymix\Framework\Storage::exists($old)
          ? \Rhymix\Framework\Storage::move($old, $path)
          : \Rhymix\Framework\Storage::copy($this->module_path . 'tpl/img/' . FrameModel::BUILTINS[$new]['source'], $path);
        if (!$done) {
          continue;
        }
        @chmod($path, 0644);
      } else {
        \Rhymix\Framework\Storage::delete($old);
      }
      FrameModel::rename(intval($frame->frame_srl), $new);
    }
  }

  /**
   * Built-in frames (FrameModel::BUILTINS) not registered yet and not deleted by an admin.
   *
   * @return string[] stored file names
   */
  protected function _missingBuiltins(): array {
    $registered = array_column(FrameModel::getSystem(), 'file_name');
    return array_values(array_diff(array_keys(FrameModel::BUILTINS), $registered, ConfigModel::getConfig()->deleted_builtins));
  }

  /**
   * Registered built-in frames whose name / corner size / fill mode differ from FrameModel::BUILTINS.
   *
   * @return \stdClass[]
   */
  protected function _outdatedBuiltins(): array {
    return array_values(array_filter(FrameModel::getSystem(), function ($frame) {
      $builtin = FrameModel::BUILTINS[$frame->file_name] ?? null;
      return $builtin && ($frame->orig_name !== $builtin['name'] || intval($frame->slice) !== $builtin['slice'] || $frame->repeat_mode !== $builtin['repeat']);
    }));
  }

  /**
   * Register the built-in border images (tpl/img/*) that are missing.
   *
   * @return void
   */
  protected function _ensureDefaultFrames(): void {
    $oDB = DB::getInstance();
    if (!$oDB->isTableExists('img_boarder_frames')) {
      return;
    }
    $dir = FrameModel::getDir();
    foreach ($this->_missingBuiltins() as $fileName) {
      $builtin = FrameModel::BUILTINS[$fileName];
      $source = $this->module_path . 'tpl/img/' . $builtin['source'];
      $path = $dir . $fileName;
      $info = is_file($source) ? @getimagesize($source) : false;
      if (!$info || !(\Rhymix\Framework\Storage::isDirectory($dir) || \Rhymix\Framework\Storage::createDirectory($dir))
        || !\Rhymix\Framework\Storage::copy($source, $path)) {
        continue;
      }
      @chmod($path, 0644);
      $args = new \stdClass;
      $args->frame_srl = getNextSequence();
      $args->member_srl = 0;
      $args->file_name = $fileName;
      $args->orig_name = $builtin['name'];
      $args->img_width = $info[0];
      $args->img_height = $info[1];
      $args->slice = $builtin['slice'];
      $args->repeat_mode = $builtin['repeat'];
      FrameModel::insert($args);
    }
  }
}
