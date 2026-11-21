<?php

namespace Rhymix\Modules\Img_boarder\Models;

use Rhymix\Framework\Exceptions\QueryError;
use Rhymix\Modules\Img_boarder\Models\Frame as FrameModel;

/**
 * Per-document image border data (width / color / style / radius / shadow / min width / frame image).
 */
class Border {
  public const DEFAULT_COLOR = '#ffaa56';
  public const DEFAULT_STYLE = 'solid';
  public const DEFAULT_MIN_WIDTH = 350;
  public const MAX_WIDTH = 20;
  public const MAX_RADIUS = 100;

  /**
   * Allowed CSS border-style values.
   */
  public const STYLES = [
    'solid' => '실선',
    'double' => '이중선',
    'dashed' => '점선(대시)',
    'dotted' => '점선(도트)',
    'groove' => '그루브',
    'ridge' => '릿지',
    'inset' => '인셋',
    'outset' => '아웃셋',
  ];

  /**
   * Get saved border data of a document.
   *
   * @param int $documentSrl
   * @return \stdClass|null
   */
  public static function get(int $documentSrl): ?\stdClass {
    $args = new \stdClass;
    $args->document_srl = $documentSrl;
    $output = executeQuery('img_boarder.getBorder', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return $output->data ?: null;
  }

  /**
   * Validate one set of border values (document default or a single image override).
   *
   * @param array $in Keys: width, color, style, radius, shadow ('Y'), frame (frame_srl), slice, repeat
   * @return array Column values: border_width, border_color, border_style, border_radius, shadow, frame_srl, frame_slice, frame_repeat
   */
  public static function normalize(array $in): array {
    $color = strval($in['color'] ?? '');
    if (!preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
      $color = self::DEFAULT_COLOR;
    }
    $style = strval($in['style'] ?? self::DEFAULT_STYLE);
    $frame = intval($in['frame'] ?? 0) > 0 ? FrameModel::get(intval($in['frame'])) : null;
    $frameSlice = 0;
    $frameRepeat = FrameModel::DEFAULT_REPEAT;
    if ($frame) {
      // Per-document corner size / fill mode; falls back to the image's own defaults.
      $maxSlice = max(1, intval(floor(min(intval($frame->img_width), intval($frame->img_height)) / 2)));
      $frameSlice = intval($in['slice'] ?? 0);
      $frameSlice = $frameSlice > 0 ? min($frameSlice, $maxSlice) : intval($frame->slice);
      $frameRepeat = in_array($in['repeat'] ?? '', FrameModel::REPEATS, true) ? $in['repeat'] : $frame->repeat_mode;
    }
    return [
      'border_width' => max(0, min(self::MAX_WIDTH, intval($in['width'] ?? 0))),
      'border_color' => $color,
      'border_style' => isset(self::STYLES[$style]) ? $style : self::DEFAULT_STYLE,
      'border_radius' => max(0, min(self::MAX_RADIUS, intval($in['radius'] ?? 0))),
      'shadow' => ($in['shadow'] ?? '') === 'Y' ? 'Y' : 'N',
      'frame_srl' => $frame ? intval($frame->frame_srl) : 0,
      'frame_slice' => $frameSlice,
      'frame_repeat' => $frameRepeat,
    ];
  }

  /**
   * Validate request values and insert/update/delete the row.
   * Width 0, radius 0, no shadow and no frame image means "nothing to apply", so the row is removed.
   *
   * @param int $documentSrl
   * @param int $moduleSrl
   * @param object $vars Request variables (ib_width, ib_color, ib_style, ib_radius, ib_shadow, ib_min_width, ib_frame, ib_frame_slice, ib_frame_repeat)
   * @return void
   */
  public static function save(int $documentSrl, int $moduleSrl, object $vars): void {
    $values = self::normalize([
      'width' => $vars->ib_width ?? 0,
      'color' => $vars->ib_color ?? '',
      'style' => $vars->ib_style ?? self::DEFAULT_STYLE,
      'radius' => $vars->ib_radius ?? 0,
      'shadow' => $vars->ib_shadow ?? '',
      'frame' => $vars->ib_frame ?? 0,
      'slice' => $vars->ib_frame_slice ?? 0,
      'repeat' => $vars->ib_frame_repeat ?? '',
    ]);
    $existing = self::get($documentSrl);
    $minWidth = intval($vars->ib_min_width ?? self::DEFAULT_MIN_WIDTH);
    if ($minWidth < 0) {
      $minWidth = self::DEFAULT_MIN_WIDTH;
    }
    if ($values['border_width'] === 0 && $values['border_radius'] === 0 && $values['shadow'] === 'N' && $values['frame_srl'] === 0) {
      self::delete($documentSrl);
      return;
    }
    $args = (object)($values + [
      'document_srl' => $documentSrl,
      'module_srl' => $moduleSrl,
      'min_image_width' => $minWidth,
    ]);
    $output = executeQuery('img_boarder.' . ($existing ? 'updateBorder' : 'insertBorder'), $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
  }

  /**
   * Delete border data of a document.
   *
   * @param int $documentSrl
   * @return void
   */
  public static function delete(int $documentSrl): void {
    $args = new \stdClass;
    $args->document_srl = $documentSrl;
    $output = executeQuery('img_boarder.deleteBorder', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
  }
}
