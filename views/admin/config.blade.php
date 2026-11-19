<style>
  .ib-wrap { max-width: 1100px; margin: 0 auto; }
  .ib-panel { border: 1px solid #dcdfe4; border-radius: 16px; background: #fff; overflow: hidden; margin-bottom: 20px; }
  .ib-head { padding: 18px 24px; border-bottom: 1px solid #e5e7eb; }
  .ib-head h2 { display: inline; margin: 0; font-size: 17px; font-weight: 700; }
  .ib-head span { margin-left: 8px; color: #8a8f98; font-size: 13px; }
  .ib-body { padding: 24px; }
  .ib-info { margin-bottom: 20px; padding: 14px 18px; border: 1px solid #e0e3e8; border-radius: 12px; background: #f6f7f9; color: #555; font-size: 13px; }
  .ib-bar { display: flex; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid #e5e7eb; }
  .ib-bar button, .ib-bar select { height: 38px; padding: 0 16px; border: 1px solid #d5d8de; border-radius: 19px; background: #f4f4f5; font-size: 13px; font-weight: 700; cursor: pointer; }
  .ib-bar select { background: #fff; font-weight: 400; }
  .ib-count { margin-left: auto; color: #8a8f98; font-size: 13px; }
  .ib-count b { color: #2f6bd8; }
  .ib-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-top: 16px; }
  .ib-card { position: relative; display: block; padding: 14px 16px; border: 1px solid #d9dce2; border-radius: 12px; background: #fff; cursor: pointer; transition: border-color .15s, background .15s, box-shadow .15s; }
  .ib-card:hover { border-color: #b7bcc6; }
  .ib-card input { position: absolute; opacity: 0; pointer-events: none; }
  .ib-card strong { display: block; font-size: 14px; line-height: 1.4; word-break: break-word; }
  .ib-card small { display: block; margin-top: 6px; color: #9aa0a9; font-size: 12px; }
  .ib-card small em { font-style: normal; color: #b08a5a; }
  .ib-card.is-selected { border-color: #2f6bd8; background: #f2f6fe; box-shadow: 0 0 0 1px #2f6bd8 inset; }
  .ib-foot { margin-top: 20px; text-align: right; }
  .ib-empty { padding: 30px 0; color: #8a8f98; text-align: center; }
</style>

<div class="ib-wrap">
  <form action="./" method="post">
    <input type="hidden" name="module" value="img_boarder" />
    <input type="hidden" name="act" value="procImg_boarderAdminInsertConfig" />
    @csrf

    <div class="ib-panel">
      <div class="ib-head">
        <h2>@lang('cmd_img_boarder_target')</h2><span>@lang('cmd_img_boarder_target_sub')</span>
      </div>
      <div class="ib-body">
        <div class="ib-info">{!! lang('about_img_boarder_target') !!}</div>

        <div class="ib-bar">
          <button type="button" id="ibToggle" data-all="@lang('cmd_img_boarder_select_all')" data-none="@lang('cmd_img_boarder_deselect_all')">@lang('cmd_img_boarder_select_all')</button>
          <select id="ibSort">
            <option value="title">@lang('cmd_img_boarder_sort_title')</option>
            <option value="mid">@lang('cmd_img_boarder_sort_mid')</option>
          </select>
          <span class="ib-count"><b id="ibCount">0</b>@lang('cmd_img_boarder_selected')</span>
        </div>

        @if (count($boards))
          <div class="ib-grid" id="ibGrid">
            @foreach ($boards as $board)
              <label class="ib-card" data-title="{{ $board->title }}" data-mid="{{ $board->mid }}">
                <input type="checkbox" name="target_srls[]" value="{{ $board->module_srl }}" @checked(in_array($board->module_srl, $config->target_srls, true)) />
                <strong>{{ $board->title }}</strong>
                <small>{{ $board->mid }} · <em>BOARD</em></small>
              </label>
            @endforeach
          </div>
        @else
          <p class="ib-empty">@lang('msg_img_boarder_no_board')</p>
        @endif

      </div>
    </div>

    <div class="ib-panel">
      <div class="ib-head">
        <h2>@lang('cmd_img_boarder_upload_groups')</h2><span>@lang('cmd_img_boarder_upload_groups_sub')</span>
      </div>
      <div class="ib-body">
        <div class="ib-info">{!! lang('about_img_boarder_upload_groups') !!}</div>
        @if (count($groups))
          <div class="ib-grid" id="ibGroupGrid">
            @foreach ($groups as $group)
              <label class="ib-card">
                <input type="checkbox" name="upload_group_srls[]" value="{{ $group->group_srl }}" @checked(in_array($group->group_srl, $config->upload_group_srls, true)) />
                <strong>{{ $group->title }}</strong>
                <small>group_srl {{ $group->group_srl }}</small>
              </label>
            @endforeach
          </div>
        @else
          <p class="ib-empty">@lang('msg_img_boarder_no_group')</p>
        @endif
      </div>
    </div>

    <div class="ib-foot">
      <button type="submit" class="x_btn x_btn-primary">@lang('cmd_save')</button>
    </div>
  </form>
</div>

<script>
  (function () {
    var groupGrid = document.getElementById('ibGroupGrid');
    if (groupGrid) {
      var syncGroups = function () {
        Array.prototype.forEach.call(groupGrid.querySelectorAll('.ib-card'), function (card) {
          card.classList.toggle('is-selected', card.querySelector('input').checked);
        });
      };
      groupGrid.addEventListener('change', syncGroups);
      syncGroups();
    }
  })();

  (function () {
    var grid = document.getElementById('ibGrid');
    if (!grid) {
      return;
    }
    var toggle = document.getElementById('ibToggle');
    var sort = document.getElementById('ibSort');
    var count = document.getElementById('ibCount');

    function cards() {
      return Array.prototype.slice.call(grid.querySelectorAll('.ib-card'));
    }

    function refresh() {
      var selected = 0;
      cards().forEach(function (card) {
        var checked = card.querySelector('input').checked;
        card.classList.toggle('is-selected', checked);
        selected += checked ? 1 : 0;
      });
      count.textContent = selected;
      toggle.textContent = selected === cards().length ? toggle.getAttribute('data-none') : toggle.getAttribute('data-all');
    }

    grid.addEventListener('change', refresh);

    toggle.addEventListener('click', function () {
      var all = cards().every(function (card) { return card.querySelector('input').checked; });
      cards().forEach(function (card) { card.querySelector('input').checked = !all; });
      refresh();
    });

    sort.addEventListener('change', function () {
      var key = sort.value;
      cards().sort(function (a, b) {
        return a.getAttribute('data-' + key).localeCompare(b.getAttribute('data-' + key), 'ko', { numeric: true, sensitivity: 'base' });
      }).forEach(function (card) { grid.appendChild(card); });
    });

    refresh();
  })();
</script>
