<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Modules\Img_boarder\Models\Border as BorderModel;
use Rhymix\Modules\Img_boarder\Models\Config as ConfigModel;

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
      if (!$border) {
        return;
      }
      $width = intval($border->border_width);
      $color = escape($border->border_color);
      $style = isset(BorderModel::STYLES[$border->border_style ?? ''])
        ? $border->border_style
        : BorderModel::DEFAULT_STYLE;
      // Layout-safe: border must not expand past the content column (esp. mobile).
      $cssParts = [
        'box-sizing:border-box',
        'max-width:100%',
        'height:auto',
      ];
      if ($width > 0) {
        $cssParts[] = sprintf('border:%dpx %s %s', $width, $style, $color);
      }
      if (($border->shadow ?? 'N') === 'Y') {
        // Keep shadow inside-ish; still paints outside but does not affect layout width.
        $cssParts[] = 'box-shadow:0 2px 8px rgba(0,0,0,.35)';
      }
      if ($width <= 0 && ($border->shadow ?? 'N') !== 'Y') {
        return;
      }
      $cssValue = implode(';', $cssParts) . ';';
      $minWidth = isset($border->min_image_width)
        ? max(0, intval($border->min_image_width))
        : BorderModel::DEFAULT_MIN_WIDTH;
      $css = sprintf(
        '<style>.ib_applied_img_%1$d{%2$s}</style>',
        $documentSrl,
        $cssValue
      );
      $js = sprintf(
        '<script>(function(){var m=%1$d,c="ib_applied_img_%2$d",s="[class*=\\"document_%2$d_\\"] img";'
        . 'function apply(){document.querySelectorAll(s).forEach(function(img){'
        . 'var w=img.naturalWidth||img.width||img.offsetWidth||0;'
        . 'if(w>=m){img.classList.add(c);}'
        . '});}'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",apply);}'
        . 'else{apply();}'
        . 'window.addEventListener("load",apply);'
        . '})();</script>',
        $minWidth,
        $documentSrl
      );
      \Context::addHtmlHeader($css);
      \Context::addHtmlFooter($js);
      return;
    }

    // Write form option box
    $width = $border ? intval($border->border_width) : 0;
    $color = $border ? escape($border->border_color) : BorderModel::DEFAULT_COLOR;
    $currentStyle = ($border && isset(BorderModel::STYLES[$border->border_style ?? '']))
      ? $border->border_style
      : BorderModel::DEFAULT_STYLE;
    $minWidth = $border && isset($border->min_image_width)
      ? max(0, intval($border->min_image_width))
      : BorderModel::DEFAULT_MIN_WIDTH;
    $chips = '';
    foreach (['#ffb3b3', '#ffd3a8', '#fff3a8', '#c4e8c8', '#bbdcff', '#a8b4e8', '#dbbcff', '#222222', '#ffffff'] as $chip) {
      $chips .= sprintf('<button type="button" class="ib_chip" data-color="%1$s" style="background:%1$s" title="%1$s"></button>', $chip);
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
    $box = sprintf(
      '<div id="ibBox" class="ib_box is-collapsed" hidden>'
      . '<button type="button" class="ib_toggle" id="ibToggle" aria-expanded="false">'
      . '<span class="ib_toggle_label">%20$s</span><span class="ib_toggle_state">Open</span>'
      . '</button>'
      . '<div class="ib_body">'
      . '<input type="hidden" name="ib_use" value="Y" />'
      . '<div class="ib_row"><label for="ibWidth">%1$s</label>'
      . '<input type="number" id="ibWidth" name="ib_width" min="0" max="%2$d" step="1" value="%3$d" /> px'
      . '<small>%4$s</small></div>'
      . '<div class="ib_row"><label for="ibStyle">%5$s</label>'
      . '<select id="ibStyle" name="ib_style">%6$s</select>'
      . '<small>%7$s</small></div>'
      . '<div class="ib_row"><label for="ibColor">%8$s</label>'
      . '<div class="ib_color"><input type="text" id="ibColor" name="ib_color" maxlength="9" placeholder="%9$s" value="%10$s" />'
      . '<input type="color" id="ibPicker" value="%10$s" title="%8$s" /><span class="ib_chips">%11$s</span></div>'
      . '<small>%12$s</small></div>'
      . '<div class="ib_row"><label for="ibMinWidth">%13$s</label>'
      . '<input type="number" id="ibMinWidth" name="ib_min_width" min="0" step="1" value="%14$d" /> px'
      . '<small>%15$s</small></div>'
      . '<div class="ib_row"><label>%16$s</label>'
      . '<label class="ib_chk"><input type="checkbox" id="ibShadow" name="ib_shadow" value="Y"%17$s /> %18$s</label>'
      . '<small>%19$s</small></div>'
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
      lang('cmd_img_boarder')
    );
    \Context::loadFile(['./modules/img_boarder/tpl/css/write.css', 'all']);
    \Context::loadFile(['./modules/img_boarder/tpl/js/write.js', 'body']);
    \Context::addHtmlFooter($box);
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
  }
}
