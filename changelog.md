1.5 - 01-12-2020
- Hook `kama_meta_robots_close` renamed to `kama_meta_robots`

1.4 - 27.11.2020
- New: Изменил логику `og_meta()`:
    - Теперь все собирается в массив. 
    - Появился хук `pre_kama_og_meta_image`. 
    - ВАЖНО! Переименован хук `kama_og_meta_thumb_id` на `kama_og_meta_image`. 
    - Появился хук `kama_og_meta_elements_values`.
