#!/usr/bin/env python3
"""Build the WordPress.org-ready plugin ZIP (no bundled updater; WP.org serves updates)."""
from pathlib import Path
import argparse, hashlib, re, zipfile
p = argparse.ArgumentParser(); p.add_argument('--output', type=Path); a = p.parse_args()
root = Path(__file__).resolve().parents[1]
main = next(f for f in root.glob('*.php') if re.search(r'Plugin Name:', f.read_text()))
slug = main.stem if main.stem != 'index' else root.name
version = re.search(r'Version:\s*([0-9.]+)', main.read_text()).group(1)
output = a.output or root / 'dist' / (slug + '.' + version + '.zip')
output.parent.mkdir(parents=True, exist_ok=True)
excluded = {'.git', '.github', '.review', '.wordpress-org', 'tests', 'scripts', 'dist', 'releases', 'node_modules', 'vendor', 'docs'}
excluded_files = {'.DS_Store', '.gitignore', '.editorconfig', '.distignore', 'phpcs.xml.dist', 'README.md', 'PROJECT.md'}
files = [f for f in sorted(root.rglob('*'))
         if f.is_file() and f.relative_to(root).parts[0] not in excluded and f.name not in excluded_files]
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as z:
    for f in files:
        info = zipfile.ZipInfo.from_file(f, slug + '/' + str(f.relative_to(root)))
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        z.writestr(info, f.read_bytes())
with zipfile.ZipFile(output) as z:
    assert z.testzip() is None
    assert all((i.external_attr >> 16) & 0o7777 == 0o644 for i in z.infolist()), "Packaged files must use 0644 permissions"
    assert {n.split('/')[0] for n in z.namelist()} == {slug}
    assert slug + '/readme.txt' in z.namelist() and slug + '/' + main.name in z.namelist()
    for f in files:
        assert z.read(slug + '/' + str(f.relative_to(root))) == f.read_bytes()
digest = hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix(output.suffix + '.sha256').write_text(digest + '  ' + output.name + '\n')
print(output); print(digest)
