<!DOCTYPE html>
<html lang="vi">
<body style="font-family: Arial, sans-serif; color: #222;">
  <p>Xin chào {{ $hoTen }},</p>
  <p>Mã xác minh để đổi mật khẩu của bạn là:</p>
  <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $otp }}</p>
  <p>Mã có hiệu lực trong {{ $phutHieuLuc }} phút và chỉ dùng được một lần.
     Tuyệt đối không chia sẻ mã này cho bất kỳ ai, kể cả nhân viên hỗ trợ.</p>
  <p>Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua thư này và báo cho bộ phận nhân sự.</p>
</body>
</html>