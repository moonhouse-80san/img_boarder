<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Modules\Img_boarder\Models\Border as BorderModel;
use Rhymix\Modules\Img_boarder\Models\Config as ConfigModel;
use Rhymix\Modules\Img_boarder\Models\Frame as FrameModel;
use Rhymix\Modules\Img_boarder\Models\Item as ItemModel;

/**
 * Img_boarder event handlers (triggers).
 */
class EventHandlers extends Base {
  /**
   * After module proc: on a board write page add the option box,
   * on a board document page add the border/shadow CSS + size filter script.
   *
   * @param object $oModule
   * @return void
   */
  public function afterModuleProc(object $oModule): void {
    if ($oModule->module !== 'board' || !$oModule->toBool() || !in_array($oModule->act, ['dispBoardWrite', 'dispBoardContent'], true)) {
      return;
    }
    $config = ConfigModel::getConfig();
    $targetSrls = $config->target_srls;
    if ($targetSrls && !in_array(intval($oModule->module_srl), $targetSrls, true)) {
      return;
    }
    $documentSrl = intval(\Context::get('document_srl'));
    $border = $documentSrl ? BorderModel::get($documentSrl) : null;

    if ($oModule->act === 'dispBoardContent') {
      $items = $documentSrl ? ItemModel::getList($documentSrl) : [];
      if (!$border && !$items) {
        return;
      }
      $minWidth = ($border && isset($border->min_image_width))
        ? max(0, intval($border->min_image_width))
        : BorderModel::DEFAULT_MIN_WIDTH;
      $css = '';
      $defaultClass = '';
      $defaultCss = $border ? $this->_buildCss($border) : '';
      if ($defaultCss !== '') {
        $defaultClass = 'ib_applied_img_' . $documentSrl;
        $css .= sprintf('.%s{%s}', $defaultClass, $defaultCss);
      }
      // Per-image overrides: img_key => class name ('' = this image gets no border at all).
      $classMap = [];
      foreach ($items as $index => $item) {
        $itemCss = $this->_buildCss($item);
        $classMap[$item->img_key] = '';
        if ($itemCss !== '') {
          $classMap[$item->img_key] = sprintf('ib_item_%d_%d', $documentSrl, $index);
          $css .= sprintf('.%s{%s}', $classMap[$item->img_key], $itemCss);
        }
      }
      if ($css === '') {
        return;
      }
      $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
      $js = strtr(
        '<script>(function(){var m=%MIN%,d=%DEF%,map=%MAP%,s=%SEL%;'
        . 'function k(u){u=String(u||"").split("#")[0].split("?")[0];u=u.substring(u.lastIndexOf("/")+1);'
        . 'try{u=decodeURIComponent(u);}catch(e){}return u;}'
        . 'function apply(){document.querySelectorAll(s).forEach(function(img){'
        . 'var key=k(img.getAttribute("src"));'
        . 'if(Object.prototype.hasOwnProperty.call(map,key)){if(map[key]){img.classList.add(map[key]);}return;}'
        . 'if(!d){return;}'
        . 'var w=img.naturalWidth||img.width||img.offsetWidth||0;'
        . 'if(w>=m){img.classList.add(d);}'
        . '});}'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",apply);}'
        . 'else{apply();}'
        . 'window.addEventListener("load",apply);'
        . '})();</script>',
        [
          '%MIN%' => $minWidth,
          '%DEF%' => json_encode($defaultClass, $jsonFlags),
          '%MAP%' => json_encode((object)$classMap, $jsonFlags),
          '%SEL%' => json_encode(sprintf('[class*="document_%d_"] img', $documentSrl), $jsonFlags),
        ]
      );
      \Context::addHtmlHeader('<style>' . $css . '</style>');
      \Context::addHtmlFooter($js);
      return;
    }

    // Write form option box
    $width = $border ? intval($border->border_width) : 0;
    $color = $border ? escape($border->border_color) : BorderModel::DEFAULT_COLOR;
    $currentStyle = ($border && isset(BorderModel::STYLES[$border->border_style ?? '']))
      ? $border->border_style
      : BorderModel::DEFAULT_STYLE;
    $radius = $border ? max(0, min(BorderModel::MAX_RADIUS, intval($border->border_radius ?? 0))) : 0;
    $minWidth = $border && isset($border->min_image_width)
      ? max(0, intval($border->min_image_width))
      : BorderModel::DEFAULT_MIN_WIDTH;
    $chips = '';
    foreach (['#ffb3b3', '#ffd3a8', '#fff3a8', '#c4e8c8', '#bbdcff', '#a8b4e8', '#dbbcff', '#222222', '#ffffff'] as $chip) {
      $chips .= sprintf('<button type="button" class="ib_chip" data-color="%1$s" style="--ib-c:%1$s;background:%1$s" title="%1$s"></button>', $chip);
    }
    $styleOptions = '';
    foreach (BorderModel::STYLES as $value => $label) {
      $styleOptions .= sprintf(
        '<option value="%1$s"%2$s>%3$s</option>',
        $value,
        $value === $currentStyle ? ' selected="selected"' : '',
        escape($label)
      );
    }
    $frameRow = $this->_buildFrameRow($border);
    $box = sprintf(
      '<div id="ibBox" class="ib_box is-collapsed" hidden'
      . ' data-msg-nofile="' . escape(lang('err_img_boarder_nofile')) . '"'
      . ' data-msg-confirm="' . escape(lang('msg_img_boarder_confirm_delete')) . '"'
      . ' data-msg-fail="' . escape(lang('err_img_boarder_save')) . '"'
      . ' data-max-size="' . FrameModel::MAX_FILE_SIZE . '"'
      . ' data-msg-size="' . escape(lang('err_img_boarder_size')) . '">'
      . '<button type="button" class="ib_toggle" id="ibToggle" aria-expanded="false">'
      . '<span class="ib_toggle_label">%20$s</span><span class="ib_toggle_state">Open</span>'
      . '</button>'
      . '<div class="ib_body">'
      . '<input type="hidden" name="ib_use" value="Y" />'
      . '<div class="ib_row"><label for="ibWidth">%1$s</label>'
      . '<input type="number" id="ibWidth" name="ib_width" min="0" max="%2$d" step="1" value="%3$d" /><span class="ib_unit">px</span>'
      . '<small>%4$s</small></div>'
      . '%25$s'
      . '<div class="ib_row ib_line_only"><label for="ibStyle">%5$s</label>'
      . '<select id="ibStyle" name="ib_style">%6$s</select>'
      . '<small>%7$s</small></div>'
      . '<div class="ib_row ib_line_only"><label for="ibRadius">%21$s</label>'
      . '<input type="number" id="ibRadius" name="ib_radius" min="0" max="%22$d" step="1" value="%23$d" /><span class="ib_unit">px</span>'
      . '<small>%24$s</small></div>'
      . '<div class="ib_row ib_line_only"><label for="ibColor">%8$s</label>'
      . '<div class="ib_color"><input type="text" id="ibColor" name="ib_color" maxlength="9" placeholder="%9$s" value="%10$s" />'
      . '<input type="color" id="ibPicker" value="%10$s" title="%8$s" /><span class="ib_chips">%11$s</span></div>'
      . '<small>%12$s</small></div>'
      . '<div class="ib_row"><label for="ibMinWidth">%13$s</label>'
      . '<input type="number" id="ibMinWidth" name="ib_min_width" min="0" step="1" value="%14$d" /><span class="ib_unit">px</span>'
      . '<small>%15$s</small></div>'
      . '<div class="ib_row"><label>%16$s</label>'
      . '<label class="ib_chk"><input type="checkbox" id="ibShadow" name="ib_shadow" value="Y"%17$s /> %18$s</label>'
      . '<small>%19$s</small></div>'
      . '%26$s'
      . '</div></div>',
      lang('cmd_img_boarder_width'),
      BorderModel::MAX_WIDTH,
      $width,
      sprintf(lang('about_img_boarder_width'), BorderModel::MAX_WIDTH),
      lang('cmd_img_boarder_style'),
      $styleOptions,
      lang('about_img_boarder_style'),
      lang('cmd_img_boarder_color'),
      BorderModel::DEFAULT_COLOR,
      $color,
      $chips,
      sprintf(lang('about_img_boarder_color'), BorderModel::DEFAULT_COLOR),
      lang('cmd_img_boarder_min_width'),
      $minWidth,
      lang('about_img_boarder_min_width'),
      lang('cmd_img_boarder_shadow'),
      ($border && $border->shadow === 'Y') ? ' checked="checked"' : '',
      lang('cmd_img_boarder_shadow_on'),
      lang('about_img_boarder_shadow'),
      lang('cmd_img_boarder'),
      lang('cmd_img_boarder_radius'),
      BorderModel::MAX_RADIUS,
      $radius,
      sprintf(lang('about_img_boarder_radius'), BorderModel::MAX_RADIUS),
      $frameRow,
      $this->_buildItemsRow($documentSrl ? ItemModel::getList($documentSrl) : [], $styleOptions, $chips)
    );
    \Context::loadFile(['./modules/img_boarder/tpl/css/write.css', 'all']);
    \Context::loadFile(['./modules/img_boarder/tpl/js/write.js', 'body']);
    \Context::addHtmlFooter($box);
  }

  /**
   * CSS declarations (without selector) for one border setting row; '' when the row applies nothing.
   * Used for the document default and for every per-image override (same column names).
   *
   * @param object $row border_width, border_color, border_style, border_radius, frame_srl, frame_slice, frame_repeat, shadow
   * @return string
   */
  protected function _buildCss(object $row): string {
    $width = intval($row->border_width);
    $style = isset(BorderModel::STYLES[$row->border_style ?? ''])
      ? $row->border_style
      : BorderModel::DEFAULT_STYLE;
    $radius = max(0, min(BorderModel::MAX_RADIUS, intval($row->border_radius ?? 0)));
    $shadow = ($row->shadow ?? 'N') === 'Y';
    // Border image mode: the uploaded image replaces the line border (radius does not apply to border-image).
    $frame = intval($row->frame_srl ?? 0) > 0 ? FrameModel::get(intval($row->frame_srl)) : null;
    $frame = ($frame && is_file(FrameModel::getDir() . $frame->file_name)) ? $frame : null;
    if (!$frame && $width <= 0 && $radius <= 0 && !$shadow) {
      return '';
    }
    // Layout-safe: border must not expand past the content column (esp. mobile).
    $cssParts = ['box-sizing:border-box', 'max-width:100%', 'height:auto'];
    if ($frame) {
      $frameSlice = intval($row->frame_slice ?? 0) > 0 ? intval($row->frame_slice) : intval($frame->slice);
      $frameRepeat = in_array($row->frame_repeat ?? '', FrameModel::REPEATS, true) ? $row->frame_repeat : $frame->repeat_mode;
      $cssParts[] = sprintf('border:%dpx solid transparent', $width > 0 ? $width : $frameSlice);
      $cssParts[] = sprintf('border-image:url(%s) %d %s', FrameModel::getUrl($frame), $frameSlice, $frameRepeat);
    } else {
      if ($width > 0) {
        $cssParts[] = sprintf('border:%dpx %s %s', $width, $style, escape($row->border_color));
      }
      if ($radius > 0) {
        $cssParts[] = sprintf('border-radius:%dpx', $radius);
      }
    }
    if ($shadow) {
      // Keep shadow inside-ish; still paints outside but does not affect layout width.
      $cssParts[] = 'box-shadow:0 2px 8px rgba(0,0,0,.35)';
    }
    return implode(';', $cssParts) . ';';
  }

  /**
   * "Per-image settings" block of the option box: thumbnails of the body images (filled by write.js),
   * the settings panel of the selected image, and the hidden ib_items field that carries the result.
   * Panel inputs use form="ibNoForm" so they are never submitted themselves.
   *
   * @param \stdClass[] $items Saved overrides of the document being edited
   * @param string $styleOptions <option> list of line styles
   * @param string $chips Color chip buttons of the main color field
   * @return string
   */
  protected function _buildItemsRow(array $items, string $styleOptions, string $chips): string {
    $saved = [];
    foreach ($items as $item) {
      $saved[] = [
        'key' => $item->img_key,
        'width' => intval($item->border_width),
        'color' => $item->border_color,
        'style' => $item->border_style,
        'radius' => intval($item->border_radius),
        'shadow' => $item->shadow,
        'frame' => intval($item->frame_srl),
        'slice' => intval($item->frame_slice),
        'repeat' => $item->frame_repeat,
      ];
    }
    $repeatOptions = '';
    foreach (FrameModel::REPEATS as $repeat) {
      $repeatOptions .= sprintf('<option value="%1$s">%2$s</option>', $repeat, escape(lang('cmd_img_boarder_repeat_' . $repeat)));
    }
    return sprintf(
      '<div class="ib_row ib_items_row">'
      . '<label>%1$s</label>'
      . '<div class="ib_imgs_bar"><button type="button" class="ib_btn" id="ibImgsRefresh">%2$s</button></div>'
      . '<div class="ib_imgs" id="ibImgs" data-items="%3$s" data-badge="%4$s" data-empty="%5$s"></div>'
      . '<div class="ib_item_panel is-off" id="ibItemPanel" hidden>'
      . '<div class="ib_row"><label class="ib_chk"><input type="checkbox" id="ibiOn" form="ibNoForm" /> %6$s</label><small>%7$s</small></div>'
      . '<div class="ib_item_fields">'
      . '<div class="ib_row"><label for="ibiWidth">%8$s</label>'
      . '<input type="number" id="ibiWidth" form="ibNoForm" min="0" max="%9$d" step="1" value="0" /><span class="ib_unit">px</span></div>'
      . '<div class="ib_row ib_i_line"><label for="ibiStyle">%10$s</label>'
      . '<select id="ibiStyle" form="ibNoForm">%11$s</select></div>'
      . '<div class="ib_row ib_i_line"><label for="ibiRadius">%12$s</label>'
      . '<input type="number" id="ibiRadius" form="ibNoForm" min="0" max="%13$d" step="1" value="0" /><span class="ib_unit">px</span></div>'
      . '<div class="ib_row ib_i_line"><label for="ibiColor">%14$s</label>'
      . '<div class="ib_color"><input type="text" id="ibiColor" form="ibNoForm" maxlength="9" placeholder="%15$s" value="%15$s" />'
      . '<input type="color" id="ibiPicker" form="ibNoForm" value="%15$s" title="%14$s" /><span class="ib_chips">%16$s</span></div></div>'
      . '<div class="ib_row"><label>%17$s</label>'
      . '<label class="ib_chk"><input type="checkbox" id="ibiShadow" form="ibNoForm" value="Y" /> %18$s</label></div>'
      . '<div class="ib_row"><label>%19$s</label>'
      . '<div class="ib_frames" id="ibiFrames" data-none="%20$s"></div>'
      . '<div class="ib_item_opts">'
      . '<span class="ib_upload_opt"><label for="ibiSlice">%21$s</label><input type="number" id="ibiSlice" form="ibNoForm" min="1" step="1" value="%22$d" /><span class="ib_unit">px</span></span>'
      . '<span class="ib_upload_opt"><label for="ibiRepeat">%23$s</label><select id="ibiRepeat" form="ibNoForm">%24$s</select></span>'
      . '</div></div>'
      . '</div></div>'
      . '<input type="hidden" name="ib_items" id="ibItems" value="" />'
      . '<small>%25$s</small></div>',
      lang('cmd_img_boarder_items'),
      lang('cmd_img_boarder_items_refresh'),
      escape(json_encode($saved, JSON_UNESCAPED_UNICODE)),
      escape(lang('cmd_img_boarder_item_badge')),
      escape(lang('msg_img_boarder_items_empty')),
      lang('cmd_img_boarder_item_on'),
      lang('about_img_boarder_item_on'),
      lang('cmd_img_boarder_width'),
      BorderModel::MAX_WIDTH,
      lang('cmd_img_boarder_style'),
      $styleOptions,
      lang('cmd_img_boarder_radius'),
      BorderModel::MAX_RADIUS,
      lang('cmd_img_boarder_color'),
      BorderModel::DEFAULT_COLOR,
      str_replace('ib_chip', 'ib_ichip', $chips),
      lang('cmd_img_boarder_shadow'),
      lang('cmd_img_boarder_shadow_on'),
      lang('cmd_img_boarder_frame'),
      escape(lang('cmd_img_boarder_frame_none')),
      lang('cmd_img_boarder_frame_slice'),
      FrameModel::DEFAULT_SLICE,
      lang('cmd_img_boarder_frame_repeat'),
      $repeatOptions,
      lang('about_img_boarder_items')
    );
  }

  /**
   * Border image picker + uploader row (logged-in members only).
   * Inputs without a name are never submitted with the document form; only the ib_frame radio is.
   *
   * @param \stdClass|null $border Saved border row of the document being edited
   * @return string
   */
  protected function _buildFrameRow(?\stdClass $border): string {
    $currentFrameSrl = $border ? intval($border->frame_srl ?? 0) : 0;
    $canUpload = FrameModel::canUpload();
    // Built-in frames first, then the members' uploads (newest first).
    $frames = array_merge(FrameModel::getSystem(), array_filter(FrameModel::getAll(), fn($f) => intval($f->member_srl) > 0));
    $items = '';
    // The frame this document already uses stays selectable even if it is older than the listed ones.
    if ($currentFrameSrl > 0 && !array_filter($frames, fn($f) => intval($f->frame_srl) === $currentFrameSrl)) {
      $current = FrameModel::get($currentFrameSrl);
      if ($current) {
        array_unshift($frames, $current);
      }
    }
    foreach ($frames as $frame) {
      $items .= $this->_buildFrameItem($frame, $currentFrameSrl === intval($frame->frame_srl), FrameModel::canDelete($frame));
    }
    // Saved per-document values win; otherwise the form starts with the defaults.
    $slice = ($currentFrameSrl > 0 && intval($border->frame_slice ?? 0) > 0) ? intval($border->frame_slice) : FrameModel::DEFAULT_SLICE;
    $currentRepeat = ($currentFrameSrl > 0 && in_array($border->frame_repeat ?? '', FrameModel::REPEATS, true)) ? $border->frame_repeat : FrameModel::DEFAULT_REPEAT;
    $repeatOptions = '';
    foreach (FrameModel::REPEATS as $repeat) {
      $repeatOptions .= sprintf(
        '<option value="%1$s"%3$s>%2$s</option>',
        $repeat,
        escape(lang('cmd_img_boarder_repeat_' . $repeat)),
        $repeat === $currentRepeat ? ' selected="selected"' : ''
      );
    }
    return sprintf(
      '<div class="ib_row ib_frame_row"><label>%1$s</label>'
      . '<div class="ib_frames" id="ibFrames">'
      . '<span class="ib_frame ib_frame_none"><label><input type="radio" name="ib_frame" value="0"%2$s /> <span class="ib_frame_txt">%3$s</span></label></span>%4$s'
      . '</div>'
      . '<div class="ib_upload">'
      . '%11$s'
      . '<span class="ib_upload_opt"><label for="ibFrameSlice">%5$s</label><input type="number" id="ibFrameSlice" name="ib_frame_slice" min="1" step="1" value="%6$d" /><span class="ib_unit">px</span></span>'
      . '<span class="ib_upload_opt"><label for="ibFrameRepeat">%7$s</label><select id="ibFrameRepeat" name="ib_frame_repeat">%8$s</select></span>'
      . '%12$s'
      . '</div>'
      . '<small>%10$s</small></div>',
      lang('cmd_img_boarder_frame'),
      array_filter($frames, fn($f) => intval($f->frame_srl) === $currentFrameSrl) ? '' : ' checked="checked"',
      lang('cmd_img_boarder_frame_none'),
      $items,
      lang('cmd_img_boarder_frame_slice'),
      $slice,
      lang('cmd_img_boarder_frame_repeat'),
      $repeatOptions,
      '',
      $canUpload
        ? sprintf(lang('about_img_boarder_frame'), FrameModel::MAX_FILE_SIZE / 1048576, FrameModel::MAX_DIMENSION, FrameModel::MAX_PER_MEMBER)
        : lang('about_img_boarder_frame_pick'),
      $canUpload ? '<input type="file" id="ibFrameFile" accept="image/png,image/gif,image/webp,image/jpeg" />' : '',
      $canUpload ? sprintf('<button type="button" class="ib_btn" id="ibFrameUpload">%s</button>', lang('cmd_img_boarder_frame_upload')) : ''
    );
  }

  /**
   * One selectable frame thumbnail.
   *
   * @param \stdClass $frame
   * @param bool $checked
   * @param bool $canDelete Uploader or admin
   * @return string
   */
  protected function _buildFrameItem(\stdClass $frame, bool $checked, bool $canDelete): string {
    return sprintf(
      '<span class="ib_frame" data-srl="%1$d" data-slice="%5$d" data-repeat="%6$s"><label><input type="radio" name="ib_frame" value="%1$d"%3$s /><img src="%2$s" alt="" loading="lazy" /><span class="ib_frame_name" title="%7$s">%7$s</span></label>'
      . '%8$s</span>',
      intval($frame->frame_srl),
      escape(FrameModel::getUrl($frame)),
      $checked ? ' checked="checked"' : '',
      escape(lang('cmd_img_boarder_frame_delete')),
      intval($frame->slice),
      escape($frame->repeat_mode),
      escape($frame->orig_name ?: $frame->file_name),
      $canDelete ? sprintf('<button type="button" class="ib_frame_del" data-srl="%d" title="%s">&times;</button>', intval($frame->frame_srl), escape(lang('cmd_img_boarder_frame_delete'))) : ''
    );
  }

  /**
   * After document insert.
   *
   * @param object $obj
   * @return void
   */
  public function afterInsertDocument(object $obj): void {
    $this->_saveFromRequest($obj);
  }

  /**
   * After document update.
   *
   * @param object $obj
   * @return void
   */
  public function afterUpdateDocument(object $obj): void {
    $this->_saveFromRequest($obj);
  }

  /**
   * After document delete.
   *
   * @param object $obj
   * @return void
   */
  public function afterDeleteDocument(object $obj): void {
    BorderModel::delete(intval($obj->document_srl));
    ItemModel::deleteAll(intval($obj->document_srl));
  }

  /**
   * Save option values sent from the write form (only when the option box was in the form).
   *
   * @param object $obj
   * @return void
   */
  protected function _saveFromRequest(object $obj): void {
    $vars = \Context::getRequestVars();
    if (($vars->ib_use ?? '') !== 'Y') {
      return;
    }
    $targetSrls = ConfigModel::getConfig()->target_srls;
    if ($targetSrls && !in_array(intval($obj->module_srl), $targetSrls, true)) {
      return;
    }
    BorderModel::save(intval($obj->document_srl), intval($obj->module_srl), $vars);
    // ib_items stays empty when write.js did not run: keep the saved overrides in that case.
    if (strval($vars->ib_items ?? '') !== '') {
      ItemModel::saveAll(intval($obj->document_srl), strval($vars->ib_items), strval($obj->content ?? ''));
    }
  }
}
