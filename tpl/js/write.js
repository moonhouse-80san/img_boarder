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

  function hidePreview() {
    $preview.hide();
  }

  $frames.on('mouseenter', '.ib_frame img', function () {
    var rect = this.getBoundingClientRect();
    var gap = 10;
    // Above the thumbnail, centered on it; below only when there is no room above.
    var top = rect.top - gap - PREVIEW_SIZE;
    if (top < 4) {
      top = Math.min(rect.bottom + gap, window.innerHeight - PREVIEW_SIZE - 4);
    }
    var left = rect.left + rect.width / 2 - PREVIEW_SIZE / 2;
    left = Math.max(4, Math.min(left, window.innerWidth - PREVIEW_SIZE - 4));
    $preview.attr('src', this.src).css({ left: left, top: top }).show();
  }).on('mouseleave', '.ib_frame img', hidePreview);
  $(window).on('scroll blur', hidePreview);

  syncFrameMode();

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
