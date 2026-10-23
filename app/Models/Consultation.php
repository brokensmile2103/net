<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** 1 cuộc hẹn gọi tư vấn khách đặt trên trang đặt đơn. */
class Consultation extends Model
{
    /** studio_id, client_id, status, token cố tình KHÔNG fillable. */
    protected $fillable = ['name', 'phone', 'email', 'facebook', 'channel', 'note', 'starts_at', 'duration'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'duration' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Consultation $c) {
            $c->token ??= Str::random(40);
            $c->status ??= 'booked';
        });
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration);
    }

    public function channelLabel(): string
    {
        return config('net.consult.channels.'.$this->channel.'.label', $this->channel);
    }

    public function statusLabel(): string
    {
        return ['booked' => 'Đã hẹn', 'done' => 'Đã gọi', 'cancelled' => 'Đã huỷ'][$this->status] ?? $this->status;
    }

    public function isUpcoming(): bool
    {
        return $this->status === 'booked' && $this->endsAt()->isFuture();
    }

    /** "Thứ Ba, 28/10 · 09:30–10:00" */
    public function whenLabel(): string
    {
        $days = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

        return $days[$this->starts_at->isoWeekday()].', '.$this->starts_at->format('d/m').' · '.$this->starts_at->format('H:i').'–'.$this->endsAt()->format('H:i');
    }

    public function publicUrl(): string
    {
        return route('public.consult.show', $this->token);
    }

    /** Link "Thêm vào Google Calendar". */
    public function googleCalendarUrl(): string
    {
        $fmt = fn (Carbon $t) => $t->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => 'Gọi tư vấn với '.$this->studio->name,
            'dates' => $fmt($this->starts_at).'/'.$fmt($this->endsAt()),
            'details' => $this->channelLabel().' · '.$this->publicUrl(),
        ]);
    }

    /** Nội dung file .ics (thêm vào lịch điện thoại / Outlook / Apple Calendar). */
    public function ics(string $summary, string $description): string
    {
        $esc = fn ($t) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $t);
        $fmt = fn (Carbon $t) => $t->copy()->utc()->format('Ymd\THis\Z');

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Net//Consultation//VI', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:consult-'.$this->id.'-'.substr(sha1($this->token), 0, 12).'@'.(parse_url(url('/'), PHP_URL_HOST) ?: 'net'),
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($this->starts_at),
            'DTEND:'.$fmt($this->endsAt()),
            'SUMMARY:'.$esc($summary),
            'DESCRIPTION:'.$esc($description),
            'URL:'.$this->publicUrl(),
            'STATUS:'.($this->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
            'BEGIN:VALARM', 'TRIGGER:-PT15M', 'ACTION:DISPLAY', 'DESCRIPTION:'.$esc($summary), 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);
    }
}
