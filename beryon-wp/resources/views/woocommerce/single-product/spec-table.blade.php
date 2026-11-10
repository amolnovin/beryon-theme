{{--
  جدول مشخصات محصول — قالب بریون
  ------------------------------------------------------------------
  ردیف‌ها از تنظیمات «products.default_spec_rows» می‌آیند تا مدیر سایت بتواند
  عنوان/کلید هر ردیف را تغییر دهد. مارک‌آپ دقیقاً مطابق product-single.html است.
  $product = آرایه نرمال‌شده \App\Products::normalize()
--}}
@php
  $rows = beryon_rows('products.default_spec_rows', [
      ['label' => 'گروه محصول', 'meta' => 'category'],
      ['label' => 'نوع گوشت', 'meta' => 'meat_type'],
      ['label' => 'درصد گوشت', 'meta' => 'meat'],
      ['label' => 'نوع محصول', 'meta' => 'type'],
      ['label' => 'وزن هر قطعه', 'meta' => 'unit_weight'],
      ['label' => 'بسته‌بندی', 'meta' => 'packaging'],
      ['label' => 'ماندگاری', 'meta' => 'shelf_life'],
  ]);

  $values = [
      'category'    => (string) ($product['cat_name'] ?? ''),
      'meat_type'   => (string) ($product['meat_type'] ?? ''),
      'meat'        => empty($product['meat']) ? '' : beryon_fa($product['meat']) . '٪',
      'type'        => (string) ($product['type_label'] ?? ''),
      'unit_weight' => (string) ($product['unit_weight'] ?? ''),
      'packaging'   => (string) ($product['packaging'] ?? ''),
      'shelf_life'  => (string) ($product['shelf_life'] ?? ''),
      'storage'     => (string) ($product['storage'] ?? ''),
      'title'       => (string) ($product['title'] ?? ''),
  ];
@endphp
<table class="spec-table">
              <tbody>
@foreach ($rows as $row)
@php
  $value = $values[(string) ($row['meta'] ?? '')] ?? '';
@endphp
@if ((string) $value !== '')
              <tr><th>{{ $row['label'] ?? '' }}</th><td>{{ $value }}</td></tr>
@endif
@endforeach
              </tbody>
              </table>
