1.9.13 - 1-06-2022
- Minor improvements.

1.9.8 - 25-10-2021
- Disable function wptexturize() convert_chars() for title.
- NEW hook `kama_meta_keywords`.

1.6 - 13-12-2020
- paged becomes real part of implode.
- `$paged` parametesr added to `kama_meta_title` hook.

1.5 - 01-12-2020
- `get_term_meta()` for meta_robots(). Simplify the code of meta_robots().
- Hook `kama_meta_robots_close` renamed to `kama_meta_robots`

1.4 - 27.11.2020
- New: Изменил логику `og_meta()`:
    - Теперь все собирается в массив. 
    - Появился хук `pre_kama_og_meta_image`. 
    - ВАЖНО! Переименован хук `kama_og_meta_thumb_id` на `kama_og_meta_image`. 
    - Появился хук `kama_og_meta_elements_values`.
