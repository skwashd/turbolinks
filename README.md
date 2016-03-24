# TODO

- Everything in `<head>`: `<title>`, `<meta>` tags, etc.
- Optimize the case where a region is theoretically different but practically identical: path-based visibility can cause two different paths to still yield the same exact HTML output, and currently we then always send the "updated" region.
- The contextual links' `?destination=` query argument does not get updated.
- ‘Back’ and ‘Forward’ buttons in browser are broken.


# Requirements

- Theme always has the same layout (e.g. no conditional `<body>` classes based on current path/route).
- Theme always has the same set of regions.

Or it is at least configured to always the same layout and regions.

Example: Bartik can be problematic, but it usually is not, because it seldomly is configured to use the flexibility it provides. As long as you just always use the first sidebar and there always is some block visible in there, there is no problem. See `bartik_preprocess_html().
