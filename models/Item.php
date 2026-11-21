<?php

namespace Rhymix\Modules\Img_boarder\Models;

use Rhymix\Framework\Exceptions\QueryError;
use Rhymix\Modules\Img_boarder\Models\Border as BorderModel;

/**
 * Per-image border overrides of a document. An image is identified by the file name in its src (img_key).
 * Images without a row follow the document default (Border model).
 */
class Item {
  public const MAX_ITEMS = 50;

  /**
   * Overrides of a document, oldest first.
   *
   * @param int $documentSrl
   * @return \stdClass[]
   */
  public static function getList(int $documentSrl): array {
    $args = new \stdClass;
    $args->document_srl = $documentSrl;
    $output = executeQueryArray('img_boarder.getItems', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
    return $output->data ?: [];
  }

  /**
   * Replace all overrides of a document with the submitted set.
   * Entries whose image is no longer in the content are dropped (only when the content is known).
   *
   * @param int $documentSrl
   * @param string $json Submitted ib_items value: [{key, width, color, style, radius, shadow, frame, slice, repeat}, ...]
   * @param string $content Saved document content, '' when unknown
   * @return void
   */
  public static function saveAll(int $documentSrl, string $json, string $content): void {
    $list = json_decode($json, true);
    if (!is_array($list)) {
      return;
    }
    // Same key rule as write.js / the document page script: file name of src, without query/hash, URL-decoded.
    $present = $content === '' ? null : [];
    if ($present !== null && preg_match_all('/<img\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1/is', $content, $matches)) {
      foreach ($matches[2] as $src) {
        $present[rawurldecode(basename(explode('#', explode('?', html_entity_decode($src, ENT_QUOTES))[0])[0]))] = true;
      }
    }
    $rows = [];
    foreach ($list as $entry) {
      $key = is_array($entry) ? mb_substr(strval($entry['key'] ?? ''), 0, 255) : '';
      if ($key === '' || isset($rows[$key]) || ($present !== null && !isset($present[$key])) || count($rows) >= self::MAX_ITEMS) {
        continue;
      }
      $rows[$key] = BorderModel::normalize($entry);
    }
    self::deleteAll($documentSrl);
    foreach ($rows as $key => $values) {
      $args = (object)($values + [
        'item_srl' => getNextSequence(),
        'document_srl' => $documentSrl,
        'img_key' => strval($key),
      ]);
      $output = executeQuery('img_boarder.insertItem', $args);
      if (!$output->toBool()) {
        throw new QueryError($output->getMessage());
      }
    }
  }

  /**
   * Delete all overrides of a document.
   *
   * @param int $documentSrl
   * @return void
   */
  public static function deleteAll(int $documentSrl): void {
    $args = new \stdClass;
    $args->document_srl = $documentSrl;
    $output = executeQuery('img_boarder.deleteItems', $args);
    if (!$output->toBool()) {
      throw new QueryError($output->getMessage());
    }
  }
}
