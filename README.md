Block.Pro.3
===========

Модуль для DLE [pre-alpha]

-
* строка подключения пока такая: {include file="engine/modules/blockpro/block.pro.3.php"}

* Модуль пока умеет выводить то, что видно в шаблоне

Переменные
-------------------
* template - Название шаблона (без расширения)
* prefix - Дефолтный префикс кеша
* nocache - Не использовать кеш
* cache_live - Время жизни кеша
* start_from - C какой новости начать вывод
* limit - Количество новостей в блоке	
* day - Временной период для отбора новостей		
* sort - Сортировка (top, date, comms, rating, views)
* order - Направление сортировки
* image - Откуда брать картинку (short_story, full_story или xfield)
* img_size - Размер уменьшенной копии картинки
* resize_type - Опция уменьшения копии картинки (exact, portrait, landscape, auto, crop)
* noimage - Картинка-заглушка
* showstat - Показывать время стату по блоку