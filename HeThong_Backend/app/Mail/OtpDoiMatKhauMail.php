<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpDoiMatKhauMail extends Mailable
{
    // thuộc tính public tự có mặt trong file giao diện thư
    public function __construct(public string $hoTen, public string $otp, public int $phutHieuLuc)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã xác minh đổi mật khẩu');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp_doi_mat_khau');
    }
}