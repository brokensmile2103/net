<?php

namespace App\Services;

use App\Models\Studio;
use Illuminate\Support\Carbon;

/**
 * Giờ trống để khách đặt lịch gọi tư vấn: theo ngày trong tuần + khung giờ studio đặt, chia theo thời lượng mỗi cuộc,
 * bỏ giờ quá sát (ít hơn notice_hours), bỏ giờ trùng cuộc hẹn đã có.
 */
class ConsultSlots
{
    /** @return list<array{date: string, label: string, weekday: string, times: list<string>}> */
    public function forStudio(Studio $studio): array
    {
        $p = $studio->consultPrefs();
        if (! $p['enabled']) {
            return [];
        }
        $earliest = now()->addHours($p['notice_hours']);
        $from = today();
        $to = today()->addDays($p['ahead_days']);
        $booked = $studio->consultations()->where('status', 'booked')
            ->whereBetween('starts_at', [$from->copy()->subDay(), $to->copy()->addDay()])
            ->get(['starts_at', 'duration'])
            ->map(fn ($c) => [$c->starts_at->copy(), $c->starts_at->copy()->addMinutes($c->duration)]);
        $weekdays = config('net.consult.weekdays');

        $out = [];
        for ($day = $from->copy(); $day->lt($to); $day->addDay()) {
            if (! in_array($day->isoWeekday(), $p['days'], true)) {
                continue;
            }
            $times = [];
            foreach ($p['ranges'] as [$a, $b]) {
                $t = Carbon::parse($day->toDateString().' '.$a);
                $end = Carbon::parse($day->toDateString().' '.$b);
                while ($t->copy()->addMinutes($p['duration'])->lte($end)) {
                    $slotEnd = $t->copy()->addMinutes($p['duration']);
                    $clash = $booked->contains(fn ($r) => $r[0]->lt($slotEnd) && $r[1]->gt($t));
                    if ($t->gte($earliest) && ! $clash) {
                        $times[] = $t->format('H:i');
                    }
                    $t->addMinutes($p['duration']);
                }
            }
            if ($times) {
                $out[] = [
                    'date' => $day->toDateString(),
                    'label' => $day->isToday() ? 'Hôm nay' : ($day->isTomorrow() ? 'Ngày mai' : $day->format('d/m')),
                    'weekday' => $weekdays[$day->isoWeekday()],
                    'times' => array_values(array_unique($times)),
                ];
            }
        }

        return $out;
    }

    public function isAvailable(Studio $studio, string $date, string $time): bool
    {
        foreach ($this->forStudio($studio) as $d) {
            if ($d['date'] === $date) {
                return in_array($time, $d['times'], true);
            }
        }

        return false;
    }
}
