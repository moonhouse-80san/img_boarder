<?php

namespace Rhymix\Modules\Img_boarder\Controllers;

use Rhymix\Framework\Exceptions\InvalidRequest;
use Rhymix\Framework\Exceptions\NotPermitted;
use Rhymix\Framework\Exceptions\TargetNotFound;
use Rhymix\Framework\Storage;
use Rhymix\Modules\Img_boarder\Models\Frame as FrameModel;

/**
 * Img_boarder frame (border image) upload / delete actions.
 */
class Frame extends Base {
  /**
   * Upload a border image into the logged-in member's library.
   */
  public function procImg_boarderUploadFrame() {
    $logged = \Context::get('logged_info');
    $memberSrl = intval($logged->member_srl ?? 0);
    if (!$memberSrl) {
      throw new NotPermitted(lang('err_img_boarder_login'));
    }
    if (!FrameModel::canUpload()) {
      throw new NotPermitted(lang('err_img_boarder_upload_denied'));
    }
    $file = $_FILES['ib_frame_file'] ?? null;
    if (!$file || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
      throw new InvalidRequest(lang('err_img_boarder_nofile'));
    }
    if (intval($file['size']) > FrameModel::MAX_FILE_SIZE) {
      throw new InvalidRequest(lang('err_img_boarder_size'));
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset(FrameModel::TYPES[$info[2]])) {
      throw new InvalidRequest(lang('err_img_boarder_type'));
    }
    [$imgWidth, $imgHeight] = $info;
    if ($imgWidth > FrameModel::MAX_DIMENSION || $imgHeight > FrameModel::MAX_DIMENSION) {
      throw new InvalidRequest(lang('err_img_boarder_dim'));
    }
    if (FrameModel::countByMember($memberSrl) >= FrameModel::MAX_PER_MEMBER) {
      throw new InvalidRequest(sprintf(lang('err_img_boarder_quota'), FrameModel::MAX_PER_MEMBER));
    }
    $slice = max(1, min(intval(floor(min($imgWidth, $imgHeight) / 2)), intval(\Context::get('slice') ?: FrameModel::DEFAULT_SLICE)));
    $repeat = strval(\Context::get('repeat_mode'));
    if (!in_array($repeat, FrameModel::REPEATS, true)) {
      $repeat = FrameModel::DEFAULT_REPEAT;
    }

    $fileName = bin2hex(random_bytes(8)) . '.' . FrameModel::TYPES[$info[2]];
    $path = FrameModel::getDir() . $fileName;
    if (!(Storage::isDirectory(FrameModel::getDir()) || Storage::createDirectory(FrameModel::getDir())) || !Storage::moveUploadedFile($file['tmp_name'], $path)) {
      throw new InvalidRequest(lang('err_img_boarder_save'));
    }
    @chmod($path, 0644);

    $args = new \stdClass;
    $args->frame_srl = getNextSequence();
    $args->member_srl = $memberSrl;
    $args->file_name = $fileName;
    $args->orig_name = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]/u', '', basename(strval($file['name']))), 0, 100);
    $args->img_width = $imgWidth;
    $args->img_height = $imgHeight;
    $args->slice = $slice;
    $args->repeat_mode = $repeat;
    FrameModel::insert($args);

    $this->add('frame_srl', $args->frame_srl);
    $this->add('frame_url', FrameModel::getUrl($args));
    $this->add('frame_name', $args->orig_name ?: $fileName);
    $this->add('frame_slice', $slice);
    $this->add('frame_repeat', $repeat);
  }

  /**
   * Delete a frame (owner or admin). Frames still used by a document cannot be deleted.
   */
  public function procImg_boarderDeleteFrame() {
    $logged = \Context::get('logged_info');
    $memberSrl = intval($logged->member_srl ?? 0);
    if (!$memberSrl) {
      throw new NotPermitted(lang('err_img_boarder_login'));
    }
    $frame = FrameModel::get(intval(\Context::get('frame_srl')));
    if (!$frame) {
      throw new TargetNotFound(lang('err_img_boarder_not_found'));
    }
    if (!FrameModel::canDelete($frame)) {
      throw new NotPermitted(lang('msg_not_permitted'));
    }
    if (FrameModel::countUse(intval($frame->frame_srl)) > 0) {
      throw new InvalidRequest(lang('err_img_boarder_in_use'));
    }
    FrameModel::delete($frame);
  }
}
