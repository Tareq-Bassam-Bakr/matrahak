{{-- ترويسة حكومية فوق نموذج الدخول --}}
<div style="text-align:center; margin-bottom:.75rem;">
    @if (config('brand.state_emblem'))
        <img src="{{ asset(config('brand.emblem')) }}" alt="{{ config('brand.owner') }}" style="height:5.5rem; margin:0 auto .5rem;">
    @else
        <div style="font-weight:700; color:{{ config('brand.colors.gold') }};">{{ config('brand.owner') }}</div>
    @endif
    <div style="font-size:.8rem; opacity:.75;">لوحة إدارة منصة {{ config('brand.name') }} — خاصة بالجهات الحكومية المختصة</div>
</div>
