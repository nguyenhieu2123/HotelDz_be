<?php

namespace App\Http\Controllers;

use App\Mail\ThanhToanHoaDonMail;
use App\Models\ChiTietThuePhong;
use App\Models\GiaoDich;
use App\Models\HoaDon;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery\Expectation;

class GiaoDichController extends Controller
{
    public function index()
    {
        $client = new Client();
        $payload = [
            "USERNAME"      => "THANHTRUONG2311",
            "PASSWORD"      => "Lethanhtruong2311@@",
            "DAY_BEGIN"     => Carbon::today()->format('d/m/Y'),
            "DAY_END"       => Carbon::today()->format('d/m/Y'),
            "NUMBER_MB"     => "1910061030119"
        ];

        try {
            $response = $client->post('http://103.137.185.71:2603/mb', [
                'json' => $payload
            ]);

            $data   = json_decode($response->getBody(), true);
            $duLieu = $data['data'];
            foreach ($duLieu as $key => $value) {
                // Chúng ta chỉ tạo mới khi $value['refNo'] chưa có ở table giao_dichs
                $check = GiaoDich::where('refNo', $value['refNo'])->first();
                if (!$check) {
                    GiaoDich::create([
                        'creditAmount'  => $value['creditAmount'],
                        'description'   => $value['description'],
                        'refNo'         => $value['refNo'],
                    ]);
                    $string = $value['description'];
                    $pattern = '/TTDP2/';
                    if (preg_match($pattern, $string, $matches)) {
                        $string = $matches[0];
                        $pattern = '/\d/';
                        if (preg_match($pattern, $string, $matches)) {
                            $id_hoa_don = $matches[0];
                            $hoaDon = HoaDon::join('khach_hangs', 'hoa_dons.id_khach_hang', '=', 'khach_hangs.id')
                                            ->where('hoa_dons.id', $id_hoa_don)
                                            ->select('khach_hangs.ho_lot', 'khach_hangs.ten', 'khach_hangs.email', 'hoa_dons.*')
                                            ->first();
                            if ($value['creditAmount'] >= $hoaDon->tong_tien) {
                                HoaDon::where('id', $id_hoa_don)->update([
                                    'is_thanh_toan' => 1
                                ]);
                                ChiTietThuePhong::where('id_hoa_don', $id_hoa_don)->update([
                                    'tinh_trang'    =>  3
                                ]);

                                $data = HoaDon::join('chi_tiet_thue_phongs', 'hoa_dons.id', '=', 'chi_tiet_thue_phongs.id_hoa_don')
                                    ->join('khach_hangs', 'hoa_dons.id_khach_hang', '=', 'khach_hangs.id')
                                    ->join('phongs', 'chi_tiet_thue_phongs.id_phong', '=', 'phongs.id')
                                    ->join('loai_phongs', 'phongs.id_loai_phong', '=', 'loai_phongs.id')
                                    ->where('chi_tiet_thue_phongs.id_hoa_don', $hoaDon->id)
                                    ->select(
                                        'loai_phongs.hinh_anh',
                                        'loai_phongs.ten_loai_phong',
                                        'phongs.ten_phong',
                                        'hoa_dons.ngay_den',
                                        'hoa_dons.ngay_di',
                                        DB::raw('SUM(chi_tiet_thue_phongs.gia_thue) as tong_gia_thue')
                                    )
                                    ->groupBy(
                                        'loai_phongs.hinh_anh',
                                        'loai_phongs.ten_loai_phong',
                                        'phongs.ten_phong',
                                        'hoa_dons.ngay_den',
                                        'hoa_dons.ngay_di',
                                    )
                                    ->get();

                                $bien_1['ma_hoa_don']      =   $hoaDon->ma_hoa_don;
                                $bien_1['ten_nguoi_nhan']  =   $hoaDon->ho_lot . " " . $hoaDon->ten ;
                                $bien_1['tong_tien']       =   $value['creditAmount'];
                                $bien_1['email']           =   $hoaDon->email;

                                // dd($data->toArray());
                                Mail::to($bien_1['email'])->send(new ThanhToanHoaDonMail($bien_1, $data));
                            }
                        }
                    }
                }
                // echo $value['description'] . " --- " . $value['creditAmount'];
            }
        } catch (Exception $e) {
            echo $e;
        }
    }
}
