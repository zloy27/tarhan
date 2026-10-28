"""Publish only the public district-label supplement and its attribution."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import shutil

root = Path(__file__).resolve().parents[1]
source = root / 'wordpress/wp-content/plugins/tarhan-testing/data/geography'
output = root / 'public/documents/geography-districts.zip'
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for name in ('districts.json', 'DISTRICTS-LICENSE.md', 'ATTRIBUTION.md'):
        archive.write(source / name, name)
    for path in sorted((source / 'districts').glob('*.json')):
        archive.write(path, 'districts/' + path.name)
theme = root / 'wordpress/wp-content/themes/tarhan'
(theme / 'assets/data').mkdir(parents=True, exist_ok=True)
shutil.copyfile(output, theme / 'assets/data/geography-districts.zip')
shutil.copyfile(root / 'src/html/components/privacy-policy/privacy-policy.html', theme / 'privacy-policy-content.html')
print('Published district data and policy page content.')
