        <?php

        // Phạm vi, từ rộng đến hẹp:
        //   tat_ca   : toàn tập đoàn
        //   cong_ty  : công ty của mình (công ty mẹ thì gồm cả công ty con)
        //   cap_duoi : chính mình và mọi cấp dưới (theo bảng BanQuanLy)
        //   cua_minh : chỉ của chính mình
        // Tài khoản nhiều vai trò thì lấy phạm vi RỘNG NHẤT.

        return [
            // ----- Danh mục -----
            'chucvu.xem'   => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'tat_ca', 'HR' => 'tat_ca', 'MANAGER' => 'tat_ca', 'ACCOUNTANT' => 'tat_ca', 'EMPLOYEE' => 'tat_ca'],
            'chucvu.them'  => ['SUPER_ADMIN' => 'tat_ca'],
            'chucvu.sua'   => ['SUPER_ADMIN' => 'tat_ca'],
            'chucvu.ngung' => ['SUPER_ADMIN' => 'tat_ca'],

            'congty.xem'   => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'MANAGER' => 'cong_ty', 'ACCOUNTANT' => 'cong_ty', 'EMPLOYEE' => 'cong_ty'],
            'congty.them'  => ['SUPER_ADMIN' => 'tat_ca'],
            'congty.sua'   => ['SUPER_ADMIN' => 'tat_ca'],
            'congty.doi_trang_thai' => ['SUPER_ADMIN' => 'tat_ca'],

            'phongban.xem'   => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'MANAGER' => 'cong_ty', 'ACCOUNTANT' => 'cong_ty', 'EMPLOYEE' => 'cong_ty'],
            'phongban.them'  => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty'],
            'phongban.sua'   => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty'],
            'phongban.doi_trang_thai' => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty'],

                // ----- Nhân viên: ba bậc xem -----
            // Bậc 1 - Danh bạ: ai cũng thấy đồng nghiệp trong công ty
            'nhanvien.xem'         => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'MANAGER' => 'cong_ty', 'ACCOUNTANT' => 'cong_ty', 'EMPLOYEE' => 'cong_ty'],
            // Bậc 2 - Liên hệ: email, số điện thoại
            'nhanvien.xem_lien_he' => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'MANAGER' => 'cap_duoi', 'EMPLOYEE' => 'cua_minh'],
            // Bậc 3 - Hồ sơ đầy đủ
            'nhanvien.xem_day_du'  => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'EMPLOYEE' => 'cua_minh'],
            'nhanvien.doi_trang_thai' => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty'],
            'nhanvien.them'        => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty'],
            'nhanvien.sua'         => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty'],
            'nhanvien.ngung'       => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty'],
            
            // ----- Các nhóm dữ liệu khác (chỉ quyền xem, phần thêm sửa làm khi vào từng tính năng) -----
            'hopdong.xem'  => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'ACCOUNTANT' => 'cong_ty', 'EMPLOYEE' => 'cua_minh'],
            'luong.xem'    => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'ACCOUNTANT' => 'cong_ty', 'EMPLOYEE' => 'cua_minh'],
            'cong.xem'     => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'MANAGER' => 'cap_duoi', 'ACCOUNTANT' => 'cong_ty', 'EMPLOYEE' => 'cua_minh'],
            'nghiphep.xem' => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'HR' => 'cong_ty', 'MANAGER' => 'cap_duoi', 'EMPLOYEE' => 'cua_minh'],
            'congviec.xem' => ['SUPER_ADMIN' => 'tat_ca', 'COMPANY_ADMIN' => 'cong_ty', 'MANAGER' => 'cap_duoi', 'EMPLOYEE' => 'cua_minh'],
        ];
