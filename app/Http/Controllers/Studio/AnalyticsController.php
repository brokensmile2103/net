<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thống kê hiệu quả trang đặt đơn: người xem, nguồn, thiết bị, đơn & tỉ lệ chuyển đổi. */
class AnalyticsController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request, AnalyticsReport $report): View
    {
        $studio = $this->studio($request);

        return view('analytics.index', [
            'studio' => $studio,
            'r' => $report->build($studio, (int) $request->query('ngay', 30)),
        ]);
    }
}
