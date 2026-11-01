<!DOCTYPE html>
<html lang="vi">
<body style="font-family: Arial, sans-serif; color: #222;">
  <p>Xin chào <?php echo e($hoTen); ?>,</p>
  <p>Mã xác minh để đổi mật khẩu của bạn là:</p>
  <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;"><?php echo e($otp); ?></p>
  <p>Mã có hiệu lực trong <?php echo e($phutHieuLuc); ?> phút và chỉ dùng được một lần.
     Tuyệt đối không chia sẻ mã này cho bất kỳ ai, kể cả nhân viên hỗ trợ.</p>
  <p>Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua thư này và báo cho bộ phận nhân sự.</p>
</body>
</html><?php /**PATH /Applications/MAMP/htdocs/HeThongQuanLyNhanSu/HeThong_Backend/resources/views/emails/otp_doi_mat_khau.blade.php ENDPATH**/ ?>