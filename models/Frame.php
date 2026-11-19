<?php

namespace Rhymix\Modules\Img_boarder\Models;

use Rhymix\Framework\Exceptions\QueryError;

/**
 * Uploaded border image ("frame") library. Frames are shared: any member can pick any frame, only allowed groups can upload.
 */
class Frame {
  public const MAX_PER_MEMBER = 30;
  public const MAX_FILE_SIZE = 1048576;
  public const MAX_DIMENSION = 1000;
  public const DEFAULT_SLICE = 20;

  /**
   * Built-in frames shipped in tpl/img/ and registered on install/update (stored file name => settings).
   */
  public const BUILTINS = [
    'default_20_1.png' => ['source' => '20_1.png', 'name' => '20_1.png', 'slice' => 20, 'repeat' => 'stretch'],
    'default_5_2.png' => ['source' => '5_2.png', 'name' => '5_2.png', 'slice' => 5, 'repeat' => 'stretch'],
  ];

  /**
   * Built-in frames whose stored file name changed (old => new). Registered rows and files are renamed on module update.
   */
  public const RENAMED_BUILTINS = [
    'default_5_1.png' => 'default_5_2.png',
  ];

  public const DEFAULT_REPEAT = 'stretch';
  public const REPEATS = ['stretch', 'round', 'repeat'];

  /**
   * Allowed image types => stored extension. SVG is intentionally not allowed.
   */
  public const TYPES = [
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_WEBP => 'webp',
  ];

  /**
   * Storage directory (absolute path, trailing slash).
   *
   * @return string
   */
  public static function getDir(): string {
    return \RX_BASEDIR . 'files/attach/img_boarder/';
  }

  /**
   * Public URL of a frame image.
   *
   * @param \stdClass $frame
   * @return string
   */
  public static function getUrl(\stdClass $frame): string {
    return \RX_BASEURL . 'files/attach/img_boarder/' . $frame->file_name;
  }

  /**
   * @param int $frameSrl
   * @return \stdClass|null
   */
  public static function get(int $frameSrl): ?\stdClass {
    $args = new \stdClass;
    $args->frame_srl = $frameSrl;
    $output = executeQuery('img_boarder.getFrame', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return $output->data ?: null;
  }

  /**
   * Shared library: frames of all members, newest first.
   *
   * @param int $limit
   * @return \stdClass[]
   */
  public static function getAll(int $limit = 200): array {
    $args = new \stdClass;
    $args->list_count = $limit;
    $output = executeQueryArray('img_boarder.getAllFrames', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return $output->data ?: [];
  }

  /**
   * Built-in frames shipped with the module (member_srl 0), oldest first. They cannot be deleted from the write form.
   *
   * @return \stdClass[]
   */
  public static function getSystem(): array {
    $output = executeQueryArray('img_boarder.getSystemFrames');
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return $output->data ?: [];
  }

  /**
   * Change the stored file name of a frame row.
   *
   * @param int $frameSrl
   * @param string $fileName
   * @return void
   */
  public static function rename(int $frameSrl, string $fileName): void {
    $args = new \stdClass;
    $args->frame_srl = $frameSrl;
    $args->file_name = $fileName;
    $output = executeQuery('img_boarder.renameFrame', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
  }

  /**
   * Update a frame's display name and default corner size / fill mode (used to keep built-in frames in sync with BUILTINS).
   *
   * @param int $frameSrl
   * @param string $name
   * @param int $slice
   * @param string $repeat
   * @return void
   */
  public static function updateOptions(int $frameSrl, string $name, int $slice, string $repeat): void {
    $args = new \stdClass;
    $args->frame_srl = $frameSrl;
    $args->orig_name = $name;
    $args->slice = $slice;
    $args->repeat_mode = $repeat;
    $output = executeQuery('img_boarder.updateFrame', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
  }

  /**
   * Whether the current visitor may upload border images:
   * admins, or members of a group selected in the admin config.
   *
   * @return bool
   */
  public static function canUpload(): bool {
    $logged = \Context::get('logged_info');
    $memberSrl = intval($logged->member_srl ?? 0);
    if (!$memberSrl) {
      return false;
    }
    if (($logged->is_admin ?? 'N') === 'Y') {
      return true;
    }
    $allowed = Config::getConfig()->upload_group_srls;
    return $allowed && array_intersect($allowed, array_map('intval', array_keys(\MemberModel::getMemberGroups($memberSrl) ?: [])));
  }

  /**
   * Whether the current visitor may delete a frame: its uploader or an admin. Built-in frames (member_srl 0) are admin-only.
   *
   * @param \stdClass $frame
   * @return bool
   */
  public static function canDelete(\stdClass $frame): bool {
    $logged = \Context::get('logged_info');
    $memberSrl = intval($logged->member_srl ?? 0);
    return $memberSrl > 0 && (intval($frame->member_srl) === $memberSrl || ($logged->is_admin ?? 'N') === 'Y');
  }

  /**
   * @param int $memberSrl
   * @return int
   */
  public static function countByMember(int $memberSrl): int {
    $args = new \stdClass;
    $args->member_srl = $memberSrl;
    $output = executeQuery('img_boarder.countFramesByMember', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return intval($output->data->count ?? 0);
  }

  /**
   * How many documents use this frame.
   *
   * @param int $frameSrl
   * @return int
   */
  public static function countUse(int $frameSrl): int {
    $args = new \stdClass;
    $args->frame_srl = $frameSrl;
    $output = executeQuery('img_boarder.countFrameUse', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return intval($output->data->count ?? 0);
  }

  /**
   * @param \stdClass $args frame_srl, member_srl, file_name, orig_name, img_width, img_height, slice, repeat_mode
   * @return void
   */
  public static function insert(\stdClass $args): void {
    $output = executeQuery('img_boarder.insertFrame', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
  }

  /**
   * Delete the row and the stored file.
   *
   * @param \stdClass $frame
   * @return void
   */
  public static function delete(\stdClass $frame): void {
    $args = new \stdClass;
    $args->frame_srl = intval($frame->frame_srl);
    $output = executeQuery('img_boarder.deleteFrame', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    \Rhymix\Framework\Storage::delete(self::getDir() . $frame->file_name);
    // A deleted built-in frame must not come back on the next module update.
    if (intval($frame->member_srl) === 0 && isset(self::BUILTINS[$frame->file_name])) {
      $config = Config::getConfig();
      $config->deleted_builtins = array_values(array_unique(array_merge($config->deleted_builtins, [$frame->file_name])));
      Config::setConfig($config);
    }
  }
}
