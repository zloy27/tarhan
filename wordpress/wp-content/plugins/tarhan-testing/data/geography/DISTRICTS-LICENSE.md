# District-label supplement

The machine-readable `districts.json` metadata and `districts/*.json` tables are a derivative database under the [Open Database License 1.0](https://opendatacommons.org/licenses/odbl/1-0/).

Boundary data: © [OpenStreetMap contributors](https://www.openstreetmap.org/copyright) / Wambacher, distributed by [geoBoundaries](https://www.geoboundaries.org/). Source boundary set: RUS ADM2, representing 2017, pinned snapshot `9469f09`. Source URL, checksum, derivation method and version are in `districts.json`. These simplified polygons support disambiguation, not authoritative current administrative validation.

Point coordinates and Russian name variants: © [GeoNames contributors](https://www.geonames.org/), [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).

Changes: populated-place points were matched to enclosing simplified district polygons; existing district labels were kept; missing labels were supplemented, preferring a matching Russian name from GeoNames. Ambiguous or unmatched cases were left unchanged. No nearest-district assignment was made.

The complete adapted district-label database is distributed in the adjacent `districts/*.json` files. It contains only public geographic labels and identifiers, no participant data. Reproduction code is `tests/enrich-geography-districts.py` in the project source. The independent original GeoNames regional directory retains its CC BY 4.0 attribution.
