#!/usr/bin/env python3
"""A restrictive source mode must not leak into the installable ZIP."""
from pathlib import Path
import shutil, subprocess, sys, tempfile, zipfile

builder = Path(__file__).resolve().parents[1] / "scripts/build-release.py"
with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp) / "fixture"
    for directory in ("scripts", "includes"):
        (root / directory).mkdir(parents=True, exist_ok=True)
    shutil.copy2(builder, root / "scripts/build-release.py")
    sources = {
        "fixture.php": "<?php /* Plugin Name: Fixture\nVersion: 1.0.0 */",
        "readme.txt": "=== Fixture ===",
        "includes/class.php": "<?php // fixture",
    }
    for name, content in sources.items():
        path = root / name
        path.write_text(content)
        path.chmod(0o600)
    subprocess.run([sys.executable, str(root / "scripts/build-release.py")], check=True, capture_output=True)
    with zipfile.ZipFile(root / "dist/fixture.1.0.0.zip") as package:
        assert package.testzip() is None
        for name, content in sources.items():
            info = package.getinfo("fixture/" + name)
            assert (info.external_attr >> 16) & 0o7777 == 0o644, name
            assert package.read(info) == content.encode(), name
print("PASS restricted source files packaged as 0644; content preserved")
