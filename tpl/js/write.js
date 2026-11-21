/**
 * Img_boarder - places the option box into the write form and handles fold + color sync.
 * Default: collapsed (is-collapsed). Toggle label: Open / Close.
 */
jQuery(function ($) {
  var $box = $('#ibBox');
  var $width = $('#ibWidth');
  var $color = $('#ibColor');
  var $picker = $('#ibPicker');
  var $chips = $box.find('.ib_chip');
  var $toggle = $('#ibToggle');
  var $state = $toggle.find('.ib_toggle_state');

  function isHex(v) {
    return /^#[0-9a-f]+$/i.test(v) && (v.length === 4 || v.length === 7 || v.length === 9);
  }

  function setColor(v) {
    $color.val(v);
    if (/^#[0-9a-f]{6}$/i.test(v)) {
      $picker.val(v.toLowerCase());
    }
    $chips.removeClass('on').filter(function () {
      return String($(this).data('color')).toLowerCase() === v.toLowerCase();
    }).addClass('on');
  }

  function syncToggleLabel() {
    var open = !$box.hasClass('is-collapsed');
    $state.text(open ? 'Close' : 'Open');
    $toggle.attr('aria-expanded', open ? 'true' : 'false');
  }

  function place() {
    var $form = $('form').filter(function () {
      return $(this).find('[name="content"]').length > 0 && $(this).find('[name="title"]').length > 0;
    }).first();
    if (!$form.length) {
      return false;
    }
    // Preferred spot: right above the tag field (highest wrapper that holds neither the content nor the submit button).
    var $tag = $form.find('[name="tags"]').first();
    if ($tag.length) {
      var $anchor = $tag;
      $tag.parentsUntil($form).each(function () {
        var $p = $(this);
        if ($p.find('[name="content"]').length || $p.find('input[type="submit"], button[type="submit"]').length) {
          return false;
        }
        $anchor = $p;
      });
      $anchor.before($box);
      $box.prop('hidden', false);
      syncToggleLabel();
      return true;
    }
    var $submit = $form.find('input[type="submit"], button[type="submit"]').last();
    var $area = $submit.parentsUntil($form).last();
    if (!$area.length) {
      $area = $submit;
    }
    if ($area.length) {
      $area.before($box);
    } else {
      $area = $form.find('[name="content"]').first().parentsUntil($form).last();
      $area.after($box);
    }
    $box.prop('hidden', false);
    syncToggleLabel();
    return true;
  }

  if (!$box.length) {
    return;
  }

  $toggle.on('click', function (e) {
    e.preventDefault();
    $box.toggleClass('is-collapsed');
    syncToggleLabel();
    if (!$box.hasClass('is-collapsed')) {
      refreshImages();
    }
  });

  $width.on('change blur', function () {
    var n = parseInt($width.val(), 10);
    if (isNaN(n) || n < 0) {
      n = 0;
    }
    $width.val(Math.min(n, parseInt($width.attr('max'), 10) || 20));
  });

  $('#ibRadius').on('change blur', function () {
    var $r = $(this);
    var n = parseInt($r.val(), 10);
    if (isNaN(n) || n < 0) {
      n = 0;
    }
    $r.val(Math.min(n, parseInt($r.attr('max'), 10) || 100));
  });

  // ---- Border image (frame) picker / uploader
  var $frames = $('#ibFrames');

  function syncFrameMode() {
    $box.toggleClass('is-frame', $box.find('input[name="ib_frame"]:checked').val() > 0);
  }

  function frameItem(srl, url, name, slice, repeat, title) {
    var $item = $('<span class="ib_frame"></span>').attr({ 'data-srl': srl, 'data-slice': slice, 'data-repeat': repeat });
    var $label = $('<label></label>');
    $label.append($('<input type="radio" name="ib_frame" />').val(srl));
    $label.append($('<img alt="" />').attr('src', url));
    $label.append($('<span class="ib_frame_name"></span>').attr('title', name).text(name));
    $item.append($label);
    $item.append($('<button type="button" class="ib_frame_del">&times;</button>').attr({ 'data-srl': srl, title: title }));
    return $item;
  }

  function ajaxError(data) {
    window.alert((data && data.message) || $box.data('msgFail'));
  }

  // Picking a frame loads that image's own corner size / fill mode into the fields (editable per document).
  $box.on('change', 'input[name="ib_frame"]', function () {
    var $item = $(this).closest('.ib_frame');
    if ($item.data('slice')) {
      $('#ibFrameSlice').val($item.data('slice'));
      $('#ibFrameRepeat').val($item.data('repeat'));
    }
    syncFrameMode();
  });

  $('#ibFrameUpload').on('click', function () {
    var file = $('#ibFrameFile')[0].files[0];
    if (!file) {
      window.alert($box.data('msgNofile'));
      return;
    }
    if (file.size > parseInt($box.data('maxSize'), 10)) {
      window.alert($box.data('msgSize'));
      return;
    }
    var fd = new FormData();
    fd.append('module', 'img_boarder');
    fd.append('act', 'procImg_boarderUploadFrame');
    fd.append('ib_frame_file', file);
    fd.append('slice', $('#ibFrameSlice').val());
    fd.append('repeat_mode', $('#ibFrameRepeat').val());
    var $btn = $(this).prop('disabled', true);
    Rhymix.ajax(null, fd, function (data) {
      var $item = frameItem(data.frame_srl, data.frame_url, data.frame_name, data.frame_slice, data.frame_repeat, $box.find('.ib_frame_del').first().attr('title') || '');
      $frames.find('.ib_frame_none').after($item);
      $item.find('input').prop('checked', true);
      $('#ibFrameSlice').val(data.frame_slice);
      $('#ibFrameRepeat').val(data.frame_repeat);
      $('#ibFrameFile').val('');
      syncFrameMode();
      $btn.prop('disabled', false);
    }, function (data) {
      $btn.prop('disabled', false);
      ajaxError(data);
    });
  });

  $frames.on('click', '.ib_frame_del', function (e) {
    e.preventDefault();
    var $btn = $(this);
    if (!window.confirm($box.data('msgConfirm'))) {
      return;
    }
    Rhymix.ajax('img_boarder.procImg_boarderDeleteFrame', { frame_srl: $btn.data('srl') }, function () {
      var $item = $btn.closest('.ib_frame');
      if ($item.find('input').prop('checked')) {
        $frames.find('.ib_frame_none input').prop('checked', true);
      }
      $item.remove();
      hidePreview();
      syncFrameMode();
    }, ajaxError);
  });

  // Enter inside the upload fields must not submit the write form.
  $box.find('.ib_upload').on('keydown', 'input', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      $('#ibFrameUpload').trigger('click');
    }
  });

  // Hover preview: enlarged image next to the thumbnail (appended to body so the box's overflow can not clip it).
  var $preview = $('<img id="ibPreview" alt="" />').appendTo(document.body);
  var PREVIEW_SIZE = 240;
  var BODY_PREVIEW_SIZE = 240;

  function hidePreview() {
    $preview.hide();
  }

  function showPreview(img, size) {
    var rect = img.getBoundingClientRect();
    var gap = 10;
    size = Math.min(size, window.innerWidth - 8, window.innerHeight - 8);
    // Above the thumbnail, centered on it; below only when there is no room above.
    var top = rect.top - gap - size;
    if (top < 4) {
      top = Math.min(rect.bottom + gap, window.innerHeight - size - 4);
    }
    var left = rect.left + rect.width / 2 - size / 2;
    left = Math.max(4, Math.min(left, window.innerWidth - size - 4));
    $preview[0].style.setProperty('--ib-pv', size + 'px');
    $preview.attr('src', img.src).css({ left: left, top: top }).show();
  }

  $box.on('mouseenter', '.ib_frames .ib_frame img', function () {
    showPreview(this, PREVIEW_SIZE);
  }).on('mouseleave', '.ib_frames .ib_frame img', hidePreview);
  // Body images get a bigger preview.
  $box.on('mouseenter', '.ib_imgs .ib_img img', function () {
    showPreview(this, BODY_PREVIEW_SIZE);
  }).on('mouseleave', '.ib_imgs .ib_img img', hidePreview);
  $(window).on('scroll blur', hidePreview);

  syncFrameMode();

  // ---- Per-image settings: pick a body image, override the document default for that image only.
  var $imgs = $('#ibImgs');
  var $panel = $('#ibItemPanel');
  var $itemsInput = $('#ibItems');
  var $itemFrames = $('#ibiFrames');
  var $itemColor = $('#ibiColor');
  var $itemPicker = $('#ibiPicker');
  var $itemChips = $panel.find('.ib_ichip');
  var items = {};      // img key -> {width, color, style, radius, shadow, frame, slice, repeat}
  var imgList = [];    // [{key, url}] body images found in the editor
  var sel = [];        // keys of the selected images (the panel edits all of them at once)
  var anchor = '';     // last clicked key, start of a shift-click range
  var multiMode = false; // 'select multiple' button: every click toggles
  var MAX_WIDTH = parseInt($width.attr('max'), 10) || 20;
  var MAX_RADIUS = parseInt($('#ibRadius').attr('max'), 10) || 100;

  try {
    $.each(JSON.parse($imgs.attr('data-items') || '[]'), function (i, it) {
      items[it.key] = it;
    });
  } catch (e) {}

  function clamp(v, max) {
    var n = parseInt(v, 10);
    return isNaN(n) || n < 0 ? 0 : Math.min(n, max);
  }

  // Same key rule as the server: file name of the src, without query/hash, URL-decoded.
  function keyOf(src) {
    var u = String(src || '').split('#')[0].split('?')[0];
    u = u.substring(u.lastIndexOf('/') + 1);
    try {
      u = decodeURIComponent(u);
    } catch (e) {}
    return u;
  }

  function getContent() {
    var $form = $box.closest('form');
    var seq = $form.attr('editor_sequence') || $('[data-editor-sequence]').first().data('editorSequence');
    try {
      if (seq && window.editorGetContent) {
        return String(window.editorGetContent(seq) || '');
      }
    } catch (e) {}
    return String($form.find('[name="content"]').val() || '');
  }

  function syncItemsInput() {
    var list = [];
    $.each(items, function (key, it) {
      list.push($.extend({ key: key }, it));
    });
    $itemsInput.val(JSON.stringify(list));
  }

  function mainValues() {
    var color = $.trim($color.val());
    return {
      width: clamp($width.val(), MAX_WIDTH),
      color: isHex(color) ? color : $color.attr('placeholder'),
      style: $('#ibStyle').val(),
      radius: clamp($('#ibRadius').val(), MAX_RADIUS),
      shadow: $('#ibShadow').prop('checked') ? 'Y' : 'N',
      frame: parseInt($box.find('input[name="ib_frame"]:checked').val(), 10) || 0,
      slice: parseInt($('#ibFrameSlice').val(), 10) || 0,
      repeat: $('#ibFrameRepeat').val()
    };
  }

  function setItemColor(v) {
    $itemColor.val(v);
    if (/^#[0-9a-f]{6}$/i.test(v)) {
      $itemPicker.val(v.toLowerCase());
    }
    $itemChips.removeClass('on').filter(function () {
      return String($(this).data('color')).toLowerCase() === v.toLowerCase();
    }).addClass('on');
  }

  // Frame strip of the panel = copy of the main frame list (without delete buttons), so uploads show up here too.
  function buildItemFrames(selected) {
    $itemFrames.empty();
    var $none = $('<span class="ib_frame ib_frame_none"></span>');
    $none.append($('<label></label>')
      .append($('<input type="radio" name="ibi_frame" form="ibNoForm" value="0" />'))
      .append(' ')
      .append($('<span class="ib_frame_txt"></span>').text($itemFrames.attr('data-none'))));
    $itemFrames.append($none);
    $frames.find('.ib_frame').not('.ib_frame_none').each(function () {
      var $src = $(this);
      var name = $src.find('.ib_frame_name').text();
      var $item = $('<span class="ib_frame"></span>').attr({
        'data-srl': $src.attr('data-srl'),
        'data-slice': $src.attr('data-slice'),
        'data-repeat': $src.attr('data-repeat')
      });
      $item.append($('<label></label>')
        .append($('<input type="radio" name="ibi_frame" form="ibNoForm" />').val($src.attr('data-srl')))
        .append($('<img alt="" />').attr('src', $src.find('img').attr('src')))
        .append($('<span class="ib_frame_name"></span>').attr('title', name).text(name)));
      $itemFrames.append($item);
    });
    var $radio = $itemFrames.find('input[name="ibi_frame"]').filter(function () {
      return parseInt(this.value, 10) === selected;
    }).first();
    ($radio.length ? $radio : $itemFrames.find('input[name="ibi_frame"]').first()).prop('checked', true);
    $panel.toggleClass('is-frame', parseInt($itemFrames.find('input:checked').val(), 10) > 0);
  }

  function writePanel(v) {
    $('#ibiWidth').val(v.width);
    $('#ibiStyle').val(v.style);
    $('#ibiRadius').val(v.radius);
    $('#ibiShadow').prop('checked', v.shadow === 'Y');
    $('#ibiSlice').val(v.slice || $('#ibFrameSlice').val());
    $('#ibiRepeat').val(v.repeat);
    setItemColor(v.color);
    buildItemFrames(v.frame);
  }

  function readPanel() {
    var color = $.trim($itemColor.val());
    return {
      width: clamp($('#ibiWidth').val(), MAX_WIDTH),
      color: isHex(color) ? color : $itemColor.attr('placeholder'),
      style: $('#ibiStyle').val(),
      radius: clamp($('#ibiRadius').val(), MAX_RADIUS),
      shadow: $('#ibiShadow').prop('checked') ? 'Y' : 'N',
      frame: parseInt($itemFrames.find('input:checked').val(), 10) || 0,
      slice: parseInt($('#ibiSlice').val(), 10) || 0,
      repeat: $('#ibiRepeat').val()
    };
  }

  function renderImages() {
    hidePreview();
    $imgs.empty();
    if (!imgList.length) {
      $imgs.append($('<span class="ib_imgs_empty"></span>').text($imgs.attr('data-empty')));
      return;
    }
    $.each(imgList, function (i, img) {
      var $thumb = $('<span class="ib_img" role="button" tabindex="0"></span>').attr('data-key', img.key).attr('title', img.key);
      $thumb.append($('<img alt="" />').attr('src', img.url));
      $thumb.append($('<span class="ib_img_badge"></span>').text($imgs.attr('data-badge')));
      $thumb.toggleClass('has', !!items[img.key]).toggleClass('on', $.inArray(img.key, sel) > -1);
      $imgs.append($thumb);
    });
  }

  // Panel for the selection: shows the first override found (else the document default);
  // the checkbox is on only when every selected image has its own settings.
  function openPanel() {
    if (!sel.length) {
      $panel.prop('hidden', true);
      renderImages();
      return;
    }
    var source = null;
    var all = true;
    $.each(sel, function (i, key) {
      if (!items[key]) {
        all = false;
      } else if (!source) {
        source = items[key];
      }
    });
    writePanel(source || mainValues());
    $('#ibiOn').prop('checked', all);
    $panel.toggleClass('is-off', !all).prop('hidden', false);
    $('#ibSelInfo').text($panel.attr('data-selected').replace('%d', sel.length)).prop('hidden', sel.length < 2);
    renderImages();
  }

  // Click = only this image, Ctrl/Cmd (or the multi button) = add/remove, Shift = range from the last click.
  function selectImage(key, e) {
    var index = $.inArray(key, sel);
    if (e.shiftKey && anchor) {
      var from = -1;
      var to = -1;
      $.each(imgList, function (i, img) {
        from = img.key === anchor ? i : from;
        to = img.key === key ? i : to;
      });
      if (from > -1 && to > -1) {
        sel = $.map(imgList.slice(Math.min(from, to), Math.max(from, to) + 1), function (img) {
          return img.key;
        });
      }
    } else if (multiMode || e.ctrlKey || e.metaKey) {
      if (index > -1) {
        sel.splice(index, 1);
      } else {
        sel.push(key);
      }
      anchor = key;
    } else {
      sel = [key];
      anchor = key;
    }
    openPanel();
  }

  // on = store the panel values for every selected image, off = back to the document default.
  function applyPanel(on) {
    var values = readPanel();
    $.each(sel, function (i, key) {
      if (on) {
        items[key] = $.extend({}, values);
      } else {
        delete items[key];
      }
    });
    $panel.toggleClass('is-off', !on);
    $imgs.find('.ib_img').each(function () {
      $(this).toggleClass('has', !!items[$(this).attr('data-key')]);
    });
    syncItemsInput();
  }

  function refreshImages() {
    var seen = {};
    imgList = [];
    var doc = new DOMParser().parseFromString(getContent(), 'text/html');
    Array.prototype.forEach.call(doc.querySelectorAll('img'), function (img) {
      var src = img.getAttribute('src') || '';
      var key = keyOf(src);
      if (!key || /^data:/i.test(src) || seen[key]) {
        return;
      }
      seen[key] = true;
      var url = /^(?:[a-z]+:)?\/\/|^\//i.test(src) ? src : (window.request_uri || '/') + src;
      imgList.push({ key: key, url: url });
    });
    var before = sel.length;
    sel = $.grep(sel, function (key) {
      return !!seen[key];
    });
    if (sel.length && sel.length !== before) {
      openPanel();
      return;
    }
    if (!sel.length) {
      $panel.prop('hidden', true);
    }
    renderImages();
  }

  $('#ibImgsRefresh').on('click', function (e) {
    e.preventDefault();
    refreshImages();
  });

  $('#ibImgsAll').on('click', function (e) {
    e.preventDefault();
    sel = $.map(imgList, function (img) {
      return img.key;
    });
    anchor = sel[0] || '';
    openPanel();
  });

  $('#ibImgsMulti').on('click', function (e) {
    e.preventDefault();
    multiMode = !multiMode;
    $(this).toggleClass('on', multiMode).attr('aria-pressed', multiMode ? 'true' : 'false');
  });

  $imgs.on('click', '.ib_img', function (e) {
    selectImage($(this).attr('data-key'), e);
  }).on('keydown', '.ib_img', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      selectImage($(this).attr('data-key'), e);
    }
  });

  $panel.on('input change', 'input, select', function () {
    if (!sel.length) {
      return;
    }
    if (this.name === 'ibi_frame') {
      var $item = $(this).closest('.ib_frame');
      if ($item.attr('data-slice')) {
        $('#ibiSlice').val($item.attr('data-slice'));
        $('#ibiRepeat').val($item.attr('data-repeat'));
      }
      $panel.toggleClass('is-frame', parseInt(this.value, 10) > 0);
    }
    if (this.id === 'ibiColor' && isHex($.trim(this.value))) {
      setItemColor($.trim(this.value));
    } else if (this.id === 'ibiPicker') {
      setItemColor(this.value);
    }
    applyPanel($('#ibiOn').prop('checked'));
  }).on('keydown', 'input', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
    }
  });

  $itemColor.on('blur', function () {
    var v = $.trim($itemColor.val());
    if (v && v.charAt(0) !== '#') {
      v = '#' + v;
    }
    setItemColor(isHex(v) ? v : $itemColor.attr('placeholder'));
    if ($('#ibiOn').prop('checked') && sel.length) {
      applyPanel(true);
    }
  });

  $itemChips.on('click', function () {
    setItemColor(String($(this).data('color')));
    $panel.find('#ibiColor').trigger('change');
  });

  syncItemsInput();

  $color.on('input', function () {
    var v = $.trim($color.val());
    if (isHex(v)) {
      setColor(v);
    }
  }).on('blur', function () {
    var v = $.trim($color.val());
    if (v && v.charAt(0) !== '#') {
      v = '#' + v;
    }
    setColor(isHex(v) ? v : $color.attr('placeholder'));
  });

  $picker.on('input change', function () {
    setColor($picker.val());
  });

  $chips.on('click', function () {
    setColor(String($(this).data('color')));
  });

  setColor($.trim($color.val()));
  if (!place()) {
    $(window).one('load', place);
  }
});
