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

//как всегда главная строка)))
if( ! defined( 'DATALIFEENGINE' ) ) {
	//Самый правильный посыл хакеру)))
	die( '<iframe width="853" height="480" style="margin: 50px;" src="http://www.youtube.com/embed/mTQLW3FNy-g" frameborder="0" allowfullscreen></iframe>' );
}
$start = microtime(true);
if(!class_exists('BlockPro')) {
	class BlockPro {

		//конструктор конфига модуля
		public function __construct($BlockProConfig)
		{
			// Подключаем DLE_API
			global $db, $config, $category;
			// include ('engine/api/api.class.php');
			// $this = $dle_api;

			$this->db = $db;
			$this->dle_config = $config;
			
			// Задаем конфигуратор класса
			$this->config = $BlockProConfig;
		}

		/*
		 * Главный метод класса BlockPro
		 */
		public function runBlockPro()
		{
			//Показываем статистику генерации блока если нужно
			if($this->config['showstat']) {
				$start = microtime(true);
			}
			//Определяем сегодняшнюю дату
			$tooday = date("Y-m-d H:i:s");

			if ($this->dle_config['version_id'] >= 9.6) $newVersion = true;
			
			//Если установлено время жизи кеша - убираем префикс news_ чтобы кеш не чистился автоматом
			if ($this->config['cache_live']) {
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
            $wheres[] = 'approve = 1';

            // Разбираемся с временными рамками отбора новостей
            if ($this->config['day'] && $this->config['day'] != 0) {
            	$interval = $this->config['day'];
            } elseif ($this->config['last']) {
            	# code...
            }
            $dateStart .= ' date >= "'.$tooday.'" - INTERVAL "'.$interval.'" DAY'; 

            // Условие для отображения только тех постов, дата публикации которых уже наступила
            $wheres[] = 'date < "'.$tooday.'"';
            
            // Условие для фильтрации текущего id
            // $wheres[] = 'id != '.$this->config['postId'];
            
            // Складываем условия
            $where = implode(' AND ', $wheres);
            
            // Направление сортировки по убыванию или возрастанию
            $ordering = $this->config['order'] == 'new'?'DESC':'ASC';

            // Сортировка новостей 
            switch ($this->config['sort']) {
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
            	$selectRows = '';
            }
            


            // $news = $this->load_table (PREFIX."_post", $selectRows, $where.' AND '.$dateWhere, true, 0, $this->config['links'], 'id', $ordering);

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

			$news = $this->load_table (PREFIX . "_post p LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id)", $selectRows, $where.' AND '.$dateStart, true, $this->config['start_from'], $this->config['limit'], $sort, $ordering);


			if(empty($news)) $news = array();

			//Пробегаем по массиву с новостями и формируем список
			$output = '';
			foreach ($news as $key => $newsItem) {
				// $newsItem

				$image = '';
				switch($this->config['image'])
                {
                    // Изображение из дополнительного поля
                    case 'xfield':

                        $xfields = xfieldsdataload($newsItem['xfields']);
                        if(!empty($xfields) && !empty($xfields[$this->config['image']]))
                        {
                            $image = getImage($xfields[$this->config['image']]);
                        }
                        break;
                    
                    // Первое изображение из полного описания
                    case 'full_story':
                        $image = $this->getImage($newsItem['full_story']);
                        break;
                    
                    // По умолчанию - краткая новость
                    default:
                    	$image = $this->getImage($newsItem['short_story']);
                        break;
                }
                if ($image == '') {
                	$image = "/templates/".$this->dle_config['skin']."/images/".$this->config['noimage'];
                }


				/**
				 * Основной код формирующий новость
				 */

				$output .= $this->applyTemplate($this->config['template'],
					array(
						'{title}'          	=> $newsItem["title"],
						'{full-link}'		=> $this->getPostUrl($newsItem),
						'{image}'			=> $image,
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
			
			//Показываем статистику генерации блока
			if($this->config['showstat']) {
				$output .= "<p style='color:red;'>Время выполнения: <b>". round((microtime(true) - $start), 6). "</b> сек</p>";
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

				$url = $m[1][0]; 										//адрес первой картинки в новости
				$imgOriginal = str_ireplace('/thumbs', '', $url); 		//Выдёргиваем оригинал, на случай если уменьшить надо до размеров больше, чем thumb в новости

				if ($this->config['img_size']) { 						//Если Есть параметр img_size - включаем обрезку картинок


					//--------------------------------------------
					// >>>> Вот эту часть надо както доработать ...наверное

					$urlShort = explode('/uploads/', $url); 			//разбиваем на две части, чтоб отсечь имя домена
					if(count($urlShort) != 2) continue; 				// да ну нафиг, если в нескольких папках uploads					
					$urlShort = ROOT_DIR . '/uploads/' . $urlShort[1]; 	//Берём второй кусок и подставляем root_dir, чтоб скормить классу по обработке картинок	
					//if(!is_file($urlShort))  continue; 				//а файл ли это? (пока закомментил, класс ошибок не выдаёт, надо проверять на хостинге)

					// <<<< Вот эту часть надо както доработать ...наверное
					//--------------------------------------------


					$fileName = $this->config['img_size']."_".strtolower(basename($urlShort)); 		//Определяем новое имя файла
					
					if(!file_exists($dir.$fileName)) { //Если картинки нет - создаём её
						$img_size = explode('x', $this->config['img_size']); 						//Разделяем высоту и ширину

						require_once ENGINE_DIR.'/modules/blockpro/resize_class.php'; 				//Подрубаем нормальный класс для картинок(?), а не то говно, которое в DLE
						$resizeImg = new resize($urlShort);
						$resizeImg -> resizeImage(						//создание уменьшенной копии
							$img_size[0], 								//Ширина
							$img_size[1], 								//Высота
							$this->config['resize_type']				//Метод уменьшения (exact, portrait, landscape, auto, crop)
							); 
						$resizeImg -> saveImage($dir.$fileName); 		//Сохраняем картинку в папку /uploads/blockpro
					
					}					 									
					
					$data = $this->dle_config['http_home_url']."uploads/blockpro/".$fileName;					
				
				} else { 												//Отдаём нормальную картинку (или уменьшенную движком)
					$data = $url;
				}

				 // echo "<pre class='orange'>"; print_r("/templates/".$this->dle_config['skin']."/images/".$this->config['noimage']); echo "</pre>"; 

				return $data;
			}
			
		}

		/*
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

		/*
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
		'template'		=> !empty($template)?$template:'blockpro/blockpro', 	// Название шаблона (без расширения)
		'prefix'		=> !empty($BpPrefix)?$BpPrefix:'news_', 				// Дефолтный префикс кеша
		'nocache'		=> !empty($nocache)?$nocache:false,						// Не использовать кеш
		'cache_live'	=> !empty($cache_live)?$cache_live:false,				// Время жизни кеша

		'start_from'	=> !empty($start_from)?$start_from:'0',					// C какой новости начать вывод
		'limit'			=> !empty($limit)?$limit:'10',							// Количество новостей в блоке	

		'day'			=> !empty($day)?$day:'30',								// Временной период для отбора новостей		
		'sort'			=> !empty($sort)?$sort:'top',							// Сортировка (top, date, comms, rating, views)
		'order'			=> !empty($order)?$order:'new',							// Направление сортировки


		'image'			=> !empty($image)?$image:'shotrt_story',				// Откуда брать картинку (short_story, full_story или xfield)
		'img_size'		=> !empty($img_size)?$img_size:false,					// Размер уменьшенной копии картинки
		'resize_type'	=> !empty($resize_type)?$resize_type:'auto',			// Опция уменьшения копии картинки (exact, portrait, landscape, auto, crop)
		'noimage'		=> !empty($noimage)?$noimage:'noimage.png',				// Картинка-заглушка

		'showstat'		=> !empty($showstat)?$showstat:false,					// Показывать время стату по блоку


	);
	
	// Создаем экземпляр класса для перелинковки и запускаем его главный метод
	$BlockPro = new BlockPro($BlockProConfig);
	$BlockPro->runBlockPro();
	echo "<p style='color:red;'>Время выполнения: <b>". round((microtime(true) - $start), 6). "</b> сек</p>";
?>