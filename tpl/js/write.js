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
