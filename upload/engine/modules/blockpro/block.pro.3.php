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

		// Конструктор конфига модуля
		public function __construct($BlockProConfig)
		{

			global $db, $config, $category;

			// DB
			$this->db = $db;

			// Получаем конфиг DLE
			$this->dle_config = $config;
			
			// Задаем конфигуратор класса
			$this->config = $BlockProConfig;
		}

		/*
		 * Главный метод класса BlockPro
		 */
		public function runBlockPro()
		{
			// Определяем сегодняшнюю дату
			$tooday = date("Y-m-d H:i:s");

			// Проверка версии DLE
			if ($this->dle_config['version_id'] >= 9.6) $newVersion = true;
			
			// Если установлено время жизи кеша - убираем префикс news_ чтобы кеш не чистился автоматом
			if ($this->config['cache_live']) 
			{
				$this->config['prefix'] = ''; 
			}

			// Пробуем подгрузить содержимое модуля из кэша
			$output = false;

			// Если nocache не установлен - добавляем префикс (по умолчанию news_) к файлу кеша. 
			if( !$this->config['nocache'])
			{
				$output = dle_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)));
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

			// Условие для отображения только тех постов, дата публикации которых уже наступила
			$wheres[] = 'date < "'.$tooday.'"';


			// Разбираемся с временными рамками отбора новостей
			if ($this->config['day']) 
			{
				$interval = $this->config['day'];
				$dateStart = 'AND date >= "'.$tooday.'" - INTERVAL "'.$interval.'" DAY'; 
			}

			if (!$this->config['day']) 
			{
				$dateStart = '';
			}
			
			
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
				$selectRows = 'p.id, p.autor, p.date, p.short_story, p.xfields, p.title, p.category, p.alt_name, p.allow_comm, p.comm_num, e.news_read, e.allow_rate, e.rating, e.vote_num, e.votes';
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

			$news = $this->load_table (PREFIX . "_post p LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id)", $selectRows, $where . $dateStart, true, $this->config['start_from'], $this->config['limit'], $sort, $ordering);


			if(empty($news)) $news = array();

			// Пробегаем по массиву с новостями и формируем список
			$output = '';

			if (empty($news)) {
				$output .= '<span style="color: #f00">По заданным критериям материалов нет</span>';
			}
			foreach ($news as $key => $newsItem) 
			{

				// Выводим картинку (возможно можно как то оптимизировать этот код)
				switch($this->config['image'])
				{
					// Изображение из дополнительного поля
					case 'xfield':

						$xfields = xfieldsdataload($newsItem['xfields']);
						if(!empty($xfields) && !empty($xfields[$this->config['image']]))
						{
							$image = getImage($xfields[$this->config['image']]);
							$imageFull = ($this->config['image_full']) ? getImage($xfields[$this->config['image']],1) : '';
						}
						break;
					
					// Первое изображение из полного описания
					case 'full_story':
						$image = $this->getImage($newsItem['full_story']);
						$imageFull = ($this->config['image_full']) ? $this->getImage($newsItem['full_story'],1) : '';
						break;
					
					// По умолчанию - краткая новость
					default:
						$image = $this->getImage($newsItem['short_story']);
						$imageFull = ($this->config['image_full']) ? $this->getImage($newsItem['short_story'],1) : '';
						break;
				}
				// Картинка-заглушка
				if ($image == '') {
					$image = "/templates/".$this->dle_config['skin']."/images/".$this->config['noimage']; 
				}
				if ($imageFull == '') {
					$imageFull = "/templates/".$this->dle_config['skin']."/images/".$this->config['noimage_full'];
				}


				/**
				 * Основной код формирующий новость
				 */

				$output .= $this->applyTemplate($this->config['template'],
					array(
						'{title}'          	=> $newsItem["title"],
						'{full-link}'		=> $this->getPostUrl($newsItem),
						'{image}'			=> $image,
						'{image_full}'		=> $imageFull,
						'{short-story}' 	=> $this->textLimit($newsItem['short_story'], $this->config['text_limit']),
                    	'{full-story}'  	=> $this->textLimit($newsItem['full-story'], $this->config['text_limit']),
						// '{description}'   => $description,
					),
					array(
						// "'\[show_name\\](.*?)\[/show_name\]'si" => !empty($name)?"\\1":'',
						// "'\[show_description\\](.*?)\[/show_description\]'si" => !empty($description)?"\\1":'',
					)
				);
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
		public function load_table ($table, $fields = "*", $where = '1', $multirow = false, $start = 0, $limit = 0, $sort = '', $sort_order = 'desc')
		{
			if (!$table) return false;

			if ($sort!='') $where.= ' order by '.$sort.' '.$sort_order;
			if ($limit>0) $where.= ' limit '.$start.','.$limit;
			$q = $this->db->query("SELECT ".$fields." from ".$table." where ".$where);
			if ($multirow)
			{
				while ($row = $this->db->get_row())
				{
					$values[] = $row;
				}
			}
			else
			{
				$values = $this->db->get_row();
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
				$data = strip_tags($data, "<br>");
				$data = trim(str_replace( array("<br>",'<br />'), " ", $data));

				if($count && dle_strlen($data, $config['charset'] ) > $count)
				{
					$data = dle_substr( $data, 0, $count, $config['charset'] ). "&hellip;";					
					if( !$wordcut && ($word_pos = dle_strrpos( $data, ' ', $config['charset'] )) ) 
						$data = dle_substr( $data, 0, $word_pos, $config['charset'] ). "&hellip;";

					$data = dle_substr($data, 0, $count, $config['charset']);
					if(($temp_dmax = dle_strrpos($data, ' ', $config['charset'])))
					{
						$data = dle_substr($data, 0, $temp_dmax, $config['charset']);
					}
				}
			}
			
			return $data;
		}

		/**
		 * @param $post - массив с информацией о статье
		 * @return string URL картинки
		 */

		public function getImage($post, $img_original)
		{
			$dir = ROOT_DIR . '/uploads/blockpro/'; //задаём папку для картинок
			if(!is_dir($dir)){						//Создаём и назначаем права, если нет таковых
				@mkdir($dir, 0755);
				@chmod($dir, 0755);
			} 
			if(!chmod($dir, 0755)) {
				@chmod($dir, 0755);
			}

			if(preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $post, $m)) {
				
				//адрес первой картинки в новости
				$url = $m[1][0]; 	

				//Выдёргиваем оригинал, на случай если уменьшить надо до размеров больше, чем thumb в новости									
				$imgOriginal = str_ireplace('/thumbs', '', $url); 		
				
				// Если Есть параметр img_size - включаем обрезку картинок
				if ($this->config['img_size']) 
				{ 	
					// Удаляем имя текущего домена из строки
					$urlShort = str_ireplace('http://'.$_SERVER['HTTP_HOST'].'/', '', $url);	

					// Если http нет - работаем с картинкой, если есть http или смайлик/спойлер - пропускаем, такая картинка нам не пойдёт
					if (stripos($urlShort, 'http') === false && stripos($urlShort, 'dleimages') === false) 
					{
						$urlShort = ROOT_DIR .'/'. $urlShort;					
						
						//Определяем новое имя файла
						$fileName = $this->config['img_size']."_".strtolower(basename($urlShort)); 		

						//Если картинки нет - создаём её
						if(!file_exists($dir.$fileName)) { 

							//Разделяем высоту и ширину
							$img_size = explode('x', $this->config['img_size']); 	

							//Подрубаем нормальный класс для картинок(?), а не то говно, которое в DLE
							require_once ENGINE_DIR.'/modules/blockpro/resize_class.php'; 				
							$resizeImg = new resize($urlShort);
							$resizeImg -> resizeImage(						//создание уменьшенной копии
								$img_size[0], 								//Ширина
								$img_size[1], 								//Высота
								$this->config['resize_type']				//Метод уменьшения (exact, portrait, landscape, auto, crop)
								); 
							$resizeImg -> saveImage($dir.$fileName); 		//Сохраняем картинку в папку /uploads/blockpro
						
						}					 									
						
						$data = $this->dle_config['http_home_url']."uploads/blockpro/".$fileName;	
					} 
					// Если внешняя картинка - возвращаем её
					// else {
					// 	$data = $url;						
					// }	
					
				} 
				//Если ничего не подошло - возвращаем пустое место, для подстановки заглушки.
				else {
					$data = $url;
				}

				//Отдаём нормальную картинку (или уменьшенную движком) если требуется
				if ($img_original)
				{ 												
					$data = $imgOriginal;
				}

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
					($this->dle_config['version_id'] < 9.6 && $post['flag'] && $this->dle_config['seo_type'])
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
					$url = $this->dle_config['http_home_url'].date("Y/m/d/", strtotime($post['date'])).$post['alt_name'].'.html';
				}
			}
			else
			{
				$url = $this->dle_config['http_home_url'].'index.php?newsid='.$post['id'];
			}

			return $url;
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
			// Подключаем файл шаблона $template.tpl, заполняем его
			$tpl = new dle_template();
			$tpl->dir = TEMPLATE_DIR;
			$tpl->load_template($template.'.tpl');

			// Заполняем шаблон переменными
			foreach($vars as $var => $value)
			{
				$tpl->set($var, $value);
			}

			// Заполняем шаблон блоками
			foreach($blocks as $block => $value)
			{
				$tpl->set_block($block, $value);
			}

			// Компилируем шаблон (что бы это не означало ;))
			$tpl->compile($template);

			// Выводим результат
			return $tpl->result[$template];
		}

		/*
		 * Метод выводит содержимое модуля в браузер
		 * @param $output - строка для вывода
		 */
		public function showOutput($output)
		{
			echo $output;
			echo "<hr>";
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
		'cache_live'	=> !empty($cache_live)?$cache_live:false,					// Время жизни кеша

		'start_from'	=> !empty($start_from)?$start_from:'0',						// C какой новости начать вывод
		'limit'			=> !empty($limit)?$limit:'10',								// Количество новостей в блоке	

		'day'			=> !empty($day)?$day:false,									// Временной период для отбора новостей		
		'sort'			=> !empty($sort)?$sort:'top',								// Сортировка (top, date, comms, rating, views)
		'order'			=> !empty($order)?$order:'new',								// Направление сортировки


		'image'			=> !empty($image)?$image:'shotrt_story',					// Откуда брать картинку (short_story, full_story или xfield)
		'noimage'		=> !empty($noimage)?$noimage:'noimage.png',					// Картинка-заглушка
		'img_size'		=> !empty($img_size)?$img_size:false,						// Размер уменьшенной копии картинки
		'resize_type'	=> !empty($resize_type)?$resize_type:'auto',				// Опция уменьшения копии картинки (exact, portrait, landscape, auto, crop)

		'image_full'	=> !empty($image_full)?$image_full:false,					// Откуда брать картинку (short_story, full_story или xfield)
		'noimage_full'	=> !empty($noimage_full)?$noimage_full:'noimage-full.png',	// Картинка-заглушка

		'text_limit'	=> !empty($text_limit)?$text_limit:'150',					// Ограничение количества символов
		'wordcut'		=> !empty($wordcut)?$wordcut:false,							// Жесткое ограичеие кол-ва символов, без учета слов
		

		'showstat'		=> !empty($showstat)?$showstat:false,						// Показывать время стату по блоку


	);
	
	// Создаем экземпляр класса для перелинковки и запускаем его главный метод
	$BlockPro = new BlockPro($BlockProConfig);
	$BlockPro->runBlockPro();

	//Показываем статистику генерации блока
	if($showstat) echo "<p style='color:red;'>Время выполнения: <b>". round((microtime(true) - $start), 6). "</b> сек</p>";
?>