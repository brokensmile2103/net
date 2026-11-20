@php use App\Services\Mail\EmailContent as EC; @endphp
@if($sandboxFor)[EMAIL THỬ — gửi thật thì tới {{ $sandboxFor }}]

@endif
{!! $studio->name !!}
@if($c->eyebrow){!! $c->eyebrow !!}
@endif

{!! $c->heading !!}

@if($c->greeting){!! $c->greeting !!}

@endif
@foreach($c->paragraphs as $para){!! EC::plain($para) !!}

@endforeach
@if($c->note)Lời nhắn từ {!! $studio->name !!}: {!! EC::plain($c->note) !!}

@endif
@foreach($c->facts as [$label, $value]){!! $label !!}: {!! $value !!}
@endforeach
@if($c->button)

{!! $c->button[0] !!}: {!! $c->button[1] !!}
@endif

@foreach($c->after as $para){!! EC::plain($para) !!}

@endforeach
{!! $c->signoff ?? ('Cảm ơn bạn,'."\n".$studio->name) !!}
@if($signature){!! EC::plain($signature) !!}
@endif

—
@if($c->footer){!! EC::plain($c->footer) !!}
@endif
Gửi qua Nét
