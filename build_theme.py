# -*- coding: utf-8 -*-
"""اسکریپت ساخت قالب استاتیک بریون — _src/pages + partials → beryon/*.html"""
import io, os, re, json

ROOT = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(ROOT, '_src')
OUT = os.path.join(ROOT, 'beryon')
F = ''.join(chr(0x6F0 + i) for i in range(10))
def fa(s): return ''.join(F[int(c)] if c.isdigit() else c for c in str(s))

# ---------------- داده محصولات ----------------
CATS = {
    'sausage': ('سوسیس، هات‌داگ و کوکتل', 'cat-sausage.webp', 'cat-sausage.html'),
    'kalbas':  ('کالباس', 'cat-kalbas.webp', 'cat-kalbas.html'),
    'jambon':  ('ژامبون', 'cat-jambon.webp', 'cat-jambon.html'),
    'frozen':  ('محصولات انجمادی', 'cat-frozen.webp', 'cat-frozen.html'),
    'plant':   ('محصولات گیاهی', 'cat-plant.webp', 'cat-plant.html'),
}
TAGS = {
    'cocktail': 'کوکتل', 'hotdog': 'هات‌داگ', 'paniri': 'پنیری', 'bulghari': 'بلغاری',
    'family': 'بسته خانواده', 'pepperoni': 'پپرونی', 'dry': 'خشک', 'jigo': 'ژیگو',
    'chicken': 'مرغ', 'burger': 'همبرگر', 'nugget': 'ناگت و شنیتسل', 'kebab': 'کباب و جوجه',
    'side': 'پیش‌غذا و مخلفات', 'sausage': 'سوسیس', 'kalbas': 'کالباس',
}
P = [  # (نام، دسته، درصد گوشت، تگ‌ها)
    ('سوسیس ۶۰% گوشت قرمز با پنیر (کوکتل پنیری) بریون', 'sausage', 60, ['cocktail', 'paniri']),
    ('سوسیس ۷۰% گوشت قرمز (هات‌داگ) بریون', 'sausage', 70, ['hotdog']),
    ('سوسیس ۵۵% گوشت قرمز (هات‌داگ) بریون', 'sausage', 55, ['hotdog']),
    ('سوسیس ۷۰% گوشت قرمز هات‌داگ (مجاری) بریون', 'sausage', 70, ['hotdog']),
    ('سوسیس ۵۵% گوشت مرغ (کوکتل بلغاری) بریون', 'sausage', 55, ['cocktail', 'bulghari']),
    ('سوسیس ۵۵% گوشت قرمز (کوکتل گوشت) بریون', 'sausage', 55, ['cocktail']),
    ('کوکتل چیکو ۶۰% گوشت مرغ با پنیر بریون', 'sausage', 60, ['cocktail', 'paniri']),
    ('سوسیس ۴۰% گوشت مرغ بریون', 'sausage', 40, []),
    ('سوسیس ۴۰% گوشت مرغ هلندی بریون', 'sausage', 40, []),
    ('سوسیس ۴۰% گوشت قرمز (سوسیس گوشت ویژه) بریون', 'sausage', 40, []),
    ('سوسیس ۵۵% گوشت قرمز و مرغ بریون', 'sausage', 55, []),
    ('سوسیس ۷۰% گوشت قرمز کوکتل بریون', 'sausage', 70, ['cocktail']),
    ('کالباس ۹۰% گوشت قرمز (فیله گوشت) بریون', 'kalbas', 90, []),
    ('کالباس ۷۰% گوشت قرمز (خشک ممتاز) بریون', 'kalbas', 70, ['dry']),
    ('کالباس ۷۰% گوشت قرمز پپرونی بریون', 'kalbas', 70, ['pepperoni']),
    ('کالباس ۶۰% گوشت قرمز (کالباس خشک) بریون', 'kalbas', 60, ['dry']),
    ('کالباس ۷۰% گوشت قرمز و مرغ با قارچ (کرات) بریون', 'kalbas', 70, []),
    ('کالباس ۶۰% گوشت مرغ با قارچ بریون', 'kalbas', 60, []),
    ('کالباس ۴۰% گوشت قرمز (مارتادلا) بریون', 'kalbas', 40, []),
    ('کالباس ۴۰% گوشت مرغ (پیکو) بریون', 'kalbas', 40, []),
    ('کالباس ۶۰% گوشت قرمز (خشک) خانواده بریون', 'kalbas', 60, ['dry', 'family']),
    ('کالباس ۴۰% گوشت مرغ خانواده بریون', 'kalbas', 40, ['family']),
    ('کالباس ۸۰% گوشت مرغ (ژامبون مرغ) بریون', 'jambon', 80, ['chicken']),
    ('کالباس ۹۰% گوشت قرمز ژیگو بریون', 'jambon', 90, ['jigo']),
    ('همبرگر ۹۰% ممتاز بریون (جعبه‌ای)', 'frozen', 90, ['burger']),
    ('همبرگر ۶۰% گوشت قرمز بریون', 'frozen', 60, ['burger']),
    ('همبرگر ۳۰% صدفی بریون (مخلوط گوشت و پروتئین گیاهی)', 'frozen', 30, ['burger']),
    ('ناگت مرغ ۷۰% (۹۰۰ گرمی) بریون', 'frozen', 70, ['nugget']),
    ('شنیتسل مرغ بریون', 'frozen', None, ['nugget']),
    ('کباب لقمه ۷۰% گوشت قرمز بریون', 'frozen', 70, ['kebab']),
    ('جوجه‌کباب زعفرانی بریون (ران و سینه مرغ)', 'frozen', None, ['kebab']),
    ('بال و ماهیچه مخلوط زعفرانی بریون', 'frozen', None, ['kebab']),
    ('فلافل ۳۵۰ گرمی نیمه‌آماده بریون', 'frozen', None, ['side']),
    ('فلافل پنیری ۳۵۰ گرمی نیمه‌آماده بریون', 'frozen', None, ['side']),
    ('کوکو سیب‌زمینی آماده مصرف بریون', 'frozen', None, ['side']),
    ('سیب‌زمینی خلالی منجمد بارمیز بریون (۲۵۰ گرمی)', 'frozen', None, ['side']),
    ('پنیر پیتزا موزارلا بریون', 'frozen', None, ['side']),
    ('سوسیس گیاهی بریون', 'plant', None, ['sausage']),
    ('سوسیس گیاهی (کوکتل) بریون', 'plant', None, ['sausage', 'cocktail']),
    ('هات‌داگ گیاهی بریون', 'plant', None, ['hotdog']),
    ('کالباس گیاهی بریون', 'plant', None, ['kalbas']),
    ('کالباس گیاهی خانواده بریون (قارچ، هویج، فلفل دلمه)', 'plant', None, ['kalbas', 'family']),
    ('کالباس گیاهی اسلایس‌شده بریون', 'plant', None, ['kalbas']),
]
FEATURED = [0, 1, 12, 13, 24, 27, 37, 14]
RELATED = [1, 4, 7, 6]

ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'

def card(i):
    name, cat, pct, tags = P[i]
    cname, img, _ = CATS[cat]
    pct_html = '<span class="pct">%s٪ گوشت</span>' % fa(pct) if pct else ''
    meta = ''.join('<span class="badge">%s</span>' % TAGS[t] for t in tags[:2])
    if not pct:
        meta = '<span class="badge blue">آماده طبخ</span>' + meta if cat == 'frozen' else '<span class="badge green">گیاهی</span>' + meta if cat == 'plant' else meta
    return ('<article class="product-card reveal" data-cat="%s" data-tags="%s" data-name="%s">'
            '<a class="thumb" href="product-single.html" aria-label="%s"><img src="assets/img/%s" alt="%s" loading="lazy">%s<span class="cat-tag">%s</span></a>'
            '<div class="body"><h3><a href="product-single.html">%s</a></h3><div class="meta">%s</div>'
            '<a class="more" href="product-single.html">مشاهده محصول %s</a></div></article>') % (
        cat, ' '.join(tags), name, name, img, name, pct_html, CATS[cat][0].split('،')[0], name, meta, ARROW)

def grid(kind):
    if kind == 'all':
        idx = range(len(P))
    elif kind == 'featured':
        idx = FEATURED
    elif kind == 'related':
        idx = RELATED
    else:
        idx = [i for i, p in enumerate(P) if p[1] == kind]
    return '\n'.join(card(i) for i in idx)

def cat_card(key):
    name, img, href = CATS[key]
    n = fa(sum(1 for p in P if p[1] == key))
    return ('<a class="cat-card reveal" href="%s"><span class="thumb"><img src="assets/img/%s" alt="%s بریون" loading="lazy"></span>'
            '<span class="cat-overlay"><h3>%s</h3><small>%s محصول</small></span>'
            '<span class="go">%s</span></a>') % (href, img, name, name, n, ARROW)

def cat_strip():
    return '\n'.join(cat_card(k) for k in CATS)

def other_cats(cur):
    return '\n'.join(cat_card(k) for k in CATS if k != cur)

# ---------------- متای صفحات ----------------
BASE = 'https://beryon.com/'
ORG = {"@context": "https://schema.org", "@type": "FoodEstablishment", "name": "بریون | شرکت بریان گوشت آمل",
       "url": BASE, "logo": BASE + "assets/img/logo.png", "telephone": "+981144448071",
       "email": "info@beryon.com",
       "address": {"@type": "PostalAddress", "addressLocality": "آمل", "addressRegion": "مازندران",
                   "streetAddress": "شهرک صنعتی امام‌زاده عبدالله، فاز ۲", "addressCountry": "IR"},
       "foundingDate": "2005", "brand": {"@type": "Brand", "name": "بریون"}}

def bc(items):
    return {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": i + 1, "name": n, "item": BASE + u} for i, (n, u) in enumerate(items)]}

def ld(obj):
    return '<script type="application/ld+json">' + json.dumps(obj, ensure_ascii=False) + '</script>'

PAGES = {
 'index': dict(file='index.html', title='بریون | فرآورده‌های گوشتی بریان گوشت آمل؛ سوسیس، کالباس، ژامبون با کیفیت صادراتی',
   desc='شرکت بریان گوشت آمل با برند بریون؛ تولیدکننده بیش از ۶۱ محصول سوسیس، کالباس، ژامبون، انجمادی و گیاهی با استانداردهای ISO 22000، HACCP و نشان ملی. استعلام قیمت عمده و نمایندگی.',
    canon=BASE, active='home', body_class=' class="home"', preload='<link rel="preload" as="image" href="assets/img/hero.jpg" fetchpriority="high">' + chr(10), jsonld=ld([ORG, {"@context":"https://schema.org","@type":"WebSite","name":"بریون","url":BASE}])),
 'products': dict(file='products.html', title='محصولات بریون | فهرست کامل ۶۱ فرآورده گوشتی و گیاهی + استعلام قیمت عمده',
   desc='همه محصولات بریون در یک صفحه: سوسیس و کوکتل، کالباس، ژامبون، محصولات انجمادی و گیاهی با درصد گوشت شفاف ۴۰ تا ۹۰. فیلتر، جست‌وجو و استعلام قیمت عمده.',
   canon=BASE+'products.html', active='products', jsonld=ld(bc([('خانه','index.html'),('محصولات','products.html')]))),
 'cat-sausage': dict(file='cat-sausage.html', title='سوسیس، هات‌داگ و کوکتل بریون | ۴۰ تا ۷۰٪ گوشت | استعلام قیمت عمده',
   desc='انواع سوسیس کوکتل، هات‌داگ، بلغاری و پنیری بریون با درصد گوشت ۴۰ تا ۷۰؛ بدون نگهدارنده مصنوعی، ویژه خانواده و فودسرویس. مشاهده فهرست کامل و قیمت عمده.',
   canon=BASE+'cat-sausage.html', active='products', jsonld=ld(bc([('خانه','index.html'),('محصولات','products.html'),('سوسیس، هات‌داگ و کوکتل','cat-sausage.html')]))),
 'cat-jambon': dict(file='cat-jambon.html', title='ژامبون بریون | ژامبون مرغ ۸۰٪ و ژیگو ۹۰٪ | پخش عمده و نمایندگی',
   desc='ژامبون مرغ ۸۰ درصد و ژامبون گوشت ژیگو ۹۰ درصد بریون؛ بافت منسجم و اسلایس عالی برای ساندویچ سرد و پذیرایی. استعلام قیمت عمده از بریان گوشت آمل.',
   canon=BASE+'cat-jambon.html', active='products', jsonld=ld(bc([('خانه','index.html'),('محصولات','products.html'),('ژامبون','cat-jambon.html')]))),
 'cat-kalbas': dict(file='cat-kalbas.html', title='کالباس بریون | گوشت قرمز و مرغ ۴۰ تا ۹۰ درصد | استعلام قیمت عمده',
   desc='انواع کالباس بریون: فیله گوشت ۹۰٪، خشک ممتاز، پپرونی، مارتادلا و کالباس مرغ در بسته‌بندی خانوادگی و فروشگاهی با درج شفاف درصد گوشت.',
   canon=BASE+'cat-kalbas.html', active='products', jsonld=ld(bc([('خانه','index.html'),('محصولات','products.html'),('کالباس','cat-kalbas.html')]))),
 'cat-frozen': dict(file='cat-frozen.html', title='محصولات انجمادی بریون | همبرگر، ناگت و شنیتسل | ویژه پخش عمده و فودسرویس',
   desc='محصولات انجمادی بریون: همبرگر ۳۰ تا ۹۰ درصد، ناگت مرغ ۷۰٪، شنیتسل، کباب لقمه، جوجه‌کباب زعفرانی، فلافل و سیب‌زمینی منجمد با زنجیره سرد یکپارچه.',
   canon=BASE+'cat-frozen.html', active='products', jsonld=ld(bc([('خانه','index.html'),('محصولات','products.html'),('محصولات انجمادی','cat-frozen.html')]))),
 'cat-plant': dict(file='cat-plant.html', title='محصولات گیاهی بریون | سوسیس و کالباس بدون گوشت | پخش عمده و نمایندگی',
   desc='خط محصولات گیاهی بریون: سوسیس، کالباس و هات‌داگ گیاهی بر پایه پروتئین سویا و سبزیجات؛ بدون کلسترول و مناسب گیاهخواران و تغذیه سالم.',
   canon=BASE+'cat-plant.html', active='products', jsonld=ld(bc([('خانه','index.html'),('محصولات','products.html'),('محصولات گیاهی','cat-plant.html')]))),
 'about': dict(file='about.html', title='درباره بریان گوشت آمل | برند بریون از سال ۱۳۸۴ | ISO ۲۲۰۰ و HACCP',
   desc='تاریخچه شرکت بریان گوشت آمل از تأسیس در سال ۱۳۸۴ تا امروز؛ ظرفیت ۵۰۰۰ تن در سال، گواهینامه‌های ISO 9001، ISO 22000، HACCP و GMP و گاه‌شمار افتخارات بریون.',
   canon=BASE+'about-us/', active='about', jsonld=ld(bc([('خانه','index.html'),('درباره ما','about.html')]))),
 'ceo': dict(file='ceo.html', title='مدیرعامل بریان گوشت آمل | دکتر بهرام شهره | بریون',
   desc='معرفی دکتر بهرام شهره، بنیان‌گذار و مدیرعامل بریان گوشت آمل؛ دامپزشک، فیزیولوژیست و استاد دانشگاه با سه دهه سابقه علمی و اجرایی در صنایع گوشتی.',
   canon=BASE+'about-us/introduction-of-ceo/', active='about', jsonld=ld(bc([('خانه','index.html'),('درباره ما','about.html'),('معرفی مدیرعامل','ceo.html')]))),
 'org-chart': dict(file='org-chart.html', title='چارت سازمانی بریان گوشت آمل | واحدهای تولید و کیفیت',
   desc='ساختار سازمانی شرکت بریان گوشت آمل: مدیریت تولید، QC، فنی بهداشت، R&D، فروش، بازرگانی و مالی؛ شرح وظایف هر واحد در یک نگاه.',
   canon=BASE+'about-us/organizational-chart/', active='about', jsonld=ld(bc([('خانه','index.html'),('درباره ما','about.html'),('چارت سازمانی','org-chart.html')]))),
 'certificates': dict(file='certificates.html', title='گواهینامه‌ها و افتخارات بریون | ISO ۲۲۰۰ و HACCP | استاندارد ایران',
   desc='فهرست گواهینامه‌های بریون: ISO 9001:2015، ISO 22000:2018، HACCP، GMP، نشان استاندارد ایران، برند اعتماد ملی و تندیس‌های ملی از جمله حقوق مصرف‌کننده ۱۴۰۳.',
   canon=BASE+'about-us/certificates/', active='about', jsonld=ld(bc([('خانه','index.html'),('درباره ما','about.html'),('گواهینامه‌ها','certificates.html')]))),
 'feedback': dict(file='feedback.html', title='نظرات و شکایات | ثبت آنلاین پیام با کد پیگیری | بریون',
   desc='فرم آنلاین ثبت نظر، پیشنهاد، انتقاد و شکایت مشتریان بریون با کد پیگیری یکتا؛ پاسخ‌گویی واحد رسیدگی به مشتریان در کمتر از ۷۲ ساعت کاری.',
   canon=BASE+'feedback/', active='feedback', jsonld=ld(bc([('خانه','index.html'),('نظرات و شکایات','feedback.html')]))),
 'blog': dict(file='blog.html', title='مجله بریان گوشت | وبلاگ آشپزی، تغذیه و دانستنی فرآورده‌های گوشتی',
   desc='مقالات کاربردی بریون: راهنمای خرید و پخت، دانستنی‌های تغذیه سالم، رسپی‌های ساندویچ و تازه‌های دنیای فرآورده‌های گوشتی و گیاهی.',
   canon=BASE+'blog/', active='blog', jsonld=ld(bc([('خانه','index.html'),('وبلاگ','blog.html')]))),
 'blog-single': dict(file='blog-single.html', title='سوسیس چیست؟ آیا می‌توان سوسیس را خام مصرف کرد؟ | مجله بریون',
   desc='پاسخ کارشناسان بریون به پرسش مصرف خام سوسیس؛ تفاوت سوسیس پخته، دودی و خام، نشانه‌های سوسیس باکیفیت و راهنمای نگهداری صحیح پس از خرید.',
   canon=BASE+'sausage-raw-consumption-cooking-guide/', active='blog',
   jsonld=ld([bc([('خانه','index.html'),('وبلاگ','blog.html'),('سوسیس چیست؟','blog-single.html')]),
     {"@context":"https://schema.org","@type":"Article","headline":"سوسیس چیست؟ آیا می‌توان سوسیس را خام مصرف کرد؟",
      "datePublished":"2026-08-17","author":{"@type":"Organization","name":"تحریریه مجله بریان گوشت"},
      "publisher":{"@type":"Organization","name":"بریون","logo":{"@type":"ImageObject","url":BASE+"assets/img/logo.png"}}}])) ,
 'contact': dict(file='contact.html', title='تماس با بریون | تلفن کارخانه، فروش و آدرس آمل | واحد فروش و نمایندگی',
   desc='راه‌های ارتباط با بریان گوشت آمل: تلفن کارخانه ۰۱۱-۴۴۴۴۸۰۷۱، واحد فروش ۰۱۱-۴۴۴۸۰۷۸، ایمیل info@beryon.com و آدرس شهرک صنعتی امام‌زاده عبدالله آمل + فرم تماس.',
   canon=BASE+'contact-us/', active='contact', jsonld=ld([ORG, bc([('خانه','index.html'),('تماس با ما','contact.html')])])),
 'agency': dict(file='agency.html', title='درخواست نمایندگی سوسیس و کالباس بریون | ثبت‌نام | شرایط همکاری',
   desc='فرم آنلاین درخواست نمایندگی فروش بریون؛ شرایط همکاری، مراحل بررسی درخواست و ثبت اطلاعات محل کار، انبار و توان توزیع برای پیوستن به شبکه پخش بریان گوشت آمل.',
   canon=BASE+'فرم-درخواست-نمایندگی/', active='', jsonld=ld(bc([('خانه','index.html'),('اعطای نمایندگی','agency.html')]))),
 'catalog': dict(file='catalog.html', title='کاتالوگ محصولات بریون | ۶۱ فرآورده گوشتی و گیاهی | استعلام قیمت عمده',
   desc='دانلود کاتالوگ کامل محصولات بریون شامل مشخصات، درصد گوشت، وزن بسته‌بندی و شرایط نگهداری همه ۶۱ محصول در پنج گروه محصولی.',
   canon=BASE+'?sl=qaampo', active='catalog', jsonld=ld(bc([('خانه','index.html'),('کاتالوگ','catalog.html')]))),
 'product-single': dict(file='product-single.html', title='سوسیس پنیری بریون ۶۰% گوشت قرمز | کوکتل پنیری | مشخصات و قیمت عمده',
   desc='سوسیس ۶۰ درصد گوشت قرمز با پنیر (کوکتل پنیری) بریون؛ وزن هر قطعه ۵۰ گرم، بسته‌بندی وکیوم یک کیلوگرمی، ماندگاری ۴۵ روز. ترکیبات، شرایط نگهداری و استعلام قیمت عمده.',
   canon=BASE+'productall/سوسیس-60-گوشت-قرمز-با-پنیر-کوکتل/', active='products',
   jsonld=ld([{"@context":"https://schema.org","@type":"Product","name":"سوسیس ۶۰% گوشت قرمز با پنیر (کوکتل پنیری) بریون",
     "brand":{"@type":"Brand","name":"بریون"},"category":"سوسیس و کوکتل",
     "description":"کوکتل پنیری با ۶۰٪ گوشت قرمز تازه و پنیر کش‌دار، بدون مواد نگهدارنده مصنوعی.",
     "image":BASE+"assets/img/cat-sausage.webp","weight":{"@type":"QuantitativeValue","value":"50","unitCode":"GRM"}},
     bc([('خانه','index.html'),('محصولات','products.html'),('سوسیس و کوکتل','cat-sausage.html'),('کوکتل پنیری','product-single.html')])])),
}

head = io.open(os.path.join(SRC,'partials','head.html'), encoding='utf-8').read()
header = io.open(os.path.join(SRC,'partials','header.html'), encoding='utf-8').read()
footer = io.open(os.path.join(SRC,'partials','footer.html'), encoding='utf-8').read()

ACT_KEYS = ['home','products','about','blog','catalog','feedback','contact']

for key, meta in PAGES.items():
    body = io.open(os.path.join(SRC,'pages',key+'.html'), encoding='utf-8').read()
    # توکن‌های محتوا
    body = body.replace('{{CAT_STRIP}}', cat_strip())
    body = re.sub(r'\{\{GRID:(\w+)\}\}', lambda m: grid(m.group(1)), body)
    body = re.sub(r'\{\{OTHER_CATS:(\w+)\}\}', lambda m: other_cats(m.group(1)), body)
    # هدر: کلاس فعال
    h = header
    for ak in ACT_KEYS:
        h = h.replace('{{ACT_%s}}' % ak, ' class="active" aria-current="page"' if meta['active'] == ak else '')
    hd = (head.replace('{{TITLE}}', meta['title']).replace('{{DESC}}', meta['desc'])
              .replace('{{CANON}}', meta['canon']).replace('{{JSONLD}}', meta['jsonld']).replace('{{PRELOAD}}', meta.get('preload', '')).replace('{{BODY_CLASS}}', meta.get('body_class', '')))
    html = hd + h + body + footer
    io.open(os.path.join(OUT, meta['file']), 'w', encoding='utf-8').write(html)
    print('built', meta['file'], len(html), 'bytes')

# robots + sitemap
io.open(os.path.join(OUT,'robots.txt'),'w',encoding='utf-8').write(
    'User-agent: *\nAllow: /\nSitemap: https://beryon.com/sitemap.xml\n')
sm = ['<?xml version="1.0" encoding="UTF-8"?>',
      '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
for key, meta in PAGES.items():
    sm.append('<url><loc>%s</loc><lastmod>2026-10-20</lastmod><changefreq>weekly</changefreq><priority>%s</priority></url>'
              % (meta['canon'].rstrip('/'), '1.0' if key=='index' else '0.8'))
sm.append('</urlset>')
io.open(os.path.join(OUT,'sitemap.xml'),'w',encoding='utf-8').write('\n'.join(sm))
print('robots.txt + sitemap.xml written')
