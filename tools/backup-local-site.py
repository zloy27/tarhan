"""Read-only Local backup; never prints database credentials."""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import zipfile

p = argparse.ArgumentParser()
p.add_argument('--dump', required=True)
p.add_argument('--port', required=True, type=int)
args = p.parse_args()
site = Path.home() / 'Local Sites/tarhan-wp'
public = site / 'app/public'
config = (public / 'wp-config.php').read_text(encoding='utf-8-sig')
def setting(name):
    match = re.search(r"define\s*\(\s*['\"]" + name + r"['\"]\s*,\s*(['\"])(.*?)\1\s*\)", config)
    if not match:
        raise RuntimeError('Cannot safely read database configuration')
    return match.group(2)

target = Path.home() / 'Local Backups/tarhan' / datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
target.mkdir(parents=True, exist_ok=False)
sql = target / 'database.sql'
fd, temp = tempfile.mkstemp(suffix='.cnf')
try:
    with os.fdopen(fd, 'w', encoding='utf-8') as f:
        f.write('[client]\n')
        for key, value in [('user', setting('DB_USER')), ('password', setting('DB_PASSWORD'))]:
            f.write(key + '="' + value.replace('\\', '\\\\').replace('"', '\\"') + '"\n')
    cmd = [args.dump, '--defaults-extra-file=' + temp, '--host=127.0.0.1',
           '--port=' + str(args.port), '--protocol=TCP', '--single-transaction',
           '--quick', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF',
           '--hex-blob', '--result-file=' + str(sql), setting('DB_NAME')]
    result = subprocess.run(cmd, capture_output=True)
    if result.returncode:
        raise RuntimeError('Database backup failed; credentials and raw diagnostics suppressed')
finally:
    Path(temp).unlink(missing_ok=True)
if sql.stat().st_size < 1000:
    raise RuntimeError('Database export unexpectedly small')
archive = target / 'site-files.zip'
count = 0
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=3) as z:
    for root in [public, site / 'app/tarhan-private', site / 'conf']:
        if not root.exists():
            raise RuntimeError('Required site directory missing: ' + root.name)
        for file in root.rglob('*'):
            if file.is_file():
                z.write(file, file.relative_to(site).as_posix())
                count += 1
with zipfile.ZipFile(archive) as z:
    if z.testzip() is not None:
        raise RuntimeError('Archive verification failed')
def sha(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda: f.read(1024 * 1024), b''):
            h.update(block)
    return h.hexdigest()
manifest = {'created_at': datetime.datetime.now().isoformat(), 'files': count,
            'archive_verified': True, 'restore_tested': False,
            'artifacts': {f.name: {'bytes': f.stat().st_size, 'sha256': sha(f)} for f in [sql, archive]}}
(target / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
print(json.dumps({'backup': str(target), **manifest}, ensure_ascii=False))
