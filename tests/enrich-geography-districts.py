"""Add missing district labels by point-in-polygon, without guessing the nearest district.

GeoNames names/points: CC BY 4.0. Boundary-derived table: ODbL 1.0,
OpenStreetMap contributors / Wambacher via geoBoundaries (RUS ADM2 2017).
The generated, separately licensed table is published with the plugin.
"""
from pathlib import Path
from collections import defaultdict
import csv, io, zipfile, json, math, re, hashlib, unicodedata
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / '.address-source.local'
OUT = ROOT / 'wordpress/wp-content/plugins/tarhan-testing/data/geography'
BOUNDARIES = 'https://github.com/wmgeolab/geoBoundaries/raw/9469f09/releaseData/gbOpen/RUS/ADM2/geoBoundaries-RUS-ADM2_simplified.geojson'

def clean(s):
    return unicodedata.normalize('NFC', ''.join(c for c in unicodedata.normalize('NFD', s) if c not in {'\u0300', '\u0301'}))

def key(s):
    text = clean(s).lower().replace('ё', 'е')
    text = re.sub(r'\b(?:municipal|district|rayon|raion|rajon)\b', '', text)
    text = re.sub(r'sk(?:iy|ij)\b', 'sky', text)
    return re.sub(r'[^a-zа-я0-9]', '', text)

def ru_district(row):
    names = [clean(n) for n in row[3].split(',') if re.search('[А-Яа-я]', n) and re.search(r'(район|округ)$', clean(n), re.I)]
    if not names: return None
    name = names[-1]
    return re.sub(r'\b(Район|Округ)\b', lambda m: m[0].lower(), name)

def inside(x, y, ring):
    hit = False
    for a, b in zip(ring, ring[1:] + ring[:1]):
        if (a[1] > y) != (b[1] > y) and x < (b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0]: hit = not hit
    return hit

def main():
    boundary_file = CACHE / 'rus-adm2-simplified.geojson'
    if not boundary_file.exists():
        with urlopen(BOUNDARIES, timeout=60) as response: boundary_file.write_bytes(response.read())
    catalogue = json.loads((OUT/'regions.json').read_text(encoding='utf-8'))
    regional = {r['id']: json.loads((OUT/(r['id']+'.json')).read_text(encoding='utf-8')) for r in catalogue['regions']}
    with zipfile.ZipFile(CACHE/'RU.zip') as archive:
        rows = list(csv.reader(io.TextIOWrapper(archive.open('RU.txt'), encoding='utf-8'), delimiter='\t'))
    region_by_code = {r[10]: r[0] for r in rows if r[7] == 'ADM1' and r[0] in regional}
    aliases = defaultdict(dict)
    for row in rows:
        if row[7] != 'ADM2': continue
        region_id = region_by_code.get(row[10])
        name = ru_district(row)
        if not region_id or not name: continue
        for variant in [row[1], row[2]] + row[3].split(','):
            aliases[region_id][key(variant)] = name
    geometry = []
    grid = defaultdict(list)
    for feature in json.loads(boundary_file.read_text(encoding='utf-8'))['features']:
        g = feature['geometry']
        if g['type'] not in {'Polygon', 'MultiPolygon'}: continue
        polygons = g['coordinates'] if g['type'] == 'MultiPolygon' else [g['coordinates']]
        points = [p for polygon in polygons for p in polygon[0]]
        x0, y0 = min(p[0] for p in points), min(p[1] for p in points)
        x1, y1 = max(p[0] for p in points), max(p[1] for p in points)
        idx = len(geometry)
        geometry.append((x0,y0,x1,y1,polygons,feature['properties']['shapeName']))
        for gx in range(math.floor(x0/2), math.floor(x1/2)+1):
            for gy in range(math.floor(y0/2), math.floor(y1/2)+1): grid[gx,gy].append(idx)
    extra = defaultdict(dict)
    for row in rows:
        region_id = region_by_code.get(row[10])
        record = regional.get(region_id, {}).get(row[0])
        if not record or record[1]: continue
        direct = aliases[region_id].get(key(row[11])) if row[11] else None
        if direct:
            extra[region_id][row[0]] = direct; continue
        x,y = float(row[5]),float(row[4])
        found = set()
        for idx in grid[math.floor(x/2),math.floor(y/2)]:
            x0,y0,x1,y1,polygons,name = geometry[idx]
            if not (x0 <= x <= x1 and y0 <= y <= y1): continue
            if any(inside(x,y,p[0]) and not any(inside(x,y,hole) for hole in p[1:]) for p in polygons):
                found.add(aliases[region_id].get(key(name), name))
        if len(found) == 1: extra[region_id][row[0]] = found.pop()
    version = hashlib.sha256(boundary_file.read_bytes() + Path(__file__).read_bytes() + (CACHE/'RU.zip').read_bytes()).hexdigest()[:12]
    result = {'version':version,'regions':sorted(extra),'license':'ODbL 1.0','license_url':'https://opendatacommons.org/licenses/odbl/1-0/',
        'attribution':'OpenStreetMap contributors / Wambacher via geoBoundaries; Russian names: GeoNames (CC BY 4.0)',
        'boundary_year':2017,'source':BOUNDARIES,'source_sha256':hashlib.sha256(boundary_file.read_bytes()).hexdigest(),
        'method':'Point-in-polygon against simplified ADM2 boundaries; ambiguous/outside matches left empty. Not official current GAR boundaries.'}
    (OUT/'districts').mkdir(exist_ok=True)
    for region_id, qualifiers in extra.items():
        (OUT/'districts'/(region_id+'.json')).write_text(json.dumps(qualifiers,ensure_ascii=False,separators=(',',':')),encoding='utf-8')
    (OUT/'districts.json').write_text(json.dumps(result,ensure_ascii=False,separators=(',',':')),encoding='utf-8')
    print(json.dumps({'district_labels':sum(len(v) for v in extra.values()),'version':version}))

if __name__ == '__main__': main()
