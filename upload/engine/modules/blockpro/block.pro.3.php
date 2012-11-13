<?
/*
=============================================================================
BlockPro 3 - Модуль для вывода блоков с новостями на страницах сайта DLE (тестировался на 9.7)
=============================================================================
Автор модуля: ПафНутиЙ 
URL: http://blockpro.ru/
ICQ: 817233 
email: pafnuty10@gmail.com
-----------------------------------------------------------------------------
Автор методов: Александр Фомин
URL: http://mithrandir.ru/
email: mail@mithrandir.ru
=============================================================================
Файл:  block.pro.3.php
-----------------------------------------------------------------------------
Версия: 3.0a 
=============================================================================
*/ 

// Как всегда главная строка)))
if( ! defined( 'DATALIFEENGINE' ) ) {
	// Самый правильный посыл хакеру)))
	die( '<iframe width="853" height="480" style="margin: 50px;" src="http://www.youtube.com/embed/mTQLW3FNy-g" frameborder="0" allowfullscreen></iframe>' );
}
if($showstat) $start = microtime(true);
if(!class_exists('BlockPro')) {
	class BlockPro {
		protected static $_instance;
		// Конструктор конфига модуля
		private function __construct()
		{
			global $db, $config, $category, $category_id, $cat_info, $lang;

			$this->db = $db;
			$this->cat_info = $cat_info;
			$this->dle_lang = $lang;

			// Получаем конфиг DLE
			$this->dle_config = $config;
		}
				
		public function __clone(){}
		private function __wakeup() {}
		
		/**
		* Статическая функция, которая возвращает
		* экземпляр класса или создает новый при
		* необходимости
		*
		* @return SingletonTest
		*/
		 public static function getInstance() {
			if (null === self::$_instance) {
		        	self::$_instance = new self();
		        }
		        return self::$_instance;
		}

		/*
		 * Новый конфиг
		 */
		public function set_config($cfg) {
			// Задаем конфигуратор класса
			$this->config = $cfg;
		}

		/*
		 * Обновление даных
		 */
		public function get_category() {
			global $category, $category_id;
			$this->category_id = $category_id;
			$this->category = $category;		
		}

		/*
		 * Главный метод класса BlockPro
		 */
		public function runBlockPro($BlockProConfig)
		{

			$this->get_category();
			$this->set_config($BlockProConfig);

			// Защита от фашистов )))) (НУЖНА ЛИ? )
			$this->config['post_id']     = @$this->db->safesql(strip_tags(str_replace('/', '', $this->config['post_id'])));
			$this->config['not_post_id'] = @$this->db->safesql(strip_tags(str_replace('/', '', $this->config['not_post_id'])));

			$this->config['author']      = @$this->db->safesql(strip_tags(str_replace('/', '', $this->config['author'])));
			$this->config['not_author']  = @$this->db->safesql(strip_tags(str_replace('/', '', $this->config['not_author'])));

			$this->config['xfilter']     = @$this->db->safesql(strip_tags(str_replace('/', '', $this->config['xfilter'])));
			$this->config['not_xfilter']     = @$this->db->safesql(strip_tags(str_replace('/', '', $this->config['not_xfilter'])));


			// Определяем сегодняшнюю дату
			$tooday = date( "Y-m-d H:i:s", (time() + $this->dle_config['date_adjust'] * 60) );
			// Проверка версии DLE
			if ($this->dle_config['version_id'] >= 9.6) $newVersion = true;
			
			
			// Пробуем подгрузить содержимое модуля из кэша
			$output = false;

			// Если установлено время жизи кеша - убираем префикс news_ чтобы кеш не чистился автоматом
			// и задаём настройки времени жизни кеша в секундах (надо доработать, где то косяк)
			if ($this->config['cache_live']) 
			{
				$this->config['prefix'] = ''; 

				$filedate = ENGINE_DIR.'/cache/'.$this->config['prefix'].'bp_'.md5(implode('_', $this->config)).'.tmp';

				if(@file_exists($filedate)) $cache_time=time()-@filemtime ($filedate);
				else $cache_time = $this->config['cache_live']*60;	
				if ($cache_time>=$this->config['cache_live']*60) $clear_time_cache = 1;
			}

			// Если nocache не установлен - добавляем префикс (по умолчанию news_) к файлу кеша. 
			if( !$this->config['nocache'])
			{
				$output = dle_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)));
			}
			if ($clear_time_cache) {
				$output = false;
			}
			
			// Если значение кэша для данной конфигурации получено, выводим содержимое кэша
			if($output !== false)
			{
				$this->showOutput($output);
				return;
			}
			
			// Если в кэше ничего не найдено, генерируем модуль заново

			$wheres = array();


			// Условие для отображения только постов, прошедших модерацию
			$wheres[] = 'approve';

		
			// Разбираемся с временными рамками отбора новостей, если кол-во дней указано - ограничиваем выборку, если нет - выводим без ограничения даты
			// if ($this->config['day']) 
			// {
			// 	$interval = $this->config['day'];
			// 	$dateStart = 'AND date >= "'.$tooday.'" - INTERVAL "'.$interval.'" DAY'; 
			// }

			// if (!$this->config['day']) 
			// {
			// 	$dateStart = '';
			// }


			// Фильтрация КАТЕГОРИЙ по их ID
			if ($this->config['cat_id'] == 'this') $this->config['cat_id'] = $this->category_id;
			if ($this->config['not_cat_id'] == 'this') $this->config['not_cat_id'] = $this->category_id;
			
			if ($this->config['cat_id'] || $this->config['not_cat_id']) {
				$ignore = ($this->config['not_cat_id']) ? 'NOT ' : '';
				$catArr = ($this->config['not_cat_id']) ? $this->config['not_cat_id'] : $this->config['cat_id'];	
				
				$wheres[] = $ignore.'category regexp "[[:<:]]('.str_replace(',', '|', $catArr).')[[:>:]]"';				
			}

			// Фильтрация НОВОСТЕЙ по их ID
			if ($this->config['post_id'] == 'this') $this->config['post_id'] = $_REQUEST["newsid"];
			if ($this->config['not_post_id'] == 'this') $this->config['not_post_id'] = $_REQUEST["newsid"];

			if ($this->config['post_id'] || $this->config['not_post_id']) {
				$ignorePosts = ($this->config['not_post_id']) ? 'NOT ' : '';
				$postsArr = ($this->config['not_post_id']) ? $this->config['not_post_id'] : $this->config['post_id'];					
				$wheres[] = $ignorePosts.'id regexp "[[:<:]]('.str_replace(',', '|', $postsArr).')[[:>:]]"';				
			}

			// Фильтрация новостей по АВТОРАМ
			if ($this->config['author'] == 'this') $this->config['author'] = $_REQUEST["user"];
			if ($this->config['not_author'] == 'this') $this->config['not_author'] = $_REQUEST["user"];

			if ($this->config['author'] || $this->config['not_author']) {
				$ignoreAuthors = ($this->config['not_author']) ? 'NOT ' : '';
				$authorsArr = ($this->config['not_author']) ? $this->config['not_author'] : $this->config['author'];					
				$wheres[] = $ignoreAuthors.'autor regexp "[[:<:]]('.str_replace(',', '|', $authorsArr).')[[:>:]]"';				
			}

			// Фильтрация новостей по ДОПОЛНИТЕЛЬНЫМ ПОЛЯМ

			if ($this->config['xfilter'] || $this->config['not_xfilter']) {
				$ignoreXfilters = ($this->config['not_xfilter']) ? 'NOT ' : '';
				$xfiltersArr = ($this->config['not_xfilter']) ? $this->config['not_xfilter'] : $this->config['xfilter'];					
				$wheres[] = $ignoreXfilters.'xfields regexp "[[:<:]]('.str_replace(',', '|', $xfiltersArr).')[[:>:]]"';				
			}

			
			// Разбираемся с временными рамками отбора новостей, если кол-во дней указано - ограничиваем выборку, если нет - выводим без ограничения даты
			if(intval($this->config['day'])) $wheres[] =  'date >= "'.$tooday.'" - INTERVAL ' .  intval($this->config['day']) . ' DAY';

			// Условие для отображения только тех постов, дата публикации которых уже наступила
			$wheres[] = 'date < "'.$tooday.'"';
			
			// Условие для фильтрации текущего id
			// $wheres[] = 'id != '.$this->config['postId'];
			
			// Складываем условия
			$where = implode(' AND ', $wheres);
			
			// Направление сортировки по убыванию или возрастанию
			$ordering = $this->config['order'] == 'new'?'DESC':'ASC';

			// Сортировка новостей 
			switch ($this->config['sort']) 
			{
				case 'date':					// Дата
					$sort = 'date '; 			
					break;

				case 'rating':					// Рейтинг
					$sort = 'rating ';			
					break;

				case 'comms':					// Комментарии
					$sort = 'comm_num ';
					break;

				case 'views':					// Просмотры
					$sort = 'news_read ';
					break;

				case 'random':					// Случайные
					$sort = 'RAND() ';
					break;
				
				default:						// Топ как в DLE (сортировка по умолчанию)
					$sort = 'rating '.$ordering.', comm_num '.$ordering.', news_read ';
					break;
			}
			
			// Формирование запроса в зависимости от версии движка

			if ($newVersion) {
				// 9.6 и выше
				$selectRows = 'p.id, p.autor, p.date, p.short_story, p.full_story, p.xfields, p.title, p.category, p.alt_name, p.allow_comm, p.comm_num, e.news_read, e.allow_rate, e.rating, e.vote_num, e.votes';
			} else {
				// старые версии
				$selectRows = '*'; //пока старые версии курят в сторонке
			}

			
			/**
			 * Service function - take params from table
			 * @param $table string - название таблицы
			 * @param $fields string - необходимые поля через запятйю или * для всех
			 * @param $where string - условие выборки
			 * @param $multirow bool - забирать ли один ряд или несколько
			 * @param $start int - начальное значение выборки
			 * @param $limit int - количество записей для выборки, 0 - выбрать все
			 * @param $sort string - поле, по которому осуществляется сортировка
			 * @param $sort_order - направление сортировки
			 * @return array с данными или false если mysql вернуль 0 рядов
			 */
			//$news = $this->load_table (PREFIX."_post", $fields = "*", $where = '1', $multirow = false, $start = 0, $limit = 10, $sort = '', $sort_order = 'desc');

			$news = $this->load_table (PREFIX . '_post p LEFT JOIN ' . PREFIX . '_post_extras e ON (p.id=e.news_id)', $selectRows, $where, true, $this->config['start_from'], $this->config['limit'], $sort, $ordering);


			if(empty($news)) $news = array();

			// Задаём переменную, в котоую будем всё складывать
			$output = '';

			// Если в выборке нет новостей - сообщаем об этом
			if (empty($news)) {
				$output .= '<span style="color: #f00">По заданным критериям материалов нет, попробуйте изменить параметры строки подключения</span>';
				return;
			}
			// Пробегаем по массиву с новостями и формируем список
			foreach ($news as $newsItem) 
			{
				$xfields = xfieldsload();
				$newsItem['date'] = strtotime($newsItem['date']);

				// Формируем ссылки на категории и иконки категорий
				$my_cat = array();
				$my_cat_icon = array();
				$my_cat_link = array();
				$cat_list = explode(',', $newsItem['category']);
				foreach($cat_list as $element) {
					if(isset($this->cat_info[$element])) {
						$my_cat[] = $this->cat_info[$element]['name'];
						if ($this->cat_info[$element]['icon'])
							$my_cat_icon[] = '<img class="bp-cat-icon" src="'.$this->cat_info[$element]['icon'].'" alt="'.$this->cat_info[$element]['name'].'" />';
						else
							$my_cat_icon[] = '<img class="bp-cat-icon" src="{THEME}/blockpro/'.$this->config['noicon'].'" alt="'.$this->cat_info[$element]['name'].'" />';
						if( $this->dle_config['allow_alt_url'] == 'yes' ) 
							$my_cat_link[] = '<a href="'.$this->dle_config['http_home_url'].get_url($element).'/">'.$this->cat_info[$element]['name'].'</a>';
						else 
							$my_cat_link[] = '<a href="'.$PHP_SELF.'?do=cat&category='.$this->cat_info[$element]['alt_name'].'">'.$this->cat_info[$element]['name'].'</a>';
					}
				}
				$categoryUrl = ($newsItem['category']) ? $this->dle_config['http_home_url'] . get_url(intval($newsItem['category'])) . '/' : '/' ;

				// Ссылка на профиль  юзера
				if( $this->dle_config['allow_alt_url'] == 'yes' ) {
					$go_page = $config['http_home_url'].'user/'.urlencode($newsItem['autor']).'/';
				} else {
					$go_page = $PHP_SELF.'?subaction=userinfo&amp;user='.urlencode($newsItem['autor']);
				}

				// Выводим картинку
				switch($this->config['image'])
				{
					// Изображение из дополнительного поля
					case 'short_story':
						$imgArray = $this->getImage($newsItem['short_story'], $newsItem['date']);
						break;
					
					// Первое изображение из полного описания
					case 'full_story':
						$imgArray = $this->getImage($newsItem['full_story'], $newsItem['date']);
						break;
					
					// По умолчанию - первое изображение из краткой новости
					default:
						$xfieldsdata = xfieldsdataload($newsItem['xfields'], $newsItem['date']);
						if(!empty($xfieldsdata) && !empty($xfieldsdata[$this->config['image']]))
						{
							$imgArray = getImage($xfieldsdata[$this->config['image']]);
						}
						break;
				}

				// Определяем переменные, выводящие картинку
				$image = ($imgArray['imgResized']) ? $imgArray['imgResized'] : '{THEME}/blockpro/'.$this->config['noimage'];
				if (!$imgArray['imgResized']) {
					$imageFull = '{THEME}/blockpro/'.$this->config['noimage_full'];
				} else {
					$imageFull = $imgArray['imgOriginal'];
				}

				// Формируем вид даты новости для вывода в шаблон
				if(date('Ymd', $newsItem['date']) == date('Ymd')) {
					$showDate = $this->dle_lang['time_heute'].langdate(', H:i', $newsItem['date']);		
				} elseif(date('Ymd', $newsItem['date'])  == date('Ymd') - 1) {			
					$showDate = $this->dle_lang['time_gestern'].langdate(', H:i', $newsItem['date']);		
				} else {			
					$showDate = langdate($this->dle_config['timestamp_active'], $newsItem['date']);		
				}

				/**
				 * Код, формирующий вывод шаблона новости
				 */
				//$tpl->copy_template = preg_replace("#\{date=(.+?)\}#ie", "langdate('\\1', '{$newsItem['date']}')", $tpl->copy_template );
				// проверяем существует ли файл шаблона, если есть - работаем дальше
				if (file_exists(TEMPLATE_DIR.'/'.$template.'.tpl')) 
				{
					$output .= $this->applyTemplate($this->config['template'],
						array(
							'{title}'          	=> $newsItem['title'],
							'{full-link}'		=> $this->getPostUrl($newsItem),
							'{image}'			=> $image,
							'{image_full}'		=> $imageFull,
							'{short-story}' 	=> $this->textLimit($newsItem['short_story'], $this->config['text_limit']),
	                    	'{full-story}'  	=> $this->textLimit($newsItem['full_story'], $this->config['text_limit']),
	                    	'{link-category}'	=> implode(', ', $my_cat_link),
							'{category}'		=> implode(', ', $my_cat),
							'{category-icon}'	=> implode('', $my_cat_icon),
							'{category-url}'	=> $categoryUrl,
							'{news-id}'			=> $newsItem['id'],
							'{author}'			=> "<a onclick=\"ShowProfile('" . urlencode( $newsItem['autor'] ) . "', '" . $go_page . "', '" . $user_group[$member_id['user_group']]['admin_editusers'] . "'); return false;\" href=\"" . $go_page . "\">" . $newsItem['autor'] . "</a>",
							'{login}'			=> $newsItem['autor'],
							'[profile]'			=> '<a href="'.$go_page.'">',
							'[/profile]'		=> '</a>',
							'[com-link]'		=> $newsItem['allow_comm']?'<a href="'.$this->getPostUrl($newsItem).'#comment">':'',
							'[/com-link]'		=> $newsItem['allow_comm']?'</a>':'',
							'{comments-num}'	=> $newsItem['allow_comm']?$newsItem['comm_num']:'',
							'{views}'			=> $newsItem['news_read'],
							'{date}'			=> $showDate,
							'{rating}'			=> $newsItem['allow_rate']?ShowRating( $newsItem['id'], $newsItem['rating'], $newsItem['vote_num'], 0 ):'', 
							'{vote-num}'		=> $newsItem['allow_rate']?$newsItem['vote_num']:'', 

						),
						array(
							// "'\[show_name\\](.*?)\[/show_name\]'si" => !empty($name)?"\\1":'',
							// "'\[show_description\\](.*?)\[/show_description\]'si" => !empty($description)?"\\1":'',
							"'\[comments\\](.*?)\[/comments\]'si"             => $newsItem['comm_num']!=='0'?'\\1':'',
							"'\[not-comments\\](.*?)\[/not-comments\]'si"     => $newsItem['comm_num']=='0'?'\\1':'',
							"'\[rating\\](.*?)\[/rating\]'si"                 => $newsItem['allow_rate']?'\\1':'',
							"'\[allow-comm\\](.*?)\[/allow-comm\]'si"         => $newsItem['allow_comm']?'\\1':'',
							"'\[not-allow-comm\\](.*?)\[/not-allow-comm\]'si" => !$newsItem['allow_comm']?'\\1':'',
						)

					);
				} else 
				{
					// Если файла шаблона нет - выведем ошибку, а не белый лист.
					$output = '<b style="color: red;">Отсутствует файл шаблона: '.$template.'.tpl</b>';
				}
			}

			// Cохраняем в кэш по данной конфигурации если nocache false
			if(!$this->config['nocache'])
			{
				create_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)), $output);
			}
			
			// Выводим содержимое модуля
			$this->showOutput($output);

			
		}
		
		/**
		 * Service function - take params from table
		 * @param $table string - название таблицы
		 * @param $fields string - необходимые поля через запятйю или * для всех
		 * @param $where string - условие выборки
		 * @param $multirow bool - забирать ли один ряд или несколько
		 * @param $start int - начальное значение выборки
		 * @param $limit int - количество записей для выборки, 0 - выбрать все
		 * @param $sort string - поле, по которому осуществляется сортировка
		 * @param $sort_order - направление сортировки
		 * @return array с данными или false если mysql вернуль 0 рядов
		 */
		public function load_table ($table, $fields = '*', $where = '1', $multirow = false, $start = 0, $limit = 0, $sort = '', $sort_order = 'desc')
		{
			if (!$table) return false;

			if ($sort!='') $where.= ' order by '.$sort.' '.$sort_order;
			if ($limit>0) $where.= ' limit '.$start.','.$limit;
			$q = $this->db->query('SELECT '.$fields.' from '.$table.' where '.$where);
			if ($multirow)
			{
				while ($row = $this->db->get_row($q))
				{
					$values[] = $row;
				}
			}
			else
			{
				$values = $this->db->get_row($q);
			}
			if (count($values)>0) return $values;
			
			return false;

		}

		/**
		 * @param $data - контент
		 * @param $length - максимальный размер возвращаемого контента
		 * 
		 * @return $data - обрезанный результат 
		 */
		public function textLimit($data, $count)
		{
			if ($this->config['text_limit'] != '0') 
			{	
				$data = strip_tags($data, '<br>');
				$data = trim(str_replace( array('<br>','<br />'), ' ', $data));

				if($count && dle_strlen($data, $this->dle_config['charset'] ) > $count)
				{
					$data = dle_substr( $data, 0, $count, $this->dle_config['charset'] ). '&hellip;';					
					if( !$this->config['wordcut'] && ($word_pos = dle_strrpos( $data, ' ', $this->dle_config['charset'] )) ) 
						$data = dle_substr( $data, 0, $word_pos, $this->dle_config['charset'] ). '&hellip;';

				}
			}
			return $data;
		}

		/**
		 * @param $post - массив с информацией о статье
		 * @return array - URL`s уменьшенной картинки и оригинальной
		 * если картинка лежит на внешнем ресурсе и включен параметр remote_images - выводится url внешней картинки 
		 * если картинка не обработалась - выводится пустота
		 */

		public function getImage($post, $date)
		{	
			// Задаём папку для картинок
			$dir_prefix = $this->config['img_size'].'/'.date("Y-m", $date).'/';


			$dir = ROOT_DIR . '/uploads/blockpro/'.$dir_prefix;
			//$dir = ROOT_DIR . '/uploads/blockpro/'.$this->config['img_size'].'/'; 

			// echo "<pre class='orange'>"; print_r($dir); echo "</pre>";

			// Создаём и назначаем права, если нет таковых
			if(!is_dir($dir)){						
				@mkdir($dir, 0755, true);
				@chmod($dir, 0755);
			} 
			if(!chmod($dir, 0755)) {
				@chmod($dir, 0755);
			}

			if(preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $post, $m)) {
				
				// Адрес первой картинки в новости
				$url = $m[1][0]; 	

				//Выдёргиваем оригинал, на случай если уменьшить надо до размеров больше, чем thumb в новости									
				$imgOriginal = str_ireplace('/thumbs', '', $url); 	

				// Удаляем текущий домен из строки
				$urlShort = str_ireplace('http://'.$_SERVER['HTTP_HOST'], '', $imgOriginal);

				// Если http нет - работаем с картинкой, если есть http или смайлик/спойлер - пропускаем, такая картинка нам не пойдёт, вставим заглушку
				if (stripos($urlShort, 'http') === false && stripos($urlShort, 'dleimages') === false && stripos($urlShort, 'engine/data/emoticons') === false) 
				{
					// Если Есть параметр img_size - включаем обрезку картинок
					if ($this->config['img_size']) 
					{
						// Подставляем корневю дирректорию, чтоб ресайзер понял что ему дают.
						$imgResized = ROOT_DIR . $urlShort;					
						
						// Определяем новое имя файла
						$fileName = $this->config['img_size'].'_'.strtolower(basename($imgResized)); 		

						// Если картинки нет - создаём её
						if(!file_exists($dir.$fileName)) 
						{ 
							// Разделяем высоту и ширину
							$img_size = explode('x', $this->config['img_size']); 	

							// Подрубаем нормальный класс для картинок(надо его в деле проверить ещё :-D)
							require_once ENGINE_DIR.'/modules/blockpro/resize_class.php'; 				
							$resizeImg = new resize($imgResized);
							$resizeImg -> resizeImage(						//создание уменьшенной копии
								$img_size[0], 								//Ширина
								$img_size[1], 								//Высота
								$this->config['resize_type']				//Метод уменьшения (exact, portrait, landscape, auto, crop)
								); 
							$resizeImg -> saveImage($dir.$fileName); 		//Сохраняем картинку в папку /uploads/blockpro
						}					 									
						
						$imgResized = $this->dle_config['http_home_url'].'uploads/blockpro/'.$dir_prefix.$fileName;	
					}
					// Если параметра img_size нет - отдаём оригинальную картинку
					else 
					{
						$imgResized = $urlShort;
					}
				} 

				// Если внешняя картинка - возвращаем её, при наличии перемнной remote_images в строке подключения
				elseif (stripos($urlShort, 'http') !== false && $this->config['remote_images']) {
					$imgResized = $urlShort;						
				}

				// Если remote_images не указан - выдаём пустоту
				elseif (stripos($urlShort, 'http') !== false)
				{
					$imgResized = '';
					$imgOriginal = '';
				}

				// Нам нужен на выходе массив из двух картинок
				$data = array('imgResized' => $imgResized, 'imgOriginal' => $imgOriginal);				

				return $data;
			}
			
		}

		/**
		 * @param $post - массив с информацией о статье
		 * @return string URL для категории
		 */
		public function getPostUrl($post)
		{
			if($this->dle_config['allow_alt_url'] == 'yes')
			{
				if(
					($this->dle_config['version_id'] < 9.6 && $this->dle_config['seo_type'])
						||
					($this->dle_config['version_id'] >= 9.6 && ($this->dle_config['seo_type'] == 1 || $this->dle_config['seo_type'] == 2))
				)
				{
					if(intval($post['category']) && $this->dle_config['seo_type'] == 2)
					{
						$url = $this->dle_config['http_home_url'].get_url(intval($post['category'])).'/'.$post['id'].'-'.$post['alt_name'].'.html';
					}
					else
					{
						$url = $this->dle_config['http_home_url'].$post['id'].'-'.$post['alt_name'].'.html';
					}
				}
				else
				{
					$url = $this->dle_config['http_home_url'].date('Y/m/d/', strtotime($post['date'])).$post['alt_name'].'.html';
				}
			}
			else
			{
				$url = $this->dle_config['http_home_url'].'index.php?newsid='.$post['id'];
			}

			return $url;
		}

		/**
		 * ***************
		 * под вопосом
		 * ***************
		 *
		 */
		public function copyTemplate($data = array())
		{
			// заменяем в шаблоне теги
			foreach ($copyTemplate as $value) 
			{
				if ($copyTemplateMetod) {
					$this->tpl->copy_template = preg_replace($value, $this->tpl->copy_template);
				} else {
					$this->tpl->copy_template = str_replace($value, $this->tpl->copy_template);
				}				
				
			}
		}
		/**
		 * Метод подхватывает tpl-шаблон, заменяет в нём теги и возвращает отформатированную строку
		 * @param $template - название шаблона, который нужно применить
		 * @param $vars - ассоциативный массив с данными для замены переменных в шаблоне
		 * @param $blocks - ассоциативный массив с данными для замены блоков в шаблоне
		 *
		 * @return string tpl-шаблон, заполненный данными из массива $data
		 */
		public function applyTemplate($template, $vars = array(), $blocks = array())
		{
			if(!isset($this->tpl)) {
				$this->tpl = new dle_template();
				$this->tpl->dir = TEMPLATE_DIR;
			} else {
				$this->tpl->global_clear();
			}
			// Подключаем файл шаблона $template.tpl, заполняем его
			$this->tpl->load_template($template.'.tpl');

			// Заполняем шаблон переменными
			foreach($vars as $var => $value)
			{
				$this->tpl->set($var, $value);
			}

			// Заполняем шаблон блоками
			foreach($blocks as $block => $value)
			{
				$this->tpl->set_block($block, $value);
			}

			// Компилируем шаблон (что бы это не означало ;))
			$this->tpl->compile($template);

			// Выводим результат
			return $this->tpl->result[$template];
		}

		/*
		 * Метод выводит содержимое модуля в браузер
		 * @param $output - строка для вывода
		 */
		public function showOutput($output)
		{
			echo $output;
			echo '<hr>';
			// echo "<pre class='orange'>"; print_r($output); echo "</pre>";
			// echo "<hr>";
		}

	}//конец класса BlockPro
} 

	// Цепляем конфиг модуля
	$BlockProConfig = array(
		'template'		=> !empty($template)?$template:'blockpro/blockpro', 		// Название шаблона (без расширения)
		'prefix'		=> !empty($BpPrefix)?$BpPrefix:'news_', 					// Дефолтный префикс кеша
		'nocache'		=> !empty($nocache)?$nocache:false,							// Не использовать кеш
		'cache_live'	=> !empty($cache_live)?$cache_live:false,					// Время жизни кеша в минутах

		'start_from'	=> !empty($start_from)?$start_from:'0',						// C какой новости начать вывод
		'limit'			=> !empty($limit)?$limit:'10',								// Количество новостей в блоке	

		'post_id'		=> !empty($post_id)?$post_id:'',							// ID новостей для вывода в блоке (через запятую)
		'not_post_id'	=> !empty($not_post_id)?$not_post_id:'',					// ID игнорируемых новостей (через запятую)

		'author'		=> !empty($author)?$author:'',								// Логины авторов, для показа их новостей в блоке (через запятую)
		'not_author'	=> !empty($not_author)?$not_author:'',						// Логины игнорируемых авторов (через запятую)

		'xfilter'		=> !empty($xfilter)?$xfilter:'',							// Имена дополнительных полей для фильтрации по ним новостей (через запятую)
		'not_xfilter'	=> !empty($not_xfilter)?$not_xfilter:'',					// Имена дополнительных полей для игнорирования показа (через запятую)

		'cat_id'		=> !empty($cat_id)?$cat_id:'',								// Категории для показа	(через запятую)
		'not_cat_id'	=> !empty($not_cat_id)?$not_cat_id:'',						// Игнорируемые категории (через запятую)
		
		'noicon'		=> !empty($noicon)?$noicon:'noicon.png',					// Заглушка для иконок категорий

		'day'			=> !empty($day)?$day:false,									// Временной период для отбора новостей		
		'sort'			=> !empty($sort)?$sort:'top',								// Сортировка (top, date, comms, rating, views)
		'order'			=> !empty($order)?$order:'new',								// Направление сортировки


		'image'			=> !empty($image)?$image:'short_story',						// Откуда брать картинку (short_story, full_story или xfield)
		'remote_images'	=> !empty($remote_images)?$remote_images:false,				// Показывать картинки с других сайтов (уменьшаться они не будут!)
		'noimage'		=> !empty($noimage)?$noimage:'noimage.png',					// Картинка-заглушка маленькая
		'noimage_full'	=> !empty($noimage_full)?$noimage_full:'noimage-full.png',	// Картинка-заглушка большая
		'img_size'		=> !empty($img_size)?$img_size:false,						// Размер уменьшенной копии картинки
		'resize_type'	=> !empty($resize_type)?$resize_type:'auto',				// Опция уменьшения копии картинки (exact, portrait, landscape, auto, crop)
		

		'text_limit'	=> !empty($text_limit)?$text_limit:false,					// Ограничение количества символов
		'wordcut'		=> !empty($wordcut)?$wordcut:false,							// Жесткое ограничение кол-ва символов, без учета длины слов		

		'showstat'		=> !empty($showstat)?$showstat:false,						// Показывать время стату по блоку


	);
	
	// Создаем экземпляр класса для перелинковки и запускаем его главный метод
	//$BlockPro = new BlockPro($BlockProConfig); // В сингелтоне такое неьзя делать
	$BlockPro = BlockPro::getInstance();
	$BlockPro->runBlockPro($BlockProConfig);


	//Показываем статистику генерации блока
	if($showstat) echo '<p style="color:red;">Время выполнения: <b>'. round((microtime(true) - $start), 6). '</b> сек</p>';
?>