#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
افزودن قواعد CSS غایب پنل به resources/css/admin.css.

بررسی کلاس‌ها نشان داد این‌ها در مارک‌آپ پنل استفاده می‌شوند ولی قاعده‌ای
ندارند (یعنی کارت‌های آمار و نوار فیلتر «صندوق پیام‌ها» بی‌استایل می‌مانند):
    .beryon-stat-row  .beryon-stat  .beryon-stat--warn  .beryon-filters  .beryon-form
"""
import io
import os
import sys

PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                    'beryon-wp', 'resources', 'css', 'admin.css')

BLOCK = """/* ---------- کارت‌های آمار (صندوق پیام‌ها) ---------- */
.beryon-stat-row{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:18px}
.beryon-stat{display:flex;flex-direction:column;gap:4px;background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:14px 16px;box-shadow:0 6px 20px rgba(24,26,30,.04)}
.beryon-stat strong{font-size:1.35rem;font-weight:800;line-height:1.5;color:var(--ink)}
.beryon-stat span{font-size:.8rem;font-weight:700;color:var(--muted)}
.beryon-stat--warn{border-color:rgba(245,179,1,.5);background:linear-gradient(180deg,#fffaf0,#fff)}
.beryon-stat--warn strong{color:#a8730a}
.beryon-stat--ok{border-color:rgba(30,158,85,.34);background:linear-gradient(180deg,#f2fbf6,#fff)}
.beryon-stat--ok strong{color:#146c3b}
.beryon-stat--brand{border-color:rgba(213,35,44,.3);background:linear-gradient(180deg,var(--brand-soft),#fff)}
.beryon-stat--brand strong{color:var(--brand-2)}

/* ---------- نوار فیلتر ---------- */
.beryon-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:14px 16px;margin-bottom:18px;box-shadow:0 6px 20px rgba(24,26,30,.04)}
.beryon-filters input[type="search"],.beryon-filters select{min-width:170px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-family:inherit;font-size:.88rem}
.beryon-filters input[type="search"]{flex:1 1 240px}
.beryon-filters input:focus,.beryon-filters select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(213,35,44,.12);outline:none}
.beryon-filters .button{border-radius:10px;margin:0}

/* ---------- فرم‌های پنل ---------- */
.beryon-form{margin:0}
#beryon-submissions-form .tablenav{margin-bottom:8px}
#beryon-submissions-form .widefat td,#beryon-submissions-form .widefat th{vertical-align:middle}

"""

RESPONSIVE_960 = """@media screen and (max-width:960px){
  .beryon-grid-2{grid-template-columns:1fr}"""

RESPONSIVE_960_NEW = """@media screen and (max-width:960px){
  .beryon-grid-2{grid-template-columns:1fr}
  .beryon-stat-row{grid-template-columns:repeat(3,minmax(0,1fr))}"""

RESPONSIVE_600 = """@media screen and (max-width:600px){
  .beryon-card{padding:16px}"""

RESPONSIVE_600_NEW = """@media screen and (max-width:600px){
  .beryon-card{padding:16px}
  .beryon-stat-row{grid-template-columns:repeat(2,minmax(0,1fr))}
  .beryon-filters input[type="search"],.beryon-filters select{min-width:0;width:100%}"""


def main():
    with io.open(PATH, encoding='utf-8') as handle:
        source = handle.read()

    if '.beryon-stat-row{' in source:
        print('admin.css: قواعد آمار پیش‌تر افزوده شده — عبور')
        return 0

    anchor = '/* ---------- واکنش‌گرایی پنل ---------- */'
    for old, new, label in [
        (anchor, BLOCK + anchor, 'block'),
        (RESPONSIVE_960, RESPONSIVE_960_NEW, 'media-960'),
        (RESPONSIVE_600, RESPONSIVE_600_NEW, 'media-600'),
    ]:
        if source.count(old) != 1:
            raise SystemExit('ANCHOR FAIL [%s] count=%d' % (label, source.count(old)))
        source = source.replace(old, new)

    tmp = PATH + '.tmp'
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(source)
    os.replace(tmp, PATH)

    opens = source.count('{')
    closes = source.count('}')
    print('admin.css: %d بایت | آکولاد %d/%d %s' % (
        len(source.encode('utf-8')), opens, closes, '✔' if opens == closes else '✗ نامتوازن!'))
    return 0 if opens == closes else 1


if __name__ == '__main__':
    sys.exit(main())
