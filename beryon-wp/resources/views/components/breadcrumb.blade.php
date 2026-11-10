{{--
  مسیر صفحه — مارک‌آپ دقیقاً مطابق قالب HTML
  $items = [['label' => 'خانه', 'url' => '…'], ['label' => 'محصولات', 'url' => '…'], ['label' => 'ژامبون']]
--}}<nav class="breadcrumb" aria-label="مسیر صفحه">
@foreach ($items as $item)
@if (! empty($item['url']))        <a href="{{ esc_url($item['url']) }}">{{ $item['label'] }}</a>
        @php(beryon_svg('chevron-left'))
@else        <span>{{ $item['label'] }}</span>
@endif
@endforeach      </nav>
