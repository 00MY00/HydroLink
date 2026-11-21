{{-- Status pill: color dot + word (the color never carries the information alone). --}}
<span class="hl-pill hl-pill-{{ $s['level'] }}" @isset($id) data-pill="{{ $id }}" @endisset><span class="hl-dot" aria-hidden="true"></span><span class="hl-pill-label">{{ $s['label'] }}</span></span>
