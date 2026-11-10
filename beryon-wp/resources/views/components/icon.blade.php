{{-- آیکون SVG درون‌خطی؛ دقیقاً همان مارک‌آپ قالب HTML نهایی --}}
@php(beryon_svg($name ?? '', array_filter(['width' => $width ?? '', 'class' => $class ?? '', 'style' => $style ?? '', 'title' => $title ?? '', 'attr' => $attr ?? []], static fn ($v) => $v !== '')))
