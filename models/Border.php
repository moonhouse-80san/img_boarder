<?php

namespace Rhymix\Modules\Img_boarder\Models;

use Rhymix\Framework\Exceptions\QueryError;

/**
 * Per-document image border data (width / color / style / shadow / min width).
 */
class Border {
  public const DEFAULT_COLOR = '#ffaa56';
  public const DEFAULT_STYLE = 'solid';
  public const DEFAULT_MIN_WIDTH = 350;
  public const MAX_WIDTH = 20;

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
   * Validate request values and insert/update/delete the row.
   * Width 0 and no shadow means "nothing to apply", so the row is removed.
   *
   * @param int $documentSrl
   * @param int $moduleSrl
   * @param object $vars Request variables (ib_width, ib_color, ib_style, ib_shadow, ib_min_width)
   * @return void
   */
  public static function save(int $documentSrl, int $moduleSrl, object $vars): void {
    $width = max(0, min(self::MAX_WIDTH, intval($vars->ib_width ?? 0)));
    $color = strval($vars->ib_color ?? '');
    if (!preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
      $color = self::DEFAULT_COLOR;
    }
    $style = strval($vars->ib_style ?? self::DEFAULT_STYLE);
    if (!isset(self::STYLES[$style])) {
      $style = self::DEFAULT_STYLE;
    }
    $shadow = ($vars->ib_shadow ?? '') === 'Y' ? 'Y' : 'N';
    $minWidth = intval($vars->ib_min_width ?? self::DEFAULT_MIN_WIDTH);
    if ($minWidth < 0) {
      $minWidth = self::DEFAULT_MIN_WIDTH;
    }
    if ($width === 0 && $shadow === 'N') {
      self::delete($documentSrl);
      return;
    }
    $args = new \stdClass;
    $args->document_srl = $documentSrl;
    $args->module_srl = $moduleSrl;
    $args->border_width = $width;
    $args->border_color = $color;
    $args->border_style = $style;
    $args->shadow = $shadow;
    $args->min_image_width = $minWidth;
    $output = executeQuery('img_boarder.' . (self::get($documentSrl) ? 'updateBorder' : 'insertBorder'), $args);
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
