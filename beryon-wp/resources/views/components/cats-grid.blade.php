{{-- گرید دسته‌ها — $cats = خروجی \App\Products::categories() --}}
@foreach ($cats as $category)
@include('components.cat-card', ['category' => $category])
@endforeach
