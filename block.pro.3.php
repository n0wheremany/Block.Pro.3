<?
/*=====================================================
BlockPro 3 - Модуль для вывода блоков с новостями на страницах сайта DLE (тестировался на 9.7)
=====================================================
Автор: ПафНутиЙ 
URL: http://pafnuty.name/
ICQ: 817233 
email: p13mm@yandex.ru
=====================================================
Файл:  block.pro.3.php
------------------------------------------------------
Версия: 3.0a (14.10.2012)
Альфаверсия - пробуем ООП (подсмотрено в разных местах, на 90% у Александра Фомина)
=====================================================*/

//как всегда главная строка)))
if( ! defined( 'DATALIFEENGINE' ) ) {
	die( '<iframe width="853" height="480" style="margin: 50px;" src="http://www.youtube.com/embed/mTQLW3FNy-g" frameborder="0" allowfullscreen></iframe>' );
}

if(!class_exists('BlockPro')) {
	class BlockPro {

		//конструктор конфига модуля
		public function __construct($BlockProConfig)
		{
			// Подключаем DLE_API
			global $db, $config, $category;
			include ('engine/api/api.class.php');
			$this->dle_api = $dle_api;
			
			// Задаем конфигуратор класса
			$this->config = $BlockProConfig;
		}

		/*
		 * Главный метод класса BlockPro
		 */
		public function runBlockPro()
		{
			if ($this->config['cache_live']) {
				$this->config['prefix'] = '';
			}


			// Пробуем подгрузить содержимое модуля из кэша
			$output = false;

			if( !$this->config['nocache'])
			{
				$output = $this->dle_api->load_from_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)));
			}
			
			// Если значение кэша для данной конфигурации получено, выводим содержимое кэша
			if($output !== false)
			{
				$this->showOutput($output);
				return;
			}
			
			// Если в кэше ничего не найдено, генерируем модуль заново

			$wheres = array();

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
			//$news = $this->dle_api->load_table (PREFIX."_post", $fields = "*", $where = '1', $multirow = false, $start = 0, $limit = 10, $sort = '', $sort_order = 'desc');

			$news = $this->dle_api->load_table (PREFIX."_post", "*", '1', true, 0, 10, '', 'desc');


			if(empty($news)) $news = array();

			//Пробегаем по массиву с новостями и формируем список
			$output = '';
			foreach ($news as $key => $newsItem) {
				// $newsItem
			

				/**
				 *
				 *
				 *
				 *
				 *
				 */

				$output .= $this->applyTemplate($this->config['template'],
					array(
						'{title}'          	=> $newsItem["title"],
						'{full-link}'		=> $this->getPostUrl($newsItem),
						// '{description}'   => $description,
					),
					array(
						// "'\[show_name\\](.*?)\[/show_name\]'si" => !empty($name)?"\\1":'',
						// "'\[show_description\\](.*?)\[/show_description\]'si" => !empty($description)?"\\1":'',
					)
				);
			}

			// Если разрешено кэширование, сохраняем в кэш по данной конфигурации
			if(/*$this->dle_api->dle_config['allow_cache'] == 'yes'*/ !$this->config['nocache'])
			{
				$this->dle_api->save_to_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)), $output);
			}
			
			// Выводим содержимое модуля
			$this->showOutput($output);
		}


		/*
		 * @param $post - массив с информацией о статье
		 * @return string URL для категории
		 */
		public function getPostUrl($post)
		{
			if($this->dle_api->dle_config['allow_alt_url'] == 'yes')
			{
				if(
					($this->dle_api->dle_config['version_id'] < 9.6 && $post['flag'] && $this->dle_api->dle_config['seo_type'])
						||
					($this->dle_api->dle_config['version_id'] >= 9.6 && ($this->dle_api->dle_config['seo_type'] == 1 || $this->dle_api->dle_config['seo_type'] == 2))
				)
				{
					if(intval($post['category']) && $this->dle_api->dle_config['seo_type'] == 2)
					{
						$url = $this->dle_api->dle_config['http_home_url'].get_url(intval($post['category'])).'/'.$post['id'].'-'.$post['alt_name'].'.html';
					}
					else
					{
						$url = $this->dle_api->dle_config['http_home_url'].$post['id'].'-'.$post['alt_name'].'.html';
					}
				}
				else
				{
					$url = $this->dle_api->dle_config['http_home_url'].date("Y/m/d/", strtotime($post['date'])).$post['alt_name'].'.html';
				}
			}
			else
			{
				$url = $this->dle_api->dle_config['http_home_url'].'index.php?newsid='.$post['id'];
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
			// echo "<hr>";
			// echo "<pre class='orange'>"; print_r($output); echo "</pre>";
			// echo "<hr>";
		}

	}//конец класса BlockPro
} 

	// Цепляем конфиг модуля
	$BlockProConfig = array(
		'block_id'		=> !empty($block_id)?$block_id:'1',
		'template'		=> !empty($template)?$template:'blockpro',
		'prefix'		=> !empty($BpPrefix)?$BpPrefix:'news_',
		'nocache'		=> !empty($nocache)?$nocache:false,
		'cache_live'	=> !empty($cache_live)?$cache_live:false,
		'news_num'		=> !empty($news_num)?$news_num:'10',
		// 'date'      => !empty($date)?$date:'old',
		// 'ring'      => !empty($ring)?$ring:'yes',
		// 'scan'      => !empty($scan)?$scan:'all_cat',
		// 'anchor'    => !empty($anchor)?$anchor:'name',
		// 'title'     => !empty($title)?$title:'title',
	);
	
	// Создаем экземпляр класса для перелинковки и запускаем его главный метод
	$BlockPro = new BlockPro($BlockProConfig);
	$BlockPro->runBlockPro();




?>