{{-- شعار «مطرحك» في الشريط العلوي وصفحة الدخول --}}
<div style="display:flex; align-items:center; gap:10px; direction:rtl;">
    <img src="{{ asset(config('brand.icon')) }}" alt="{{ config('brand.name') }}"
         style="height:2.6rem; width:2.6rem; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.15);">
    <div style="line-height:1.15; text-align:right;">
        <div style="font-weight:800; font-size:1.2rem; color:{{ config('brand.colors.green') }};" class="sc-brand-name">{{ config('brand.name') }}</div>
        <div style="font-size:.7rem; color:{{ config('brand.colors.gold') }}; font-weight:600;">{{ config('brand.tagline') }}</div>
    </div>
</div>
