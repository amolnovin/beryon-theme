#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
بررسی وجود همه توابع/کلاس‌های فراخوانی‌شده در نماهای Blade
============================================================
هر تابع beryon_*() یا App\\Class::method() که در resources/views استفاده شده
باید در app/ تعریف شده باشد. این اسکریپر از خطای رایج «اختراع نام متد از
حافظه» جلوگیری می‌کند (پیش‌تر ۵ فراخوانی اشتباه در Admin/SettingsPage.php
را همین‌طور پیدا کردیم).

اجرا: python3 tools/api_check.py
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME = os.path.join(ROOT, 'beryon-wp')
VIEWS = os.path.join(THEME, 'resources/views')
APP = os.path.join(THEME, 'app')

# توابع هسته وردپرس/ووکامرس که نیازی به تعریف در قالب ندارند
WP_CORE = set()


def collect_php_files():
    files = []
    for base, _dirs, names in os.walk(APP):
        for name in names:
            if name.endswith('.php'):
                files.append(os.path.join(base, name))
    files.append(os.path.join(THEME, 'functions.php'))
    files.append(os.path.join(THEME, 'index.php'))
    return files


def defined_functions(files):
    found = set()
    for path in files:
        text = open(path, encoding='utf-8').read()
        for match in re.findall(r'^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(', text, re.M):
            found.add(match.lower())
    return found


def defined_methods(files):
    """نگاشت ClassName → مجموعه متدهای public static."""
    table = {}
    for path in files:
        text = open(path, encoding='utf-8').read()
        classes = re.findall(r'(?:final\s+|abstract\s+)*class\s+([A-Za-z0-9_]+)', text)
        methods = set(re.findall(r'public\s+(?:static\s+)?function\s+([a-zA-Z0-9_]+)', text))
        consts = set(re.findall(r'public\s+const\s+([A-Z0-9_]+)', text))
        props = set(re.findall(r'public\s+(?:static\s+)?\$([a-zA-Z0-9_]+)', text))
        for klass in classes:
            table.setdefault(klass, set()).update(methods | consts | props)
    return table


def view_files():
    out = []
    for base, _dirs, names in os.walk(VIEWS):
        for name in names:
            if name.endswith('.blade.php'):
                out.append(os.path.join(base, name))
    return sorted(out)


def main():
    files = collect_php_files()
    funcs = defined_functions(files)
    methods = defined_methods(files)
    errors = []
    calls = 0

    for path in view_files():
        rel = os.path.relpath(path, VIEWS)
        text = open(path, encoding='utf-8').read()

        # توابع beryon_* و سایر توابع سفارشی قالب
        for name in set(re.findall(r'\b(beryon_[a-z0-9_]+)\s*\(', text)):
            calls += 1
            if name.lower() not in funcs:
                errors.append('%s: تابع تعریف‌نشده → %s()' % (rel, name))

        # App\Class::member
        for klass, member in set(re.findall(r'App\\([A-Za-z0-9_]+)::([a-zA-Z0-9_]+)', text)):
            calls += 1
            if klass not in methods:
                errors.append('%s: کلاس تعریف‌نشده → App\\%s' % (rel, klass))
            elif member not in methods[klass]:
                errors.append('%s: عضو تعریف‌نشده → App\\%s::%s' % (rel, klass, member))

        # فراخوانی کامپوننت <x-name ...>
        for component in set(re.findall(r'<x-([a-z0-9\-\.]+)', text)):
            component_path = os.path.join(VIEWS, 'components', component.replace('.', '/') + '.blade.php')
            if not os.path.isfile(component_path):
                errors.append('%s: کامپوننت مفقود → components/%s' % (rel, component))

        # @include('…')
        for dotted in set(re.findall(r"@include\(\s*'([a-zA-Z0-9_.\-/]+)'", text)):
            target = os.path.join(VIEWS, dotted.replace('.', '/') + '.blade.php')
            if not os.path.isfile(target):
                errors.append('%s: نمای @include مفقود → %s' % (rel, dotted))

        # کلیدهای beryon_opt('group.key') باید در config/defaults.json باشند
        for key in set(re.findall(r"beryon_opt\(\s*'([a-z0-9_]+\.[a-z0-9_]+)'", text)):
            group, _, name = key.partition('.')
            defaults = os.path.join(THEME, 'config/defaults.json')
            import json
            data = json.load(open(defaults, encoding='utf-8'))
            if group not in data:
                errors.append('%s: گروه تنظیمات ناشناخته → %s' % (rel, group))
            elif name not in data.get(group, {}):
                errors.append('%s: کلید تنظیمات تعریف‌نشده → %s' % (rel, key))

    # ---- لایه PHP: ارجاع به اعضای کلاس‌های App باید وجود داشته باشد
    php_targets = view_files() + [f for f in files if f.endswith('.php')]
    for path in php_targets:
        rel = os.path.relpath(path, THEME)
        text = open(path, encoding='utf-8').read()
        for klass, member in set(re.findall(r'(?<![:\\\w])([A-Z][A-Za-z0-9_]+)::([a-zA-Z0-9_]+)', text)):
            if klass not in methods:
                continue
            if member in methods[klass]:
                continue
            # ارث‌بری یا ثابت‌های PHP مجاز است
            if member in ('class', 'FUNCTION', 'METHOD'):
                continue
            errors.append('%s: عضو تعریف‌نشده → %s::%s' % (rel, klass, member))

    print('نماهای بررسی‌شده: %d | فراخوانی‌ها: %d | توابع قالب: %d | کلاس‌ها: %d'
          % (len(view_files()), calls, len([f for f in funcs if f.startswith('beryon_')]), len(methods)))

    if errors:
        print('\nخطاها (%d):' % len(errors))
        for item in sorted(set(errors)):
            print(' -', item)
        return 1

    print('همه فراخوانی‌های نماها به API موجود ارجاع می‌دهند ✔')
    return 0


if __name__ == '__main__':
    sys.exit(main())
