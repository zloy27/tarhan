"""Build the local registration directory from GeoNames country and Russian-name dumps.

Data: GeoNames, CC BY 4.0. No remote requests are made by the registration form.
Run with --download to retrieve source snapshots; otherwise reuse ignored local inputs.
"""
from pathlib import Path
from urllib.request import Request, urlopen
from datetime import datetime, timezone
from collections import defaultdict, Counter
import csv, io, json, zipfile, re, hashlib, argparse

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / '.address-source.local'
OUT = ROOT / 'wordpress/wp-content/plugins/tarhan-testing/data/geography'
SOURCES = {
    'RU.zip': 'https://download.geonames.org/export/dump/RU.zip',
    'names-RU.zip': 'https://download.geonames.org/export/dump/alternatenames/RU.zip',
    'readme.txt': 'https://download.geonames.org/export/dump/readme.txt',
}

def rows(path, filename=None):
    with zipfile.ZipFile(path) as archive:
        filename = filename or next(n for n in archive.namelist() if n != 'readme.txt' and n.endswith('.txt'))
        with archive.open(filename) as stream:
            yield from csv.reader(io.TextIOWrapper(stream, encoding='utf-8'), delimiter='\t')

def normalized(text):
    return re.sub(r'\s+', ' ', text.casefold().replace('ё', 'е')).strip()

def dump(path, data):
    path.write_text(json.dumps(data, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--download', action='store_true')
    args = parser.parse_args()
    CACHE.mkdir(exist_ok=True)
    for filename, url in SOURCES.items():
        if args.download:
            with urlopen(Request(url, headers={'User-Agent': 'Tarhan-address-directory/1.0'}), timeout=60) as response:
                (CACHE / filename).write_bytes(response.read())
        if not (CACHE / filename).is_file():
            raise RuntimeError('Missing source; run --download: ' + filename)
    populated = {'PPL', 'PPLC', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'PPLA5', 'PPLL', 'PPLF', 'PPLG'}
    features = {}
    regions = {}
    admin = {}
    for row in rows(CACHE / 'RU.zip', 'RU.txt'):
        if row[8] != 'RU' or not (row[7] in populated or row[7] in {'ADM1', 'ADM2', 'ADM3', 'ADM4'}):
            continue
        features[row[0]] = row
        if row[7] == 'ADM1': regions[row[10]] = row[0]
        elif row[7].startswith('ADM'):
            level = int(row[7][-1]); admin[tuple(row[10:10+level])] = row[0]
    ru = defaultdict(list)
    for row in rows(CACHE / 'names-RU.zip'):
        if row[1] not in features or row[2] != 'ru' or row[7] == '1' or row[6] == '1': continue
        if len(row) > 9 and row[9]: continue  # an ended historic name, even if its historic flag is missing
        name = row[3].strip()
        if name and len(name) <= 200 and re.search('[А-Яа-яЁё]', name):
            ru[row[1]].append((row[4] == '1', row[5] == '1', int(row[0]), name))
    def russian_name(ident):
        names = ru.get(ident, [])
        if names:
            if features[ident][7].startswith('ADM'):
                return sorted(names, key=lambda r: (r[1], not r[3].startswith('Республика '), -r[0], -len(r[3]), r[2]))[0][3]
            return sorted(names, key=lambda r: (-r[0], r[1], r[2]))[0][3]
        row = features[ident]
        # Some tiny localities have only an untagged Cyrillic alternative.
        for name in [row[1]] + row[3].split(','):
            if re.fullmatch(r'[А-Яа-яЁё0-9\s.,()«»\-–—\'’/]+', name) and re.search('[А-Яа-яЁё]', name):
                return name
        return row[1]
    grouped = defaultdict(dict)
    skipped = Counter()
    for ident, row in features.items():
        if row[7] not in populated: continue
        region_id = regions.get(row[10])
        if not region_id:
            skipped['no_region'] += 1; continue
        name = russian_name(ident)
        hierarchy = []
        for level in (2, 3):
            aid = admin.get(tuple(row[10:10+level]))
            if aid:
                title = russian_name(aid)
                if title not in hierarchy: hierarchy.append(title)
        district = ', '.join(hierarchy)
        aliases = list(dict.fromkeys([name] + [r[3] for r in ru.get(ident, [])]))[:8]
        search = normalized(' '.join(aliases + [row[2], district]))
        grouped[region_id][ident] = [name, district, search, int(row[14] or 0)]
    OUT.mkdir(parents=True, exist_ok=True)
    entries = []
    for region_id in sorted(grouped):
        title = russian_name(region_id)
        entries.append({'id': region_id, 'name': title, 'count': len(grouped[region_id])})
        dump(OUT / (region_id + '.json'), grouped[region_id])
    entries.sort(key=lambda r: normalized(r['name']))
    hashes = {name: hashlib.sha256((CACHE / name).read_bytes()).hexdigest() for name in SOURCES}
    builder_hash = hashlib.sha256(Path(__file__).read_bytes()).hexdigest()
    version = hashlib.sha256(('schema1:' + builder_hash + json.dumps(hashes, sort_keys=True)).encode()).hexdigest()[:16]
    metadata = {'source': 'GeoNames', 'source_url': 'https://www.geonames.org/',
        'license': 'CC BY 4.0', 'license_url': 'https://creativecommons.org/licenses/by/4.0/',
        'built_at': datetime.now(timezone.utc).isoformat(), 'version': version,
        'regions_count': len(entries), 'localities_count': sum(r['count'] for r in entries),
        'sources': SOURCES, 'source_sha256': hashes, 'excluded': dict(skipped),
        'scope': 'Populated places from RU snapshot; abandoned/historic places and city sections excluded. Not GAR/FIAS; no street/house verification.'}
    dump(OUT / 'regions.json', {'version': version, 'regions': entries})
    dump(OUT / 'metadata.json', metadata)
    (OUT / 'ATTRIBUTION.md').write_text(
        '# GeoNames registration directory\n\nData © GeoNames contributors, [GeoNames](https://www.geonames.org/), '
        '[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).\n\n'
        'Adapted from RU country and alternate-name snapshots: Russian names selected; populated places grouped by region; '
        'search strings normalized. No streets, houses, population or coordinates are returned to the registration form. '
        'Internal population values only rank search results. This is not the official GAR/FIAS registry. '
        'The source provides no guarantee of completeness, timeliness or accuracy. '
        'See metadata.json for source URLs, hashes, date, version and counts.\n', encoding='utf-8')
    print(json.dumps({'regions': len(entries), 'localities': metadata['localities_count'], 'version': version, 'excluded': dict(skipped)}))

if __name__ == '__main__': main()
