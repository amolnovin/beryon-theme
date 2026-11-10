{{-- گرید محصولات — $products = آرایه‌ای از خروجی \App\Products::normalize() --}}
@foreach ($products as $product)
@include('components.product-card', ['product' => $product])
@endforeach
