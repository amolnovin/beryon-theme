{{--
  کارت دسته‌بندی — مارک‌آپ دقیقاً مطابق قالب HTML نهایی
  $category = یکی از خروجی‌های \App\Products::categories()
--}}<a class="cat-card reveal" href="{{ esc_url($category['link']) }}"><span class="thumb"><img src="{{ esc_url($category['image']) }}" alt="{{ $category['name'] }} بریون" loading="lazy"></span><span class="cat-overlay"><h3>{{ $category['name'] }}</h3><small>{{ beryon_fa($category['count']) }} محصول</small></span><span class="go">@php(beryon_svg('arrow'))</span></a>
