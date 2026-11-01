#!/bin/bash
API=http://localhost:8000
O=(-H "Origin: http://localhost:5500" -H "Referer: http://localhost:5500/" -H "Accept: application/json")

thu() {
  local user=$1; shift
  local jar; jar=$(mktemp)

  curl -s -c "$jar" "${O[@]}" "$API/sanctum/csrf-cookie" > /dev/null
  local token
  token=$(grep XSRF-TOKEN "$jar" | awk '{print $7}' | python3 -c "import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))")

  local dn
  dn=$(curl -s -o /dev/null -w "%{http_code}" -b "$jar" -c "$jar" "${O[@]}" \
        -H "X-XSRF-TOKEN: $token" -H "Content-Type: application/json" \
        -d "{\"Ten_DangNhap\":\"$user\",\"MatKhau\":\"Test@12345\"}" "$API/api/dang-nhap")

  printf "%-20s dang-nhap=%s" "$user" "$dn"
  for duong in "$@"; do
    local code
    code=$(curl -s -o /dev/null -w "%{http_code}" -b "$jar" -c "$jar" "${O[@]}" "$API$duong")
    printf "  %s=%s" "${duong##*/}" "$code"
  done
  echo
  rm -f "$jar"
}

R="/api/thu/quyen-chuc-vu-sua /api/thu/quyen-nhan-vien-them /api/thu/quyen-nhan-vien-xem"
thu minh.ld001          $R
thu mai.nv-vc-bgd-1     $R
thu trang.nv-vc-ns-1    $R
thu an.nv-vc-kt-2       $R
thu huong.nv-vg-tt-1    $R
thu dung.nv-vc-vh-1     $R